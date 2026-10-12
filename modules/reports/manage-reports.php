<?php
/**
 * modules/reports/manage-reports.php
 * ---------------------------------------------------------------
 * Admin and Staff view of all waste reports.
 *
 * Guards:
 *   - require_role([ROLE_ADMIN, ROLE_STAFF])
 *
 * Filters:
 *   - q               free text (title, location, resident name)
 *   - status_id       report status
 *   - category_id     waste category
 *   - concern_type_id concern type
 *   - barangay_id     barangay
 *   - page            pagination
 *
 * Row actions:
 *   - View → modules/reports/admin-report-details.php?id=...
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role([ROLE_ADMIN, ROLE_STAFF]);

// -------------------------------------------------------------
// Filters
// -------------------------------------------------------------
$q             = input_string('q', 'GET');
$statusId      = input_int('status_id', 'GET');
$categoryId    = input_int('category_id', 'GET');
$concernTypeId = input_int('concern_type_id', 'GET');
$barangayId    = input_int('barangay_id', 'GET');
$page          = max(1, input_int('page', 'GET', 1));
$perPage       = 15;

// -------------------------------------------------------------
// Build WHERE
// -------------------------------------------------------------
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(wr.title LIKE ? OR wr.location LIKE ?
                 OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($statusId > 0)      { $where[] = 'wr.status_id = ?';        $params[] = $statusId; }
if ($categoryId > 0)    { $where[] = 'wr.category_id = ?';      $params[] = $categoryId; }
if ($concernTypeId > 0) { $where[] = 'wr.concern_type_id = ?';  $params[] = $concernTypeId; }
if ($barangayId > 0)    { $where[] = 'wr.barangay_id = ?';      $params[] = $barangayId; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// -------------------------------------------------------------
// Count + paginate
// -------------------------------------------------------------
$total = (int) db_scalar("
    SELECT COUNT(*)
      FROM waste_reports wr
      JOIN users u ON u.user_id = wr.user_id
    {$whereSql}
", $params);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page of reports
// -------------------------------------------------------------
$reports = db_all("
    SELECT wr.report_id, wr.title, wr.location, wr.submitted_at, wr.is_locked,
           rs.status_id, rs.status_name,
           wc.category_name, wc.color_code,
           ct.type_name,
           b.barangay_name,
           u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM report_photos rp WHERE rp.report_id = wr.report_id) AS photo_count
      FROM waste_reports wr
      JOIN report_statuses  rs ON rs.status_id       = wr.status_id
      JOIN waste_categories wc ON wc.category_id     = wr.category_id
      JOIN concern_types    ct ON ct.concern_type_id = wr.concern_type_id
      JOIN barangays        b  ON b.barangay_id      = wr.barangay_id
      JOIN users            u  ON u.user_id          = wr.user_id
     {$whereSql}
     ORDER BY wr.submitted_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

$categories   = get_waste_categories();
$concernTypes = get_concern_types();
$statuses     = get_report_statuses();
$barangays    = get_barangays();

$pageTitle = 'Manage Reports';
$pageCss   = ['manage-reports.css'];
$pageJs    = ['manage-reports.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// Preserve filters when building pagination links.
function manage_reports_url(array $overrides = []): string
{
    $base = [
        'q'               => input_string('q', 'GET'),
        'status_id'       => input_int('status_id', 'GET'),
        'category_id'     => input_int('category_id', 'GET'),
        'concern_type_id' => input_int('concern_type_id', 'GET'),
        'barangay_id'     => input_int('barangay_id', 'GET'),
        'page'            => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/reports/manage-reports.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Manage Reports</span>
    </nav>

    <div class="page-header__row">
        <div>
            <h1 class="page-title">Manage Waste Reports</h1>
            <p class="text-muted">
                <strong><?= (int) $total ?></strong>
                report<?= $total === 1 ? '' : 's' ?> match the current filters.
            </p>
        </div>
    </div>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by title, location, or resident name"
                       value="<?= e($q) ?>">
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
                <label class="sr-only" for="concern_type_id">Concern</label>
                <select class="form-select" id="concern_type_id" name="concern_type_id">
                    <option value="">All Concerns</option>
                    <?php foreach ($concernTypes as $t): ?>
                        <option value="<?= (int) $t['concern_type_id'] ?>"
                            <?= $concernTypeId === (int) $t['concern_type_id'] ? 'selected' : '' ?>>
                            <?= e($t['type_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters__field">
                <label class="sr-only" for="barangay_id">Barangay</label>
                <select class="form-select" id="barangay_id" name="barangay_id">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= (int) $b['barangay_id'] ?>"
                            <?= $barangayId === (int) $b['barangay_id'] ? 'selected' : '' ?>>
                            <?= e($b['barangay_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q !== '' || $statusId > 0 || $categoryId > 0 || $concernTypeId > 0 || $barangayId > 0): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($reports)): ?>
        <div class="empty-state">
            <h3>No reports found</h3>
            <?php if ($q !== '' || $statusId > 0 || $categoryId > 0 || $concernTypeId > 0 || $barangayId > 0): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php">Clear Filters</a>
            <?php else: ?>
                <p>No waste reports have been submitted yet.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table manage-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Resident</th>
                        <th>Barangay</th>
                        <th>Category</th>
                        <th>Concern</th>
                        <th>Status</th>
                        <th class="text-center">Photos</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr class="<?= (int) $r['is_locked'] === 1 ? 'is-locked' : '' ?>">
                            <td>#<?= (int) $r['report_id'] ?></td>
                            <td>
                                <a class="cell-title"
                                   href="<?= e(BASE_URL) ?>/modules/reports/admin-report-details.php?id=<?= (int) $r['report_id'] ?>">
                                    <?= e(mb_strimwidth($r['title'], 0, 55, '…')) ?>
                                </a>
                                <span class="cell-sub"><?= e(mb_strimwidth($r['location'], 0, 55, '…')) ?></span>
                            </td>
                            <td>
                                <span class="cell-name"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></span>
                                <span class="cell-sub"><?= e($r['email']) ?></span>
                            </td>
                            <td><?= e($r['barangay_name']) ?></td>
                            <td>
                                <span class="cell-category"
                                      style="border-left-color: <?= e($r['color_code'] ?: '#6BA24B') ?>;">
                                    <?= e($r['category_name']) ?>
                                </span>
                            </td>
                            <td><?= e($r['type_name']) ?></td>
                            <td>
                                <span class="<?= e(report_status_class($r['status_name'])) ?>">
                                    <?= e($r['status_name']) ?>
                                </span>
                            </td>
                            <td class="text-center"><?= (int) $r['photo_count'] ?></td>
                            <td><?= e(time_ago($r['submitted_at'])) ?></td>
                            <td class="text-right">
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

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Report pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(manage_reports_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(manage_reports_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(manage_reports_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(manage_reports_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(manage_reports_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>