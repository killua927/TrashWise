<?php
/**
 * actions/guides/delete-guide.php
 * ---------------------------------------------------------------
 * Delete a waste guide (Admin only).
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_ADMIN).
 *   - CSRF required.
 *   - Guide must exist.
 *   - Transactional delete.
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

try {
    db_transaction(function () use ($guideId) {
        $exists = db_exists("SELECT 1 FROM waste_guides WHERE guide_id = ?", [$guideId]);
        if (!$exists) {
            throw new RuntimeException('Guide not found.');
        }
        db_execute("DELETE FROM waste_guides WHERE guide_id = ?", [$guideId]);
    });

    flash('success', 'Guide deleted successfully.');
} catch (Throwable $e) {
    error_log('[TrashWise delete-guide] ' . $e->getMessage());
    flash('error', 'Unable to delete the guide right now. Please try again.');
}

redirect('modules/guides/manage-guides.php');