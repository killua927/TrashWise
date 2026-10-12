<?php
/**
 * modules/reports/my-reports.php
 * ---------------------------------------------------------------
 * List of the resident's own waste reports with filters,
 * search, and pagination.
 *
 * Guards: require_role(ROLE_RESIDENT)
 *
 * All records are scoped to the logged-in user via user_id.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_RESIDENT);

$userId = current_user_id();

// -------------------------------------------------------------
// Filters
// -------------------------------------------------------------
$q          = input_string('q', 'GET');
$categoryId = input_int('category_id', 'GET');
$statusId   = input_int('status_id', 'GET');
$page       = max(1, input_int('page', 'GET', 1));
$perPage    = 10;

// -------------------------------------------------------------
// Build WHERE clause
// -------------------------------------------------------------
$where  = ['wr.user_id = ?'];
$params = [$userId];

if ($q !== '') {
    $where[] = '(wr.title LIKE ? OR wr.location LIKE ? OR wr.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'wr.category_id = ?';
    $params[] = $categoryId;
}
if ($statusId > 0) {
    $where[] = 'wr.status_id = ?';
    $params[] = $statusId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------------
// Count total for pagination
// -------------------------------------------------------------
$total = (int) db_scalar("
    SELECT COUNT(*) FROM waste_reports wr {$whereSql}
", $params);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page of reports
// -------------------------------------------------------------
$sql = "
    SELECT wr.report_id, wr.title, wr.location, wr.submitted_at,
           wr.is_locked,
           rs.status_id, rs.status_name,
           wc.category_name, wc.color_code,
           ct.type_name,
           (SELECT COUNT(*) FROM report_photos rp WHERE rp.report_id = wr.report_id) AS photo_count
      FROM waste_reports wr
      JOIN report_statuses  rs ON rs.status_id      = wr.status_id
      JOIN waste_categories wc ON wc.category_id    = wr.category_id
      JOIN concern_types    ct ON ct.concern_type_id = wr.concern_type_id
     {$whereSql}
     ORDER BY wr.submitted_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
";
$reports = db_all($sql, $params);

// -------------------------------------------------------------
// Reference data for filter dropdowns
// -------------------------------------------------------------
$categories = get_waste_categories();
$statuses   = get_report_statuses();

$pageTitle = 'My Reports';
$pageCss   = ['my-reports.css'];
$pageJs    = ['my-reports.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// Helper: build a URL that keeps current filters.
function reports_url(array $overrides = []): string
{
    $base = [
        'q'           => input_string('q', 'GET'),
        'category_id' => input_int('category_id', 'GET'),
        'status_id'   => input_int('status_id', 'GET'),
        'page'        => input_int('page', 'GET', 1),
    ];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/reports/my-reports.php' . ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">My Reports</span>
    </nav>
    <div class="page-header__row">
        <div>
            <h1 class="page-title">My Waste Reports</h1>
            <p class="text-muted">
                You have <strong><?= (int) $total ?></strong>
                report<?= $total === 1 ? '' : 's' ?> in total.
            </p>
        </div>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/reports/report-waste.php">
            + New Report
        </a>
    </div>
</section>

<section class="container">
    <form method="get" action="<?= e(BASE_URL) ?>/modules/reports/my-reports.php" class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by title, location, or description"
                       value="<?= e($q) ?>">
            </div>
            <div class="filters__field">
                <label class="sr-only" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"
                            <?= $categoryId === (int) $c['category_id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters__field">
                <label class="sr-only" for="status_id">Status</label>
                <select class="form-select" id="status_id" name="status_id">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= (int) $s['status_id'] ?>"
                            <?= $statusId === (int) $s['status_id'] ? 'selected' : '' ?>>
                            <?= e($s['status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q !== '' || $categoryId > 0 || $statusId > 0): ?>
                <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <h3>No reports found</h3>
            <?php if ($q !== '' || $categoryId > 0 || $statusId > 0): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline" href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">Clear Filters</a>
            <?php else: ?>
                <p>You have not submitted any waste reports yet.</p>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/reports/report-waste.php">Submit Your First Report</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="report-list" role="list">
            <?php foreach ($reports as $r): ?>
                <?php
                    $detailUrl = BASE_URL . '/modules/reports/report-details.php?id=' . (int) $r['report_id'];
                    $editUrl   = BASE_URL . '/modules/reports/edit-report.php?id='   . (int) $r['report_id'];
                    $canEdit   = (int) $r['is_locked'] === 0;
                ?>
                <li class="report-card" data-report-id="<?= (int) $r['report_id'] ?>">
                    <div class="report-card__header">
                        <span class="report-card__category"
                              style="border-left-color: <?= e($r['color_code'] ?: '#6BA24B') ?>;">
                            <?= e($r['category_name']) ?>
                        </span>
                        <span class="<?= e(report_status_class($r['status_name'])) ?>">
                            <?= e($r['status_name']) ?>
                        </span>
                    </div>

                    <h3 class="report-card__title">
                        <a href="<?= e($detailUrl) ?>"><?= e($r['title']) ?></a>
                    </h3>

                    <div class="report-card__meta">
                        <span class="report-card__meta-item">
                            <span aria-hidden="true">📍</span>
                            <?= e($r['location']) ?>
                        </span>
                        <span class="report-card__meta-item">
                            <span aria-hidden="true">🏷</span>
                            <?= e($r['type_name']) ?>
                        </span>
                        <span class="report-card__meta-item">
                            <span aria-hidden="true">📷</span>
                            <?= (int) $r['photo_count'] ?>
                            photo<?= $r['photo_count'] == 1 ? '' : 's' ?>
                        </span>
                        <span class="report-card__meta-item report-card__meta-item--muted">
                            Submitted <?= e(time_ago($r['submitted_at'])) ?>
                        </span>
                    </div>

                    <div class="report-card__actions">
                        <a class="btn btn-outline btn-sm" href="<?= e($detailUrl) ?>">View</a>

                        <?php if ($canEdit): ?>
                            <a class="btn btn-secondary btn-sm" href="<?= e($editUrl) ?>">Edit</a>
                            <button type="button"
                                    class="btn btn-danger btn-sm"
                                    data-delete-report="<?= (int) $r['report_id'] ?>"
                                    data-delete-title="<?= e($r['title']) ?>">
                                Delete
                            </button>
                        <?php else: ?>
                            <span class="report-card__locked" title="Verified reports cannot be edited.">
                                🔒 Locked
                            </span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Report pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(reports_url(['page' => $pg['current_page'] - 1])) ?>" aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(reports_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(reports_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(reports_url(['page' => $pg['total_pages']])) ?>"><?= (int) $pg['total_pages'] ?></a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(reports_url(['page' => $pg['current_page'] + 1])) ?>" aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- Hidden delete form (submitted by JS) -->
<form id="deleteReportForm"
      method="post"
      action="<?= e(BASE_URL) ?>/actions/reports/delete-report.php"
      class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="report_id" id="deleteReportId" value="">
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>