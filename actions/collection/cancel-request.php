<?php
/**
 * actions/collection/cancel-request.php
 * ---------------------------------------------------------------
 * Cancel a collection request.
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_RESIDENT).
 *   - Ownership check: request.user_id === current_user_id.
 *   - Status must be Pending (status_id = 1).
 *     Scheduled/Completed/Cancelled requests cannot be cancelled.
 *
 * On success:
 *   - UPDATE status to Cancelled
 *   - Notify admins
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/collection/my-requests.php');
}

require_role(ROLE_RESIDENT);
verify_csrf();

$userId    = current_user_id();
$requestId = input_int('request_id');

if ($requestId <= 0) {
    flash('error', 'Invalid request reference.');
    redirect('modules/collection/my-requests.php');
}

// -------------------------------------------------------------
// Fetch and validate ownership + status
// -------------------------------------------------------------
$request = db_one("
    SELECT request_id, user_id, status_id
      FROM collection_requests
     WHERE request_id = ?
     LIMIT 1
", [$requestId]);

if (!$request) {
    flash('error', 'Collection request not found.');
    redirect('modules/collection/my-requests.php');
}

if ((int) $request['user_id'] !== $userId) {
    http_response_code(403);
    exit('Forbidden.');
}

if ((int) $request['status_id'] !== 1) {
    flash('error', 'Only pending requests can be cancelled.');
    redirect('modules/collection/my-requests.php');
}

// -------------------------------------------------------------
// Transaction: update + notify admins
// -------------------------------------------------------------
try {
    db_transaction(function () use ($requestId) {
        db_execute("
            UPDATE collection_requests
               SET status_id = 4
             WHERE request_id = ?
        ", [$requestId]);

        db_execute("
            INSERT INTO notifications (user_id, title, message, link, channel)
            SELECT user_id,
                   'Collection request cancelled',
                   CONCAT('Collection request #', ?, ' was cancelled by the resident.'),
                   ?,
                   'in_app'
              FROM users
             WHERE role_id = ?
        ", [
            $requestId,
            '/modules/collection/manage-requests.php',
            ROLE_ID_ADMIN,
        ]);
    });

    flash('success', 'Your collection request has been cancelled.');
    redirect('modules/collection/my-requests.php');

} catch (Throwable $e) {
    error_log('[TrashWise cancel-request] ' . $e->getMessage());
    flash('error', 'Unable to cancel the request right now. Please try again.');
    redirect('modules/collection/my-requests.php');
}