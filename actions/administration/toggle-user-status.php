<?php
/**
 * actions/administration/toggle-user-status.php
 * ---------------------------------------------------------------
 * Toggle a user account's active status.
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_ADMIN).
 *   - CSRF required.
 *   - Admin cannot deactivate their own account.
 *   - User must exist.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/administration/manage-users.php');
}

require_role(ROLE_ADMIN);
verify_csrf();

$targetId    = input_int('user_id');
$currentId   = current_user_id();

if ($targetId <= 0) {
    flash('error', 'Invalid user reference.');
    redirect('modules/administration/manage-users.php');
}

if ($targetId === $currentId) {
    flash('error', 'You cannot change your own account status.');
    redirect('modules/administration/manage-users.php');
}

// -------------------------------------------------------------
// Verify user exists
// -------------------------------------------------------------
$user = db_one("
    SELECT user_id, first_name, last_name, is_active
      FROM users
     WHERE user_id = ?
     LIMIT 1
", [$targetId]);

if (!$user) {
    flash('error', 'User not found.');
    redirect('modules/administration/manage-users.php');
}

$newState = ((int) $user['is_active'] === 1) ? 0 : 1;

try {
    db_execute(
        "UPDATE users SET is_active = ? WHERE user_id = ?",
        [$newState, $targetId]
    );

    // If deactivating, remove any active sessions.
    if ($newState === 0) {
        db_execute("DELETE FROM user_sessions WHERE user_id = ?", [$targetId]);
    }

    // In-system notification.
    $title = $newState === 1 ? 'Account activated' : 'Account deactivated';
    $msg   = $newState === 1
        ? 'Your TrashWise account has been reactivated by an administrator.'
        : 'Your TrashWise account has been deactivated by an administrator. '
          . 'Contact the administrator if you believe this is a mistake.';

    db_execute("
        INSERT INTO notifications (user_id, title, message, link, channel)
        VALUES (?, ?, ?, NULL, 'in_app')
    ", [$targetId, $title, $msg]);

    flash('success', 'User ' . ($newState === 1 ? 'activated' : 'deactivated') . ' successfully.');
    redirect('modules/administration/manage-users.php');

} catch (Throwable $e) {
    error_log('[TrashWise toggle-user-status] ' . $e->getMessage());
    flash('error', 'Unable to update the user status right now. Please try again.');
    redirect('modules/administration/manage-users.php');
}