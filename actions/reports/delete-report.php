<?php
/**
 * actions/reports/delete-report.php
 * ---------------------------------------------------------------
 * Delete a waste report.
 *
 * Rules:
 *   - Only POST.
 *   - Only the owner (resident) may delete their own report.
 *   - Locked (verified/resolved/rejected) reports cannot be deleted.
 *   - Admin/Staff may not delete via this endpoint — they use an
 *     administrative flow when needed.
 *
 * Files associated with the report are removed from disk after a
 * successful DB delete.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reports/my-reports.php');
}

require_role(ROLE_RESIDENT);
verify_csrf();

$reportId = input_int('report_id');
if ($reportId <= 0) {
    flash('error', 'Invalid report reference.');
    redirect('modules/reports/my-reports.php');
}

$userId = current_user_id();

// -------------------------------------------------------------
// Fetch the report and verify ownership + lock state
// -------------------------------------------------------------
$report = db_one("
    SELECT report_id, user_id, is_locked, title
      FROM waste_reports
     WHERE report_id = ?
     LIMIT 1
", [$reportId]);

if (!$report) {
    flash('error', 'Report not found.');
    redirect('modules/reports/my-reports.php');
}

if ((int) $report['user_id'] !== $userId) {
    // Do not reveal whether the report exists to other residents.
    http_response_code(403);
    exit('Forbidden.');
}

if ((int) $report['is_locked'] === 1) {
    flash('error', 'This report is locked and cannot be deleted.');
    redirect('modules/reports/report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Collect photo file paths BEFORE delete
// -------------------------------------------------------------
$photoPaths = db_all("
    SELECT file_path FROM report_photos WHERE report_id = ?
", [$reportId]);

// -------------------------------------------------------------
// Delete inside a transaction.
// ON DELETE CASCADE removes report_photos and report_status_history.
// -------------------------------------------------------------
try {
    db_transaction(function () use ($reportId) {
        db_execute("DELETE FROM waste_reports WHERE report_id = ?", [$reportId]);
    });
} catch (Throwable $e) {
    error_log('[TrashWise delete-report] ' . $e->getMessage());
    flash('error', 'Unable to delete the report. Please try again.');
    redirect('modules/reports/report-details.php?id=' . $reportId);
}

// -------------------------------------------------------------
// Remove photo files from disk (best-effort)
// -------------------------------------------------------------
foreach ($photoPaths as $p) {
    $rel = ltrim($p['file_path'], '/');
    // Normalize separators for Windows.
    $abs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    // Safety: ensure the path stays inside the upload directory.
    $uploadsRoot = realpath(UPLOAD_DIR);
    $targetReal  = realpath($abs);

    if ($targetReal && $uploadsRoot && strpos($targetReal, $uploadsRoot) === 0 && is_file($targetReal)) {
        @unlink($targetReal);
    }
}

// -------------------------------------------------------------
// Optionally remove the now-empty report folder.
// -------------------------------------------------------------
$reportDir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $reportId;
if (is_dir($reportDir)) {
    $items = @scandir($reportDir);
    if (is_array($items) && count(array_diff($items, ['.', '..'])) === 0) {
        @rmdir($reportDir);
    }
}

flash('success', 'Report deleted successfully.');
redirect('modules/reports/my-reports.php');