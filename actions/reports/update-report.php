<?php
/**
 * actions/reports/update-report.php
 * ---------------------------------------------------------------
 * Handle the edit-report form POST.
 *
 * Rules enforced server-side:
 *   - Only POST.
 *   - require_role(ROLE_RESIDENT).
 *   - Ownership check: report.user_id === current_user_id.
 *   - Report must not be locked (is_locked = 0).
 *   - Field validation (same rules as submission).
 *   - Photos after edit must be ≥ MIN_PHOTOS_PER_REPORT.
 *
 * Transaction:
 *   1. UPDATE waste_reports
 *   2. INSERT new report_photos
 *   3. DELETE marked-for-removal report_photos
 *   4. INSERT report_status_history (audit entry)
 *
 * After commit:
 *   - Unlink removed photo files from disk.
 *
 * On failure:
 *   - Roll back DB.
 *   - Delete orphan new-photo files.
 *   - Reflash and redirect back to the edit form.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reports/my-reports.php');
}

require_role(ROLE_RESIDENT);
verify_csrf();

$userId   = current_user_id();
$reportId = input_int('report_id');

if ($reportId <= 0) {
    flash('error', 'Invalid report reference.');
    redirect('modules/reports/my-reports.php');
}

// -------------------------------------------------------------
// Fetch and validate ownership + lock state
// -------------------------------------------------------------
$report = db_one("
    SELECT report_id, user_id, is_locked, status_id
      FROM waste_reports
     WHERE report_id = ?
     LIMIT 1
", [$reportId]);

if (!$report) {
    flash('error', 'Report not found.');
    redirect('modules/reports/my-reports.php');
}

if ((int) $report['user_id'] !== $userId) {
    http_response_code(403);
    exit('Forbidden.');
}

if ((int) $report['is_locked'] === 1) {
    flash('error', 'This report is locked and cannot be edited.');
    redirect('modules/reports/report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Collect input
// -------------------------------------------------------------
$title         = input_string('title');
$description   = input_string('description');
$location      = input_string('location');
$categoryId    = input_int('category_id');
$concernTypeId = input_int('concern_type_id');
$barangayId    = input_int('barangay_id');
$removeIdsRaw  = $_POST['remove_photos'] ?? [];

if (!is_array($removeIdsRaw)) {
    $removeIdsRaw = [];
}
$removeIds = array_values(array_unique(array_filter(array_map('intval', $removeIdsRaw))));

// Preserve input for re-render on failure.
$_SESSION['old_edit_report'] = [
    'title'           => $title,
    'description'     => $description,
    'location'        => $location,
    'category_id'     => $categoryId,
    'concern_type_id' => $concernTypeId,
    'barangay_id'     => $barangayId,
];

// -------------------------------------------------------------
// Validate fields
// -------------------------------------------------------------
$errors = [];

if ($title === '' || mb_strlen($title) > 255) {
    $errors[] = 'Title is required (max 255 characters).';
}
if ($description === '' || mb_strlen($description) < 10 || mb_strlen($description) > 3000) {
    $errors[] = 'Description must be between 10 and 3000 characters.';
}
if ($location === '' || mb_strlen($location) > 500) {
    $errors[] = 'Location is required (max 500 characters).';
}
if ($categoryId <= 0)    $errors[] = 'Please select a waste category.';
if ($concernTypeId <= 0) $errors[] = 'Please select a concern type.';
if ($barangayId <= 0)    $errors[] = 'Please select a barangay.';

if (!$errors) {
    if (!db_exists("SELECT 1 FROM waste_categories WHERE category_id = ?", [$categoryId])) {
        $errors[] = 'The selected waste category is invalid.';
    }
    if (!db_exists("SELECT 1 FROM concern_types WHERE concern_type_id = ?", [$concernTypeId])) {
        $errors[] = 'The selected concern type is invalid.';
    }
    if (!db_exists("SELECT 1 FROM barangays WHERE barangay_id = ? AND is_active = 1", [$barangayId])) {
        $errors[] = 'The selected barangay is not available.';
    }
}

// -------------------------------------------------------------
// Existing photos (only those belonging to this report)
// -------------------------------------------------------------
$existingPhotos = db_all("
    SELECT photo_id, file_path
      FROM report_photos
     WHERE report_id = ?
", [$reportId]);

$existingIds   = array_map(function ($p) { return (int) $p['photo_id']; }, $existingPhotos);
$existingMap   = [];
foreach ($existingPhotos as $p) {
    $existingMap[(int) $p['photo_id']] = $p['file_path'];
}

// Filter submitted remove IDs to only those that belong to this report.
$validRemoveIds = array_values(array_intersect($removeIds, $existingIds));

// -------------------------------------------------------------
// Incoming new photos
// -------------------------------------------------------------
$newPhotos = [];
if (isset($_FILES['new_photos']) && is_array($_FILES['new_photos']['name'])) {
    $count = count($_FILES['new_photos']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ((int) $_FILES['new_photos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $newPhotos[] = [
            'name'     => $_FILES['new_photos']['name'][$i],
            'type'     => $_FILES['new_photos']['type'][$i],
            'tmp_name' => $_FILES['new_photos']['tmp_name'][$i],
            'error'    => $_FILES['new_photos']['error'][$i],
            'size'     => $_FILES['new_photos']['size'][$i],
        ];
    }
}

// Validate each new photo before starting the transaction.
if (!$errors) {
    foreach ($newPhotos as $p) {
        $check = validate_image_upload($p);
        if (!$check['ok']) {
            $errors[] = $check['error'];
            break;
        }
    }
}

// Enforce the min-photos rule after removal + addition.
$remainingAfterEdit = (count($existingPhotos) - count($validRemoveIds)) + count($newPhotos);
if ($remainingAfterEdit < MIN_PHOTOS_PER_REPORT) {
    $errors[] = 'At least ' . MIN_PHOTOS_PER_REPORT . ' photos must remain on the report.';
}
if ($remainingAfterEdit > MAX_PHOTOS_PER_REPORT) {
    $errors[] = 'A maximum of ' . MAX_PHOTOS_PER_REPORT . ' photos per report is allowed.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('modules/reports/edit-report.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Perform the update inside a transaction.
// Track files to clean up only after commit.
// -------------------------------------------------------------
$savedNewFiles   = [];   // new files to keep on success, delete on rollback
$removedFileRels = [];   // old files to delete only after successful commit

try {
    db_transaction(function () use (
        $reportId, $userId,
        $barangayId, $categoryId, $concernTypeId,
        $title, $description, $location,
        $newPhotos, $validRemoveIds, $existingMap,
        &$savedNewFiles, &$removedFileRels
    ) {
        // 1. Update report row.
        db_execute("
            UPDATE waste_reports
               SET barangay_id = ?, category_id = ?, concern_type_id = ?,
                   title = ?, description = ?, location = ?
             WHERE report_id = ?
        ", [
            $barangayId, $categoryId, $concernTypeId,
            $title, $description, $location,
            $reportId,
        ]);

        // 2. Save new photos to disk + insert rows.
        foreach ($newPhotos as $p) {
            $saved = save_report_photo($p, $reportId);
            if (!$saved['ok']) {
                throw new RuntimeException('Photo upload failed: ' . $saved['error']);
            }
            $savedNewFiles[] = $saved['path'];

            db_execute("
                INSERT INTO report_photos
                    (report_id, file_path, original_filename, file_size, mime_type, uploaded_by)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ", [
                $reportId,
                $saved['path'],
                mb_substr($p['name'], 0, 255),
                (int) $p['size'],
                (string) $p['type'],
                $userId,
            ]);
        }

        // 3. Remove marked photos.
        if (!empty($validRemoveIds)) {
            // Remember their file paths for later disk cleanup.
            foreach ($validRemoveIds as $pid) {
                if (isset($existingMap[$pid])) {
                    $removedFileRels[] = $existingMap[$pid];
                }
            }
            $placeholders = implode(',', array_fill(0, count($validRemoveIds), '?'));
            db_execute(
                "DELETE FROM report_photos WHERE report_id = ? AND photo_id IN ($placeholders)",
                array_merge([$reportId], $validRemoveIds)
            );
        }

        // 4. Audit entry in report_status_history (same status).
        db_execute("
            INSERT INTO report_status_history
                (report_id, old_status_id, new_status_id, changed_by, remarks)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $reportId,
            (int) $report['status_id'],
            (int) $report['status_id'],
            $userId,
            'Report details edited by resident.',
        ]);
    });

} catch (Throwable $e) {
    // Roll back: delete newly saved files that are now orphaned.
    foreach ($savedNewFiles as $rel) {
        $abs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) @unlink($abs);
    }
    error_log('[TrashWise update-report] ' . $e->getMessage());
    flash('error', 'Unable to save your changes right now. Please try again.');
    redirect('modules/reports/edit-report.php?id=' . $reportId);
}

// -------------------------------------------------------------
// After commit: delete removed files from disk.
// -------------------------------------------------------------
foreach ($removedFileRels as $rel) {
    $abs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));

    // Safety: only unlink files under uploads/reports/.
    $uploadsRoot = realpath(UPLOAD_DIR);
    $targetReal  = realpath($abs);

    if ($targetReal && $uploadsRoot && strpos($targetReal, $uploadsRoot) === 0 && is_file($targetReal)) {
        @unlink($targetReal);
    }
}

// Success
unset($_SESSION['old_edit_report']);
flash('success', 'Your report has been updated.');
redirect('modules/reports/report-details.php?id=' . $reportId);