<?php
/**
 * modules/reports/edit-report.php
 * ---------------------------------------------------------------
 * Edit form for a waste report owned by the logged-in resident.
 *
 * Guards:
 *   - require_role(ROLE_RESIDENT)
 *   - Ownership check
 *   - Report must not be locked (is_locked = 0)
 *
 * Loads:
 *   - Existing report data (pre-filled)
 *   - Existing photos (with Remove options)
 *   - Reference dropdown data (categories, concern types, barangays)
 *
 * Submit → actions/reports/update-report.php
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_RESIDENT);

$reportId = input_int('id', 'GET');
if ($reportId <= 0) {
    flash('error', 'Invalid report reference.');
    redirect('modules/reports/my-reports.php');
}

$userId = current_user_id();

// -------------------------------------------------------------
// Fetch report + enforce ownership and lock state
// -------------------------------------------------------------
$report = db_one("
    SELECT report_id, user_id, barangay_id, category_id, concern_type_id,
           status_id, title, description, location, is_locked, submitted_at
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
    exit('You do not have permission to edit this report.');
}

if ((int) $report['is_locked'] === 1) {
    flash('error', 'This report is locked and cannot be edited.');
    redirect('modules/reports/report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Load existing photos
// -------------------------------------------------------------
$photos = db_all("
    SELECT photo_id, file_path, original_filename, uploaded_at
      FROM report_photos
     WHERE report_id = ?
     ORDER BY uploaded_at ASC, photo_id ASC
", [$reportId]);

// -------------------------------------------------------------
// Reference data
// -------------------------------------------------------------
$categories   = get_waste_categories();
$concernTypes = get_concern_types();
$barangays    = get_barangays();

// Preserve old input on validation failure (from submit handler).
$old = $_SESSION['old_edit_report'] ?? [];
unset($_SESSION['old_edit_report']);

// Pre-fill priority: old input → report row.
$val = function (string $key, $fallback = '') use ($old, $report) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    return $report[$key] ?? $fallback;
};

$pageTitle = 'Edit Report';
$pageCss   = ['edit-report.css'];
$pageJs    = ['edit-report.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">My Reports</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/reports/report-details.php?id=<?= (int) $reportId ?>">
            #<?= (int) $reportId ?>
        </a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Edit</span>
    </nav>
    <h1 class="page-title">Edit Waste Report</h1>
    <p class="text-muted">
        You may edit this report while it has not been verified by staff.
        Once verified, editing will no longer be permitted.
    </p>
</section>

<section class="container edit-report-form">
    <form action="<?= e(BASE_URL) ?>/actions/reports/update-report.php"
          method="post" enctype="multipart/form-data"
          id="editReportForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="report_id" value="<?= (int) $reportId ?>">

        <!-- Title -->
        <div class="form-group">
            <label class="form-label" for="title">Report Title <span class="required">*</span></label>
            <input type="text" class="form-control" id="title" name="title"
                   maxlength="255" required
                   value="<?= e($val('title')) ?>">
            <span class="form-error" data-error-for="title"></span>
        </div>

        <!-- Category + Concern -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="category_id">Waste Category <span class="required">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"
                            <?= ((int) $val('category_id')) === (int) $c['category_id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-error" data-error-for="category_id"></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="concern_type_id">Concern Type <span class="required">*</span></label>
                <select class="form-select" id="concern_type_id" name="concern_type_id" required>
                    <option value="">Select a concern type</option>
                    <?php foreach ($concernTypes as $t): ?>
                        <option value="<?= (int) $t['concern_type_id'] ?>"
                            <?= ((int) $val('concern_type_id')) === (int) $t['concern_type_id'] ? 'selected' : '' ?>>
                            <?= e($t['type_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-error" data-error-for="concern_type_id"></span>
            </div>
        </div>

        <!-- Barangay + Location -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="barangay_id">Barangay <span class="required">*</span></label>
                <select class="form-select" id="barangay_id" name="barangay_id" required>
                    <option value="">Select a barangay</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= (int) $b['barangay_id'] ?>"
                            <?= ((int) $val('barangay_id')) === (int) $b['barangay_id'] ? 'selected' : '' ?>>
                            <?= e($b['barangay_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-error" data-error-for="barangay_id"></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="location">Specific Location <span class="required">*</span></label>
                <input type="text" class="form-control" id="location" name="location"
                       maxlength="500" required
                       value="<?= e($val('location')) ?>">
                <span class="form-error" data-error-for="location"></span>
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label class="form-label" for="description">Description <span class="required">*</span></label>
            <textarea class="form-textarea" id="description" name="description"
                      rows="5" maxlength="3000" required><?= e($val('description')) ?></textarea>
            <span class="form-hint">Maximum 3000 characters.</span>
            <span class="form-error" data-error-for="description"></span>
        </div>

        <!-- Existing photos -->
        <fieldset class="photo-fieldset">
            <legend class="form-label">
                Existing Photos
                <span class="form-hint form-hint--inline">
                    Mark photos you want to remove.
                </span>
            </legend>

            <?php if (empty($photos)): ?>
                <div class="empty-state empty-state--compact">
                    <p>No existing photos.</p>
                </div>
            <?php else: ?>
                <div class="existing-photos">
                    <?php foreach ($photos as $p): ?>
                        <label class="existing-photo">
                            <input type="checkbox"
                                   name="remove_photos[]"
                                   value="<?= (int) $p['photo_id'] ?>"
                                   class="existing-photo__checkbox"
                                   data-existing-photo>
                            <span class="existing-photo__overlay" aria-hidden="true">
                                Remove
                            </span>
                            <img src="<?= e(BASE_URL . '/' . ltrim($p['file_path'], '/')) ?>"
                                 alt="<?= e($p['original_filename']) ?>"
                                 loading="lazy"
                                 class="existing-photo__img">
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="photo-summary" id="photoSummary" aria-live="polite"></p>
        </fieldset>

        <!-- New photos -->
        <fieldset class="photo-fieldset">
            <legend class="form-label">
                Add New Photos
                <span class="form-hint form-hint--inline">
                    Minimum total of <?= (int) MIN_PHOTOS_PER_REPORT ?> photos required
                </span>
            </legend>

            <div class="photo-upload">
                <input type="file" id="newPhotos" name="new_photos[]"
                       accept="image/jpeg,image/png,image/webp"
                       multiple class="photo-input">
                <label for="newPhotos" class="photo-dropzone">
                    <span class="photo-dropzone__icon" aria-hidden="true">📷</span>
                    <span class="photo-dropzone__title">Click to add photos</span>
                    <span class="photo-dropzone__hint">
                        JPG, PNG, or WebP · max 5 MB each
                    </span>
                </label>
            </div>

            <span class="form-error" data-error-for="new_photos"></span>
            <div class="photo-preview" id="newPhotoPreview" aria-live="polite"></div>
        </fieldset>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/modules/reports/report-details.php?id=<?= (int) $reportId ?>"
               class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="saveBtn">
                Save Changes
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>