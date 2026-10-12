<?php
/**
 * actions/notifications/mark-read.php
 * ---------------------------------------------------------------
 * Mark notifications as read.
 *
 * Modes:
 *   - Single: notification_id
 *   - All:    mark_all = 1
 *
 * Rules:
 *   - Only POST.
 *   - require_login().
 *   - CSRF required.
 *   - Only rows belonging to the current user are updated.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/notifications/notifications.php');
}

require_login();
verify_csrf();

$userId = current_user_id();

// -------------------------------------------------------------
// Mode: mark all as read
// -------------------------------------------------------------
if (input_int('mark_all') === 1) {
    db_execute(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
        [$userId]
    );
    flash('success', 'All notifications have been marked as read.');
    redirect('modules/notifications/notifications.php');
}

// -------------------------------------------------------------
// Mode: mark a single notification as read
// -------------------------------------------------------------
$notificationId = input_int('notification_id');

if ($notificationId <= 0) {
    flash('error', 'Invalid notification reference.');
    redirect('modules/notifications/notifications.php');
}

// Ownership check via SQL (never trust the ID alone).
$affected = db_execute(
    "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?",
    [$notificationId, $userId]
);

if ($affected > 0) {
    flash('success', 'Notification marked as read.');
} else {
    // Either it does not exist, belongs to another user, or was already read.
    flash('info', 'Notification is already read or was not found.');
}

redirect('modules/notifications/notifications.php');