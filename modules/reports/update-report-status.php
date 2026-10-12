<?php
/**
 * actions/reports/update-report-status.php
 * ---------------------------------------------------------------
 * Update a waste report's status (Admin/Staff).
 *
 * Rules:
 *   - Only POST.
 *   - require_role([ROLE_ADMIN, ROLE_STAFF]).
 *   - CSRF required.
 *   - Status transition validated server-side against an allowed
 *     transition map (no arbitrary jumps).
 *   - When moving to Verified / Resolved / Rejected, the report
 *     is locked (is_locked = 1, locked_at, locked_by).
 *   - A row is appended to report_status_history.
 *   - A notification is created for the report owner (deduplicated
 *     by checking for an identical unread notification in the last
 *     60 seconds).
 *
 * Transaction wraps:
 *   1. UPDATE waste_reports
 *   2. INSERT report_status_history
 *   3. INSERT notifications (resident)
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reports/manage-reports.php');
}

require_role([ROLE_ADMIN, ROLE_STAFF]);
verify_csrf();

$userId     = current_user_id();
$reportId   = input_int('report_id');
$newStatus  = input_int('new_status_id');
$remarks    = input_string('remarks');

if ($reportId <= 0 || $newStatus <= 0) {
    flash('error', 'Invalid request.');
    redirect('modules/reports/manage-reports.php');
}

if (mb_strlen($remarks) > 1000) {
    flash('error', 'Remarks must be 1000 characters or fewer.');
    redirect('modules/reports/admin-report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Load current report
// -------------------------------------------------------------
$report = db_one("
    SELECT report_id, user_id, status_id, is_locked
      FROM waste_reports
     WHERE report_id = ?
     LIMIT 1
", [$reportId]);

if (!$report) {
    flash('error', 'Report not found.');
    redirect('modules/reports/manage-reports.php');
}

$currentStatus = (int) $report['status_id'];

// -------------------------------------------------------------
// Enforce allowed transitions
// -------------------------------------------------------------
$transitions = [
    1 => [2, 3, 5],   // Pending      → In Progress, Verified, Rejected
    2 => [3, 5],      // In Progress  → Verified, Rejected
    3 => [4],         // Verified     → Resolved
    4 => [],          // Resolved     → (terminal)
    5 => [],          // Rejected     → (terminal)
];

$allowed = $transitions[$currentStatus] ?? [];
if (!in_array($newStatus, $allowed, true)) {
    flash('error', 'This status transition is not allowed.');
    redirect('modules/reports/admin-report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Determine lock behavior
// -------------------------------------------------------------
$lockStatuses = [3, 4, 5];   // Verified, Resolved, Rejected
$shouldLock   = in_array($newStatus, $lockStatuses, true);

// -------------------------------------------------------------
// Transaction
// -------------------------------------------------------------
try {
    db_transaction(function () use (
        $reportId, $currentStatus, $newStatus, $remarks,
        $userId, $shouldLock, $report
    ) {
        // 1. Update the report row.
        if ($shouldLock) {
            db_execute("
                UPDATE waste_reports
                   SET status_id  = ?,
                       is_locked  = 1,
                       locked_at  = NOW(),
                       locked_by  = ?
                 WHERE report_id = ?
            ", [$newStatus, $userId, $reportId]);
        } else {
            db_execute("
                UPDATE waste_reports
                   SET status_id = ?
                 WHERE report_id = ?
            ", [$newStatus, $reportId]);
        }

        // 2. Append to status history.
        db_execute("
            INSERT INTO report_status_history
                (report_id, old_status_id, new_status_id, changed_by, remarks)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $reportId,
            $currentStatus,
            $newStatus,
            $userId,
            $remarks !== '' ? $remarks : null,
        ]);

        // 3. Notify the report owner.
        $newStatusName = db_scalar(
            "SELECT status_name FROM report_statuses WHERE status_id = ?",
            [$newStatus]
        );

        $message = 'Your report "' . mb_strimwidth(
            (string) db_scalar("SELECT title FROM waste_reports WHERE report_id = ?", [$reportId]),
            0, 80, '…'
        ) . '" has been updated to: ' . $newStatusName . '.';

        if ($remarks !== '') {
            $message .= ' Remarks: ' . mb_strimwidth($remarks, 0, 200, '…');
        }

        // Deduplicate: skip if an identical unread notification was
        // created in the last 60 seconds.
        $recentDup = (int) db_scalar("
            SELECT COUNT(*)
              FROM notifications
             WHERE user_id = ?
               AND is_read = 0
               AND title   = 'Report status updated'
               AND message = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ", [(int) $report['user_id'], $message]);

        if ($recentDup === 0) {
            db_execute("
                INSERT INTO notifications (user_id, title, message, link, channel)
                VALUES (?, 'Report status updated', ?, ?, 'in_app')
            ", [
                (int) $report['user_id'],
                $message,
                '/modules/reports/report-details.php?id=' . $reportId,
            ]);
        }
    });

    flash('success', 'Report status updated successfully.');
    redirect('modules/reports/admin-report-details.php?id=' . $reportId);

} catch (Throwable $e) {
    error_log('[TrashWise update-report-status] ' . $e->getMessage());
    flash('error', 'Unable to update the report status right now. Please try again.');
    redirect('modules/reports/admin-report-details.php?id=' . $reportId);
}