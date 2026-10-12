<?php
/**
 * modules/reports/report-waste.php
 * ---------------------------------------------------------------
 * Waste report submission form for residents.
 *
 *   GET  → render form
 *   POST → handled by actions/reports/submit-report.php
 *
 * Guards:
 *   - require_role(ROLE_RESIDENT)
 *
 * Loads:
 *   - Waste categories
 *   - Concern types
 *   - Barangays (defaults to resident's barangay)
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_RESIDENT);

// -------------------------------------------------------------
// Reference data
// -------------------------------------------------------------
$categories   = get_waste_categories();
$concernTypes = get_concern_types();
$barangays    = get_barangays();

// Default to the resident's own barangay (from the session).
$defaultBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : 0;

// Preserve submitted values on validation failure.
$old = $_SESSION['old_report'] ?? [];
unset($_SESSION['old_report']);

$pageTitle = 'Report Waste';
$pageCss   = ['report-waste.css'];
$pageJs    = ['report-waste.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Report Waste</span>
    </nav>
    <h1 class="page-title">Submit a Waste Report</h1>
    <p class="text-muted">
        Provide details about the waste concern. At least <strong>two photographs</strong>
        are required as evidence. Your report will be reviewed by staff.
    </p>
</section>

<section class="container report-form">
    <form action="<?= e(BASE_URL) ?>/actions/reports/submit-report.php"
          method="post" enctype="multipart/form-data"
          id="reportForm" novalidate>
        <?= csrf_field() ?>

        <!-- Title -->
        <div class="form-group">
            <label class="form-label" for="title">Report Title <span class="required">*</span></label>
            <input type="text" class="form-control" id="title" name="title"
                   maxlength="255" required
                   placeholder="e.g. Overflowing bin near public market"
                   value="<?= e($old['title'] ?? '') ?>">
            <span class="form-error" data-error-for="title"></span>
        </div>

        <!-- Category + Concern Type -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="category_id">Waste Category <span class="required">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"
                            <?= (isset($old['category_id']) && (int) $old['category_id'] === (int) $c['category_id']) ? 'selected' : '' ?>>
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
                            <?= (isset($old['concern_type_id']) && (int) $old['concern_type_id'] === (int) $t['concern_type_id']) ? 'selected' : '' ?>>
                            <?= e($t['type_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-error" data-error-for="concern_type_id"></span>
            </div>
        </div>

        <!-- Barangay + Specific Location -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="barangay_id">Barangay <span class="required">*</span></label>
                <select class="form-select" id="barangay_id" name="barangay_id" required>
                    <option value="">Select a barangay</option>
                    <?php foreach ($barangays as $b): ?>
                        <?php
                            $selected = '';
                            if (isset($old['barangay_id']) && (int) $old['barangay_id'] === (int) $b['barangay_id']) {
                                $selected = 'selected';
                            } elseif (!isset($old['barangay_id']) && $defaultBarangayId === (int) $b['barangay_id']) {
                                $selected = 'selected';
                            }
                        ?>
                        <option value="<?= (int) $b['barangay_id'] ?>" <?= $selected ?>>
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
                       placeholder="e.g. Corner of Rizal St. and Mabini Ave."
                       value="<?= e($old['location'] ?? '') ?>">
                <span class="form-error" data-error-for="location"></span>
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label class="form-label" for="description">Description <span class="required">*</span></label>
            <textarea class="form-textarea" id="description" name="description"
                      rows="5" maxlength="3000" required
                      placeholder="Describe the waste concern, how long it has been there, and any other relevant details."><?= e($old['description'] ?? '') ?></textarea>
            <span class="form-hint">Maximum 3000 characters.</span>
            <span class="form-error" data-error-for="description"></span>
        </div>

        <!-- Photos -->
        <fieldset class="photo-fieldset">
            <legend class="form-label">
                Photo Evidence <span class="required">*</span>
                <span class="form-hint form-hint--inline">Minimum 2 photos required</span>
            </legend>

            <div class="photo-upload">
                <input type="file" id="photos" name="photos[]"
                       accept="image/jpeg,image/png,image/webp"
                       multiple class="photo-input" required>
                <label for="photos" class="photo-dropzone">
                    <span class="photo-dropzone__icon" aria-hidden="true">📷</span>
                    <span class="photo-dropzone__title">Click to choose photos</span>
                    <span class="photo-dropzone__hint">
                        JPG, PNG, or WebP · max 5 MB each · at least 2 photos
                    </span>
                </label>
            </div>

            <span class="form-error" data-error-for="photos"></span>

            <div class="photo-preview" id="photoPreview" aria-live="polite"></div>
        </fieldset>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/dashboard.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                Submit Report
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>