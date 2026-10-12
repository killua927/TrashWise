<?php
/**
 * actions/guides/toggle-guide.php
 * ---------------------------------------------------------------
 * Toggle a guide's published state (Admin only).
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_ADMIN).
 *   - CSRF required.
 *   - Guide must exist.
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

$guideId = input_int('guide_id');
if ($guideId <= 0) {
    flash('error', 'Invalid guide reference.');
    redirect('modules/guides/manage-guides.php');
}

$guide = db_one("SELECT guide_id, title, is_published FROM waste_guides WHERE guide_id = ? LIMIT 1", [$guideId]);
if (!$guide) {
    flash('error', 'Guide not found.');
    redirect('modules/guides/manage-guides.php');
}

$newState = ((int) $guide['is_published'] === 1) ? 0 : 1;

try {
    db_execute(
        "UPDATE waste_guides SET is_published = ? WHERE guide_id = ?",
        [$newState, $guideId]
    );

    flash('success', 'Guide ' . ($newState === 1 ? 'published' : 'unpublished') . ' successfully.');
} catch (Throwable $e) {
    error_log('[TrashWise toggle-guide] ' . $e->getMessage());
    flash('error', 'Unable to update the guide right now. Please try again.');
}

redirect('modules/guides/manage-guides.php');