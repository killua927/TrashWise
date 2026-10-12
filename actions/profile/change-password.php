<?php
/**
 * actions/profile/change-password.php
 * ---------------------------------------------------------------
 * Change the current user's password.
 *
 * Rules:
 *   - Only POST.
 *   - require_login().
 *   - CSRF required.
 *   - Current password must match (password_verify).
 *   - New password must be ≥ 8 characters.
 *   - Confirm password must match new password.
 *   - Password is hashed with password_hash(PASSWORD_DEFAULT).
 *   - Session ID is regenerated after a successful change
 *     (defense-in-depth against fixation).
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

require_login();
verify_csrf();

$userId = current_user_id();

$current = (string) ($_POST['current_password'] ?? '');
$new     = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

$_SESSION['old_password'] = [];

$errors = [];

if ($current === '') {
    $errors[] = 'Current password is required.';
}
if (strlen($new) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}
if ($new !== $confirm) {
    $errors[] = 'New passwords do not match.';
}

// Fetch the stored hash.
$row = db_one("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1", [$userId]);
if (!$row) {
    logout_user(true);
}

if (!$errors && !password_verify($current, $row['password_hash'])) {
    $errors[] = 'Your current password is incorrect.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('profile.php');
}

// -------------------------------------------------------------
// Update the password and regenerate the session ID
// -------------------------------------------------------------
try {
    $newHash = password_hash($new, PASSWORD_DEFAULT);

    db_execute(
        "UPDATE users SET password_hash = ? WHERE user_id = ?",
        [$newHash, $userId]
    );

    // Refresh the DB session row identifier to match the new session ID.
    session_regenerate_id(true);
    $newSid = session_id();

    try {
        db_execute("
            INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_activity)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                user_id       = VALUES(user_id),
                ip_address    = VALUES(ip_address),
                user_agent    = VALUES(user_agent),
                last_activity = NOW()
        ", [
            $newSid,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
        $_SESSION['db_session_id'] = $newSid;
    } catch (Throwable $e) {
        // Non-fatal — the important part (password change) already succeeded.
        error_log('[TrashWise change-password session] ' . $e->getMessage());
    }

    flash('success', 'Your password has been updated successfully.');
    redirect('profile.php');

} catch (Throwable $e) {
    error_log('[TrashWise change-password] ' . $e->getMessage());
    flash('error', 'Unable to update your password right now. Please try again.');
    redirect('profile.php');
}