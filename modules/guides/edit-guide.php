<?php
/**
 * modules/guides/edit-guide.php
 * ---------------------------------------------------------------
 * Create or edit a waste guide.
 *
 * Guards:
 *   - require_role(ROLE_ADMIN)
 *
 * Modes:
 *   - Create: no ?id
 *   - Edit:   ?id={id}
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_ADMIN);

$editId = input_int('id', 'GET');
$isEdit = $editId > 0;

$guide = null;
if ($isEdit) {
    $guide = db_one("
        SELECT guide_id, category_id, created_by, title, content,
               is_published, created_at, updated_at
          FROM waste_guides
         WHERE guide_id = ?
         LIMIT 1
    ", [$editId]);

    if (!$guide) {
        flash('error', 'Guide not found.');
        redirect('modules/guides/manage-guides.php');
    }
}

$categories = get_waste_categories();

// Preserve input on validation failure.
$old = $_SESSION['old_edit_guide'] ?? [];
unset($_SESSION['old_edit_guide']);

$val = function (string $key, $default = '') use ($old, $guide) {
    if (array_key_exists($key, $old)) return $old[$key];
    if ($guide !== null && isset($guide[$key])) return $guide[$key];
    return $default;
};

$pageTitle = $isEdit ? 'Edit Guide' : 'New Guide';
$pageCss   = ['edit-guide.css'];
$pageJs    = ['edit-guide.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php">Waste Guides</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page"><?= $isEdit ? 'Edit #' . (int) $editId : 'New Guide' ?></span>
    </nav>
    <h1 class="page-title">
        <?= $isEdit ? 'Edit Guide' : 'Create Waste Guide' ?>
    </h1>
    <p class="text-muted">
        Provide clear, actionable waste management guidance. Residents see only published guides.
    </p>
</section>

<section class="container edit-guide">
    <form action="<?= e(BASE_URL) ?>/actions/guides/save-guide.php"
          method="post" id="editGuideForm" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="guide_id" value="<?= (int) $editId ?>">
        <?php endif; ?>

        <!-- Category -->
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

        <!-- Title -->
        <div class="form-group">
            <label class="form-label" for="title">Title <span class="required">*</span></label>
            <input type="text" class="form-control" id="title" name="title"
                   maxlength="255" required
                   placeholder="e.g. How to Handle Biodegradable Waste"
                   value="<?= e($val('title')) ?>">
            <span class="form-error" data-error-for="title"></span>
        </div>

        <!-- Content -->
        <div class="form-group">
            <label class="form-label" for="content">Content <span class="required">*</span></label>
            <textarea class="form-textarea" id="content" name="content"
                      rows="14" maxlength="10000" required
                      placeholder="Describe the proper handling of this type of waste."><?= e($val('content')) ?></textarea>
            <span class="form-hint">Maximum 10,000 characters. Line breaks are preserved.</span>
            <span class="form-error" data-error-for="content"></span>
        </div>

        <!-- Publish state -->
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_published" value="1"
                       <?= ((int) $val('is_published', 1)) === 1 ? 'checked' : '' ?>>
                <span>Publish immediately (residents can see this guide)</span>
            </label>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php"
               class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" id="saveGuideBtn">
                <?= $isEdit ? 'Save Changes' : 'Create Guide' ?>
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>