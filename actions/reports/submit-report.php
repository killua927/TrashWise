<?php
/**
 * actions/reports/submit-report.php
 * ---------------------------------------------------------------
 * Handles the resident waste-report submission POST.
 *
 * Pipeline:
 *   1. Only POST
 *   2. require_role(ROLE_RESIDENT)
 *   3. verify_csrf()
 *   4. Validate all fields server-side
 *   5. Validate that ≥ MIN_PHOTOS_PER_REPORT photos are present
 *   6. Begin transaction:
 *        a. INSERT waste_reports (status = Pending)
 *        b. Save each uploaded photo to disk
 *        c. INSERT report_photos rows
 *        d. INSERT report_status_history (initial)
 *        e. INSERT notifications for admins
 *   7. On success: redirect to report-details.php
 *   8. On failure: rollback DB, delete orphaned files, reflash errors
 *
 * Security notes:
 *   - Resident role enforced server-side
 *   - Barangay must be a valid active barangay
 *   - Category/concern IDs must exist
 *   - Photo MIME + size + extension validated
 *   - Filenames are generated, never trusted
 *   - Files stored outside a PHP-executable path
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reports/report-waste.php');
}

require_role(ROLE_RESIDENT);
verify_csrf();

$userId = current_user_id();

// -------------------------------------------------------------
// Collect and validate input
// -------------------------------------------------------------
$title         = input_string('title');
$description   = input_string('description');
$location      = input_string('location');
$categoryId    = input_int('category_id');
$concernTypeId = input_int('concern_type_id');
$barangayId    = input_int('barangay_id');

// Preserve entered values on failure.
$_SESSION['old_report'] = [
    'title'           => $title,
    'description'     => $description,
    'location'        => $location,
    'category_id'     => $categoryId,
    'concern_type_id' => $concernTypeId,
    'barangay_id'     => $barangayId,
];

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
if ($categoryId <= 0) {
    $errors[] = 'Please select a waste category.';
}
if ($concernTypeId <= 0) {
    $errors[] = 'Please select a concern type.';
}
if ($barangayId <= 0) {
    $errors[] = 'Please select a barangay.';
}

// -------------------------------------------------------------
// Verify reference IDs exist and are valid
// -------------------------------------------------------------
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
// Validate photos
// -------------------------------------------------------------
$photos = [];
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $count = count($_FILES['photos']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ((int) $_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $photos[] = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
    }
}

if (count($photos) < MIN_PHOTOS_PER_REPORT) {
    $errors[] = 'At least ' . MIN_PHOTOS_PER_REPORT . ' photos are required.';
}
if (count($photos) > MAX_PHOTOS_PER_REPORT) {
    $errors[] = 'A maximum of ' . MAX_PHOTOS_PER_REPORT . ' photos per report is allowed.';
}

// Pre-validate each photo before starting the transaction.
if (!$errors) {
    foreach ($photos as $p) {
        $check = validate_image_upload($p);
        if (!$check['ok']) {
            $errors[] = $check['error'];
            break;
        }
    }
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('modules/reports/report-waste.php');
}

// -------------------------------------------------------------
// Transactional insert
// -------------------------------------------------------------
$savedFiles = [];   // Track for rollback cleanup.

try {
    $newReportId = db_transaction(function () use (
        $userId, $barangayId, $categoryId, $concernTypeId,
        $title, $description, $location, $photos, &$savedFiles
    ) {
        // 1. Insert report.
        $reportId = db_insert("
            INSERT INTO waste_reports
                (user_id, barangay_id, category_id, concern_type_id,
                 status_id, title, description, location)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $userId,
            $barangayId,
            $categoryId,
            $concernTypeId,
            REPORT_STATUS_ID_PENDING,
            $title,
            $description,
            $location,
        ]);

        // 2. Save photos to disk + insert photo rows.
        foreach ($photos as $p) {
            $saved = save_report_photo($p, $reportId);
            if (!$saved['ok']) {
                throw new RuntimeException('Photo upload failed: ' . $saved['error']);
            }
            $savedFiles[] = $saved['path'];

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

        // 3. Insert initial status history.
        db_execute("
            INSERT INTO report_status_history
                (report_id, old_status_id, new_status_id, changed_by, remarks)
            VALUES
                (?, NULL, ?, ?, ?)
        ", [
            $reportId,
            REPORT_STATUS_ID_PENDING,
            $userId,
            'Report submitted by resident.',
        ]);

        // 4. Notify all admins of the new report.
        db_execute("
            INSERT INTO notifications (user_id, title, message, link, channel)
            SELECT user_id,
                   'New waste report submitted',
                   CONCAT('A new report titled \"', ?, '\" was submitted.'),
                   ?,
                   'in_app'
              FROM users
             WHERE role_id = ?
        ", [
            $title,
            '/modules/reports/report-details.php?id=' . $reportId,
            ROLE_ID_ADMIN,
        ]);

        return $reportId;
    });

    // Success — clean up the preserved form values.
    unset($_SESSION['old_report']);
    flash('success', 'Your waste report has been submitted. Staff will review it shortly.');
    redirect('modules/reports/report-details.php?id=' . $newReportId);

} catch (Throwable $e) {
    // Roll back already-saved files.
    foreach ($savedFiles as $rel) {
        $abs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    error_log('[TrashWise submit-report] ' . $e->getMessage());
    flash('error', 'Unable to submit the report right now. Please try again.');
    redirect('modules/reports/report-waste.php');
}