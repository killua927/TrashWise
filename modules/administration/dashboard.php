<?php
/**
 * modules/administration/dashboard.php
 * ---------------------------------------------------------------
 * Admin dashboard: real database statistics and recent activity.
 *
 * Guards: require_role([ROLE_ADMIN, ROLE_STAFF])
 *   - Admin sees system-wide statistics.
 *   - Staff (when Staff module exists) will see scoped data.
 *
 * All figures come from live SQL queries. No hardcoded values.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role([ROLE_ADMIN, ROLE_STAFF]);

$isAdmin = is_admin();
$userId  = current_user_id();

// =================================================================
// Report statistics
// =================================================================
$reportTotals = db_one("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) AS verified,
        SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN status_id = 5 THEN 1 ELSE 0 END) AS rejected
      FROM waste_reports
");

$reportsTotal      = (int) ($reportTotals['total']       ?? 0);
$reportsPending    = (int) ($reportTotals['pending']     ?? 0);
$reportsInProgress = (int) ($reportTotals['in_progress'] ?? 0);
$reportsVerified   = (int) ($reportTotals['verified']    ?? 0);
$reportsResolved   = (int) ($reportTotals['resolved']    ?? 0);
$reportsRejected   = (int) ($reportTotals['rejected']    ?? 0);

// =================================================================
// Collection request statistics
// =================================================================
$requestTotals = db_one("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) AS scheduled,
        SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) AS cancelled
      FROM collection_requests
");

$requestsTotal     = (int) ($requestTotals['total']     ?? 0);
$requestsPending   = (int) ($requestTotals['pending']   ?? 0);
$requestsScheduled = (int) ($requestTotals['scheduled'] ?? 0);
$requestsCompleted = (int) ($requestTotals['completed'] ?? 0);
$requestsCancelled = (int) ($requestTotals['cancelled'] ?? 0);

// =================================================================
// User counts by role
// =================================================================
$userCounts = db_all("
    SELECT r.role_name, COUNT(u.user_id) AS total
      FROM roles r
      LEFT JOIN users u ON u.role_id = r.role_id AND u.is_active = 1
     GROUP BY r.role_id, r.role_name
");
$usersByRole = [];
foreach ($userCounts as $row) {
    $usersByRole[$row['role_name']] = (int) $row['total'];
}
$totalResidents = $usersByRole[ROLE_RESIDENT] ?? 0;
$totalStaff     = $usersByRole[ROLE_STAFF]    ?? 0;
$totalAdmins    = $usersByRole[ROLE_ADMIN]    ?? 0;

// =================================================================
// Reference data counts
// =================================================================
$totalBarangays  = (int) db_scalar("SELECT COUNT(*) FROM barangays WHERE is_active = 1");
$totalFacilities = (int) db_scalar("SELECT COUNT(*) FROM disposal_locations WHERE is_active = 1");
$totalGuides     = (int) db_scalar("SELECT COUNT(*) FROM waste_guides WHERE is_published = 1");
$totalAnnounce   = (int) db_scalar("
    SELECT COUNT(*) FROM announcements
     WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
");

// =================================================================
// Recent reports (last 5)
// =================================================================
$recentReports = db_all("
    SELECT wr.report_id, wr.title, wr.submitted_at, wr.is_locked,
           rs.status_name, wc.category_name,
           u.first_name, u.last_name,
           b.barangay_name
      FROM waste_reports wr
      JOIN report_statuses  rs ON rs.status_id   = wr.status_id
      JOIN waste_categories wc ON wc.category_id = wr.category_id
      JOIN users            u  ON u.user_id      = wr.user_id
      JOIN barangays        b  ON b.barangay_id  = wr.barangay_id
     ORDER BY wr.submitted_at DESC
     LIMIT 5
");

// =================================================================
// Recent collection requests (last 5)
// =================================================================
$recentRequests = db_all("
    SELECT cr.request_id, cr.description, cr.preferred_date, cr.submitted_at,
           rst.status_name,
           u.first_name, u.last_name,
           b.barangay_name
      FROM collection_requests cr
      JOIN request_statuses rst ON rst.status_id   = cr.status_id
      JOIN users            u   ON u.user_id       = cr.user_id
      JOIN barangays        b   ON b.barangay_id   = cr.barangay_id
     ORDER BY cr.submitted_at DESC
     LIMIT 5
");

// =================================================================
// Monthly report trend (last 6 months)
// =================================================================
$trendRows = db_all("
    SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS ym,
           COUNT(*) AS total
      FROM waste_reports
     WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym
     ORDER BY ym ASC
");

$trendMap = [];
foreach ($trendRows as $t) {
    $trendMap[$t['ym']] = (int) $t['total'];
}

// Build a complete 6-month series so the chart shows zeros for empty months.
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $trend[] = [
        'month' => date('M Y', strtotime($key . '-01')),
        'total' => $trendMap[$key] ?? 0,
    ];
}

$pageTitle = 'Admin Dashboard';
$pageCss   = ['admin-dashboard.css'];
$pageJs    = ['admin-dashboard.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container admin-dashboard">
    <header class="admin-dashboard__header">
        <div>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <span aria-current="page">Admin Dashboard</span>
            </nav>
            <h1>Welcome, <?= e(current_user_name()) ?></h1>
            <p class="text-muted">
                System-wide overview of waste reports, collection requests, and users.
            </p>
        </div>
        <div class="admin-dashboard__actions">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php">
                Manage Reports
            </a>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php">
                Manage Requests
            </a>
            <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/modules/analytics/analytics.php">
                Analytics
            </a>
        </div>
    </header>

    <!-- Top stat cards -->
    <div class="grid grid-4 admin-dashboard__stats">
        <article class="stat-card">
            <span class="stat-card__label">Total Reports</span>
            <span class="stat-card__value"><?= (int) $reportsTotal ?></span>
            <span class="stat-card__hint">
                <?= (int) $reportsPending ?> pending
            </span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">In Progress</span>
            <span class="stat-card__value"><?= (int) $reportsInProgress ?></span>
            <span class="stat-card__hint">
                <?= (int) $reportsVerified ?> verified
            </span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Resolved</span>
            <span class="stat-card__value"><?= (int) $reportsResolved ?></span>
            <span class="stat-card__hint">
                <?= (int) $reportsRejected ?> rejected
            </span>
        </article>
        <article class="stat-card">
            <span class="stat-card__label">Collection Requests</span>
            <span class="stat-card__value"><?= (int) $requestsTotal ?></span>
            <span class="stat-card__hint">
                <?= (int) $requestsPending ?> pending
            </span>
        </article>
    </div>

    <!-- Secondary counts -->
    <div class="grid grid-4 admin-dashboard__stats">
        <article class="stat-card stat-card--muted">
            <span class="stat-card__label">Residents</span>
            <span class="stat-card__value"><?= (int) $totalResidents ?></span>
        </article>
        <article class="stat-card stat-card--muted">
            <span class="stat-card__label">Staff</span>
            <span class="stat-card__value"><?= (int) $totalStaff ?></span>
        </article>
        <article class="stat-card stat-card--muted">
            <span class="stat-card__label">Barangays</span>
            <span class="stat-card__value"><?= (int) $totalBarangays ?></span>
        </article>
        <article class="stat-card stat-card--muted">
            <span class="stat-card__label">Facilities</span>
            <span class="stat-card__value"><?= (int) $totalFacilities ?></span>
        </article>
    </div>

    <!-- Chart + quick reference counts -->
    <div class="grid grid-2 admin-dashboard__charts">
        <section class="card chart-card">
            <header class="panel__header">
                <h2 class="panel__title">Monthly Report Trend</h2>
                <span class="panel__hint">Last 6 months</span>
            </header>
            <div class="chart-container">
                <canvas id="trendChart"
                        aria-label="Monthly report trend chart"
                        role="img"></canvas>
            </div>
        </section>

        <section class="card chart-card">
            <header class="panel__header">
                <h2 class="panel__title">Reports by Status</h2>
            </header>
            <div class="chart-container">
                <canvas id="statusChart"
                        aria-label="Reports by status chart"
                        role="img"></canvas>
            </div>
        </section>
    </div>

    <!-- Recent reports -->
    <section class="card">
        <header class="panel__header">
            <h2 class="panel__title">Recent Reports</h2>
            <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php">View all</a>
        </header>

        <?php if (empty($recentReports)): ?>
            <div class="empty-state empty-state--compact">
                <p>No reports have been submitted yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Resident</th>
                            <th>Barangay</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReports as $r): ?>
                            <tr>
                                <td>#<?= (int) $r['report_id'] ?></td>
                                <td>
                                    <a href="<?= e(BASE_URL) ?>/modules/reports/admin-report-details.php?id=<?= (int) $r['report_id'] ?>">
                                        <?= e(mb_strimwidth($r['title'], 0, 60, '…')) ?>
                                    </a>
                                </td>
                                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= e($r['barangay_name']) ?></td>
                                <td><?= e($r['category_name']) ?></td>
                                <td>
                                    <span class="<?= e(report_status_class($r['status_name'])) ?>">
                                        <?= e($r['status_name']) ?>
                                    </span>
                                </td>
                                <td><?= e(time_ago($r['submitted_at'])) ?></td>
                                <td>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?= e(BASE_URL) ?>/modules/reports/admin-report-details.php?id=<?= (int) $r['report_id'] ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recent collection requests -->
    <section class="card">
        <header class="panel__header">
            <h2 class="panel__title">Recent Collection Requests</h2>
            <a class="panel__link" href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php">View all</a>
        </header>

        <?php if (empty($recentRequests)): ?>
            <div class="empty-state empty-state--compact">
                <p>No collection requests have been submitted yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Resident</th>
                            <th>Barangay</th>
                            <th>Preferred Date</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $r): ?>
                            <tr>
                                <td>#<?= (int) $r['request_id'] ?></td>
                                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= e($r['barangay_name']) ?></td>
                                <td><?= e(format_date($r['preferred_date'])) ?></td>
                                <td>
                                    <span class="<?= e(request_status_class($r['status_name'])) ?>">
                                        <?= e($r['status_name']) ?>
                                    </span>
                                </td>
                                <td><?= e(time_ago($r['submitted_at'])) ?></td>
                                <td>
                                    <a class="btn btn-outline btn-sm"
                                       href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php?id=<?= (int) $r['request_id'] ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<!-- Chart data for JS -->
<script>
    window.TRASHWISE_DASHBOARD = {
        trend:  <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>,
        status: {
            pending:     <?= (int) $reportsPending ?>,
            in_progress: <?= (int) $reportsInProgress ?>,
            verified:    <?= (int) $reportsVerified ?>,
            resolved:    <?= (int) $reportsResolved ?>,
            rejected:    <?= (int) $reportsRejected ?>
        }
    };
</script>

<!-- Chart.js (only loaded on this page) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>