<?php
/**
 * dashboard.php
 * ---------------------------------------------------------------
 * Role dispatcher for the main dashboard URL.
 *
 *   - Resident:  renders the resident dashboard inline.
 *   - Staff:     redirects to modules/staff/dashboard.php
 *                (falls back to admin dashboard until Staff module exists).
 *   - Admin:     redirects to modules/administration/dashboard.php
 *
 * Resident logic is preserved exactly as delivered previously.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$userId = current_user_id();

// -----------------------------------------------------------------
// Route non-residents to their proper dashboards.
// -----------------------------------------------------------------
if (is_admin()) {
    redirect('modules/administration/dashboard.php');
}

if (is_staff()) {
    // Staff module not yet implemented — route to admin dashboard for now,
    // which is scoped so Staff can only see permitted data.
    redirect('modules/administration/dashboard.php');
}

// -----------------------------------------------------------------
// Resident data (unchanged from previous delivery)
// -----------------------------------------------------------------

$reportCounts = [];
$rows = db_all("
    SELECT rs.status_name, COUNT(*) AS total
      FROM waste_reports wr
      JOIN report_statuses rs ON rs.status_id = wr.status_id
     WHERE wr.user_id = ?
     GROUP BY rs.status_id, rs.status_name
", [$userId]);
foreach ($rows as $r) {
    $reportCounts[$r['status_name']] = (int) $r['total'];
}

$totalReports      = array_sum($reportCounts);
$pendingReports    = $reportCounts[REPORT_STATUS_PENDING]     ?? 0;
$inProgressReports = $reportCounts[REPORT_STATUS_IN_PROGRESS] ?? 0;
$verifiedReports   = $reportCounts[REPORT_STATUS_VERIFIED]    ?? 0;
$resolvedReports   = $reportCounts[REPORT_STATUS_RESOLVED]    ?? 0;
$rejectedReports   = $reportCounts[REPORT_STATUS_REJECTED]    ?? 0;

$requestCounts = [];
$rows = db_all("
    SELECT rst.status_name, COUNT(*) AS total
      FROM collection_requests cr
      JOIN request_statuses rst ON rst.status_id = cr.status_id
     WHERE cr.user_id = ?
     GROUP BY rst.status_id, rst.status_name
", [$userId]);
foreach ($rows as $r) {
    $requestCounts[$r['status_name']] = (int) $r['total'];
}

$totalRequests     = array_sum($requestCounts);
$pendingRequests   = $requestCounts[REQUEST_STATUS_PENDING]   ?? 0;
$scheduledRequests = $requestCounts[REQUEST_STATUS_SCHEDULED] ?? 0;
$completedRequests = $requestCounts[REQUEST_STATUS_COMPLETED] ?? 0;
$cancelledRequests = $requestCounts[REQUEST_STATUS_CANCELLED] ?? 0;

$unreadNotifications = (int) db_scalar(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [$userId]
);

$recentReports = db_all("
    SELECT wr.report_id, wr.title, wr.submitted_at, wr.is_locked,
           rs.status_name, wc.category_name
      FROM waste_reports wr
      JOIN report_statuses  rs ON rs.status_id   = wr.status_id
      JOIN waste_categories wc ON wc.category_id = wr.category_id
     WHERE wr.user_id = ?
     ORDER BY wr.submitted_at DESC
     LIMIT 5
", [$userId]);

$recentRequests = db_all("
    SELECT cr.request_id, cr.description, cr.preferred_date, cr.submitted_at,
           rst.status_name
      FROM collection_requests cr
      JOIN request_statuses rst ON rst.status_id = cr.status_id
     WHERE cr.user_id = ?
     ORDER BY cr.submitted_at DESC
     LIMIT 5
", [$userId]);

$recentNotifications = db_all("
    SELECT notification_id, title, message, link, is_read, created_at
      FROM notifications
     WHERE user_id = ?
     ORDER BY is_read ASC, created_at DESC
     LIMIT 5
", [$userId]);

$residentBarangayId = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : 0;
$recentAnnouncements = db_all("
    SELECT announcement_id, title, content, published_at
      FROM announcements
     WHERE is_active = 1
       AND (expires_at IS NULL OR expires_at > NOW())
       AND (barangay_id IS NULL OR barangay_id = ?)
     ORDER BY published_at DESC
     LIMIT 3
", [$residentBarangayId]);

$pageTitle = 'Dashboard';
$pageCss   = ['dashboard.css'];
$pageJs    = ['dashboard.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/includes/header.php';
?>

<section class="container dashboard">
    <header class="dashboard__greeting">
        <div>
            <h1>Hello, <?= e(current_user_name()) ?></h1>
            <p class="text-muted">Here is a summary of your waste reports and collection requests.</p>
        </div>
        <div class="dashboard__actions">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/reports/report-waste.php">
                + Report Waste
            </a>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/modules/collection/request-collection.php">
                Request Collection
            </a>
        </div>
    </header>

    <div class="grid grid-4 dashboard__stats">
        <article class="stat-card">
            <span class="stat-card__label">Total Reports</span>
            <span class="stat-card__value"><?= (int) $totalReports ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Pending</span>
            <span class="stat-card__value"><?= (int) $pendingReports ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">In Progress</span>
            <span class="stat-card__value"><?= (int) $inProgressReports ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Verified / Resolved</span>
            <span class="stat-card__value"><?= (int) ($verifiedReports + $resolvedReports) ?></span>
        </article>
    </div>

    <div class="grid grid-4 dashboard__stats">
        <article class="stat-card">
            <span class="stat-card__label">Total Requests</span>
            <span class="stat-card__value"><?= (int) $totalRequests ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Pending Requests</span>
            <span class="stat-card__value"><?= (int) $pendingRequests ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Scheduled</span>
            <span class="stat-card__value"><?= (int) $scheduledRequests ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Unread Notifications</span>
            <span class="stat-card__value"><?= (int) $unreadNotifications ?></span>
        </article>
    </div>

    <div class="grid grid-2 dashboard__panels">
        <section class="card">
            <header class="panel__header">
                <h2 class="panel__title">Recent Reports</h2>
                <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">View all</a>
            </header>

            <?php if (empty($recentReports)): ?>
                <div class="empty-state empty-state--compact">
                    <p>You have not submitted any waste reports yet.</p>
                    <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/modules/reports/report-waste.php">Submit your first report</a>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentReports as $r): ?>
                        <li class="activity-list__item">
                            <div class="activity-list__main">
                                <a class="activity-list__title"
                                   href="<?= e(BASE_URL) ?>/modules/reports/report-details.php?id=<?= (int) $r['report_id'] ?>">
                                    <?= e($r['title']) ?>
                                </a>
                                <div class="activity-list__meta">
                                    <span class="<?= e(report_status_class($r['status_name'])) ?>"><?= e($r['status_name']) ?></span>
                                    <span class="activity-list__category"><?= e($r['category_name']) ?></span>
                                    <span class="activity-list__date"><?= e(time_ago($r['submitted_at'])) ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <header class="panel__header">
                <h2 class="panel__title">Recent Collection Requests</h2>
                <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/collection/my-requests.php">View all</a>
            </header>

            <?php if (empty($recentRequests)): ?>
                <div class="empty-state empty-state--compact">
                    <p>You have not submitted any collection requests yet.</p>
                    <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/modules/collection/request-collection.php">Request a collection</a>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentRequests as $r): ?>
                        <li class="activity-list__item">
                            <div class="activity-list__main">
                                <span class="activity-list__title"><?= e(mb_strimwidth($r['description'], 0, 70, '…')) ?></span>
                                <div class="activity-list__meta">
                                    <span class="<?= e(request_status_class($r['status_name'])) ?>"><?= e($r['status_name']) ?></span>
                                    <?php if (!empty($r['preferred_date'])): ?>
                                        <span class="activity-list__date">Preferred: <?= e(format_date($r['preferred_date'])) ?></span>
                                    <?php endif; ?>
                                    <span class="activity-list__date"><?= e(time_ago($r['submitted_at'])) ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="grid grid-2 dashboard__panels">
        <section class="card">
            <header class="panel__header">
                <h2 class="panel__title">Notifications</h2>
                <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/notifications/notifications.php">View all</a>
            </header>

            <?php if (empty($recentNotifications)): ?>
                <div class="empty-state empty-state--compact">
                    <p>No notifications yet.</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentNotifications as $n): ?>
                        <li class="activity-list__item <?= (int) $n['is_read'] === 0 ? 'is-unread' : '' ?>">
                            <div class="activity-list__main">
                                <?php if (!empty($n['link'])): ?>
                                    <a class="activity-list__title"
                                       href="<?= e(BASE_URL . $n['link']) ?>">
                                        <?= e($n['title']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="activity-list__title"><?= e($n['title']) ?></span>
                                <?php endif; ?>
                                <p class="activity-list__text"><?= e(mb_strimwidth($n['message'], 0, 110, '…')) ?></p>
                                <span class="activity-list__date"><?= e(time_ago($n['created_at'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <header class="panel__header">
                <h2 class="panel__title">Announcements</h2>
                <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/announcements/announcements.php">View all</a>
            </header>

            <?php if (empty($recentAnnouncements)): ?>
                <div class="empty-state empty-state--compact">
                    <p>No announcements at the moment.</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentAnnouncements as $a): ?>
                        <li class="activity-list__item">
                            <div class="activity-list__main">
                                <span class="activity-list__title"><?= e($a['title']) ?></span>
                                <p class="activity-list__text"><?= e(mb_strimwidth($a['content'], 0, 110, '…')) ?></p>
                                <span class="activity-list__date"><?= e(time_ago($a['published_at'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>