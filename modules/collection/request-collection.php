<?php
/**
 * modules/collection/request-collection.php
 * ---------------------------------------------------------------
 * Collection request submission form for residents.
 *
 *   GET  → render form
 *   POST → handled by actions/collection/submit-request.php
 *
 * Guards:
 *   - require_role(ROLE_RESIDENT)
 *
 * Loads:
 *   - Waste categories
 *   - Barangays (defaults to resident's own barangay)
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_RESIDENT);

$categories = get_waste_categories();
$barangays  = get_barangays();

$defaultBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : 0;

// Preserve input on validation failure.
$old = $_SESSION['old_request'] ?? [];
unset($_SESSION['old_request']);

$pageTitle = 'Request Collection';
$pageCss   = ['request-collection.css'];
$pageJs    = ['request-collection.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/collection/my-requests.php">My Requests</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">New Request</span>
    </nav>
    <h1 class="page-title">Request Waste Collection</h1>
    <p class="text-muted">
        Submit a collection request for accumulated waste at your location.
        Staff will review and schedule a pickup.
    </p>
</section>

<section class="container request-form">
    <form action="<?= e(BASE_URL) ?>/actions/collection/submit-request.php"
          method="post" id="collectionRequestForm" novalidate>
        <?= csrf_field() ?>

        <!-- Category -->
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

        <!-- Barangay + Specific location -->
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
                       placeholder="e.g. Blk 3 Lot 5, Mabini Street"
                       value="<?= e($old['location'] ?? '') ?>">
                <span class="form-error" data-error-for="location"></span>
            </div>
        </div>

        <!-- Preferred date -->
        <div class="form-group">
            <label class="form-label" for="preferred_date">Preferred Collection Date (optional)</label>
            <input type="date" class="form-control" id="preferred_date" name="preferred_date"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= e($old['preferred_date'] ?? '') ?>">
            <span class="form-hint">Requests for past dates are not accepted.</span>
            <span class="form-error" data-error-for="preferred_date"></span>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label class="form-label" for="description">Description <span class="required">*</span></label>
            <textarea class="form-textarea" id="description" name="description"
                      rows="5" maxlength="2000" required
                      placeholder="Describe the waste, its approximate quantity, and any access notes for collection staff."><?= e($old['description'] ?? '') ?></textarea>
            <span class="form-hint">Maximum 2000 characters.</span>
            <span class="form-error" data-error-for="description"></span>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/modules/collection/my-requests.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submitRequestBtn">
                Submit Request
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>