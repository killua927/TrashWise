<?php
/**
 * actions/guides/save-guide.php
 * ---------------------------------------------------------------
 * Create or update a waste guide (Admin only).
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_ADMIN).
 *   - CSRF required.
 *   - category_id must exist.
 *   - Title: required, ≤ 255 chars.
 *   - Content: required, ≥ 20 chars, ≤ 10,000 chars.
 *   - is_published: optional checkbox.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/guides/manage-guides.php');
}

require_role(ROLE_ADMIN);
verify_csrf();

$userId    = current_user_id();
$guideId   = input_int('guide_id');       // 0 = create
$isEdit    = $guideId > 0;

$categoryId  = input_int('category_id');
$title       = input_string('title');
$content     = input_string('content');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

// Preserve on failure.
$_SESSION['old_edit_guide'] = [
    'category_id'  => $categoryId,
    'title'        => $title,
    'content'      => $content,
    'is_published' => $isPublished,
];

// -------------------------------------------------------------
// Validation
// -------------------------------------------------------------
$errors = [];

if ($categoryId <= 0) {
    $errors[] = 'Please select a waste category.';
} elseif (!db_exists("SELECT 1 FROM waste_categories WHERE category_id = ?", [$categoryId])) {
    $errors[] = 'The selected category is invalid.';
}

if ($title === '' || mb_strlen($title) > 255) {
    $errors[] = 'Title is required (max 255 characters).';
}

if ($content === '' || mb_strlen($content) < 20 || mb_strlen($content) > 10000) {
    $errors[] = 'Content must be between 20 and 10,000 characters.';
}

// Edit mode: verify guide exists.
if ($isEdit && !$errors) {
    if (!db_exists("SELECT 1 FROM waste_guides WHERE guide_id = ?", [$guideId])) {
        $errors[] = 'The guide you are trying to edit does not exist.';
    }
}

if ($errors) {
    flash('error', implode(' ', $errors));
    $url = $isEdit
        ? 'modules/guides/edit-guide.php?id=' . $guideId
        : 'modules/guides/edit-guide.php';
    redirect($url);
}

// -------------------------------------------------------------
// Save
// -------------------------------------------------------------
try {
    if ($isEdit) {
        db_execute("
            UPDATE waste_guides
               SET category_id  = ?,
                   title        = ?,
                   content      = ?,
                   is_published = ?
             WHERE guide_id = ?
        ", [$categoryId, $title, $content, $isPublished, $guideId]);

        flash('success', 'Guide updated successfully.');
    } else {
        db_insert("
            INSERT INTO waste_guides
                (category_id, created_by, title, content, is_published)
            VALUES
                (?, ?, ?, ?, ?)
        ", [$categoryId, $userId, $title, $content, $isPublished]);

        flash('success', 'Guide created successfully.');
    }

    unset($_SESSION['old_edit_guide']);
    redirect('modules/guides/manage-guides.php');

} catch (Throwable $e) {
    error_log('[TrashWise save-guide] ' . $e->getMessage());
    flash('error', 'Unable to save the guide right now. Please try again.');
    $url = $isEdit
        ? 'modules/guides/edit-guide.php?id=' . $guideId
        : 'modules/guides/edit-guide.php';
    redirect($url);
}