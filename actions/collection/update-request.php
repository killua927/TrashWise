<?php
/**
 * actions/collection/update-request.php
 * ---------------------------------------------------------------
 * Update a collection request's status and/or assigned staff.
 *
 * Rules:
 *   - Only POST.
 *   - require_role([ROLE_ADMIN, ROLE_STAFF]).
 *   - CSRF required.
 *   - Status transitions enforced against a fixed map.
 *   - assigned_to must reference an active Staff user (role_id = 2).
 *   - Assignment frozen after Completed / Cancelled.
 *
 * Transaction wraps:
 *   1. UPDATE collection_requests
 *   2. INSERT notifications (resident) when status changed
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/collection/manage-requests.php');
}

require_role([ROLE_ADMIN, ROLE_STAFF]);
verify_csrf();

$requestId  = input_int('request_id');
$newStatus  = input_int('new_status_id');    // 0 = unchanged
$assignedTo = input_int('assigned_to');      // 0 = unchanged / unassign
$remarks    = input_string('remarks');
$returnTo   = input_string('return_to');

if ($requestId <= 0) {
    flash('error', 'Invalid request reference.');
    redirect('modules/collection/manage-requests.php');
}

if (mb_strlen($remarks) > 500) {
    flash('error', 'Remarks must be 500 characters or fewer.');
    redirect('modules/collection/manage-requests.php');
}

// -------------------------------------------------------------
// Load current request
// -------------------------------------------------------------
$request = db_one("
    SELECT request_id, user_id, status_id, assigned_to, description
      FROM collection_requests
     WHERE request_id = ?
     LIMIT 1
", [$requestId]);

if (!$request) {
    flash('error', 'Collection request not found.');
    redirect('modules/collection/manage-requests.php');
}

$currentStatus = (int) $request['status_id'];

// -------------------------------------------------------------
// Enforce transitions
// -------------------------------------------------------------
$transitions = [
    1 => [2, 4],   // Pending   → Scheduled, Cancelled
    2 => [3, 4],   // Scheduled → Completed, Cancelled
    3 => [],       // Completed → terminal
    4 => [],       // Cancelled → terminal
];

$allowed = $transitions[$currentStatus] ?? [];

if ($newStatus > 0 && !in_array($newStatus, $allowed, true)) {
    flash('error', 'This status transition is not allowed.');
    redirect('modules/collection/manage-requests.php');
}

// -------------------------------------------------------------
// Enforce assignment rules
// -------------------------------------------------------------
$canAssign = ($currentStatus === 1 || $currentStatus === 2);

if (!$canAssign && $assignedTo > 0) {
    flash('error', 'Assignment cannot be changed on a terminal request.');
    redirect('modules/collection/manage-requests.php');
}

// Validate assigned_to is an active Staff user.
if ($assignedTo > 0) {
    $isStaff = db_exists(
        "SELECT 1 FROM users WHERE user_id = ? AND role_id = ? AND is_active = 1",
        [$assignedTo, ROLE_ID_STAFF]
    );
    if (!$isStaff) {
        flash('error', 'The selected assignee is not an active Staff user.');
        redirect('modules/collection/manage-requests.php');
    }
}

// If nothing to change.
if ($newStatus === 0 && $assignedTo === 0) {
    flash('info', 'No changes were submitted.');
    redirect('modules/collection/manage-requests.php');
}

// -------------------------------------------------------------
// Transaction
// -------------------------------------------------------------
$statusChanged = false;
try {
    db_transaction(function () use (
        $requestId, $newStatus, $assignedTo, $currentStatus, $request,
        $remarks, &$statusChanged
    ) {
        $fields = [];
        $params = [];

        if ($newStatus > 0 && $newStatus !== $currentStatus) {
            $fields[] = 'status_id = ?';
            $params[] = $newStatus;
            $statusChanged = true;
        }
        if ($assignedTo >= 0 && $assignedTo !== (int) $request['assigned_to']) {
            $fields[] = 'assigned_to = ?';
            $params[] = $assignedTo > 0 ? $assignedTo : null;
        }

        if (!empty($fields)) {
            $params[] = $requestId;
            db_execute(
                'UPDATE collection_requests SET ' . implode(', ', $fields) . ' WHERE request_id = ?',
                $params
            );
        }

        // Notify the resident if the status changed.
        if ($statusChanged) {
            $statusName = db_scalar(
                "SELECT status_name FROM request_statuses WHERE status_id = ?",
                [$newStatus]
            );

            $msg = 'Your collection request (#' . $requestId . ') is now: ' . $statusName . '.';
            if ($remarks !== '') {
                $msg .= ' Remarks: ' . mb_strimwidth($remarks, 0, 200, '…');
            }

            // Deduplicate near-identical notifications within 60 seconds.
            $dup = (int) db_scalar("
                SELECT COUNT(*)
                  FROM notifications
                 WHERE user_id = ?
                   AND is_read = 0
                   AND title   = 'Collection request updated'
                   AND message = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ", [(int) $request['user_id'], $msg]);

            if ($dup === 0) {
                db_execute("
                    INSERT INTO notifications (user_id, title, message, link, channel)
                    VALUES (?, 'Collection request updated', ?, ?, 'in_app')
                ", [
                    (int) $request['user_id'],
                    $msg,
                    '/modules/collection/my-requests.php',
                ]);
            }
        }
    });

    flash('success', 'Collection request updated successfully.');

} catch (Throwable $e) {
    error_log('[TrashWise update-request] ' . $e->getMessage());
    flash('error', 'Unable to update the collection request right now. Please try again.');
}

// Safe redirect: only allow same-host URLs starting with BASE_URL.
if ($returnTo !== '' && strpos($returnTo, BASE_URL) === 0) {
    header('Location: ' . $returnTo);
    exit;
}
redirect('modules/collection/manage-requests.php');