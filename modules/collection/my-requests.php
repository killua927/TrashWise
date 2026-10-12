<?php
/**
 * modules/collection/my-requests.php
 * ---------------------------------------------------------------
 * List of the resident's own collection requests with filters,
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
$where  = ['cr.user_id = ?'];
$params = [$userId];

if ($q !== '') {
    $where[] = '(cr.description LIKE ? OR cr.location LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'cr.category_id = ?';
    $params[] = $categoryId;
}
if ($statusId > 0) {
    $where[] = 'cr.status_id = ?';
    $params[] = $statusId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------------
// Count total for pagination
// -------------------------------------------------------------
$total = (int) db_scalar("
    SELECT COUNT(*) FROM collection_requests cr {$whereSql}
", $params);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page of requests
// -------------------------------------------------------------
$requests = db_all("
    SELECT cr.request_id, cr.description, cr.location, cr.preferred_date,
           cr.submitted_at, cr.updated_at,
           rs.status_id, rs.status_name,
           wc.category_name, wc.color_code,
           b.barangay_name,
           u.first_name AS staff_first, u.last_name AS staff_last
      FROM collection_requests cr
      JOIN request_statuses rst ON rst.status_id   = cr.status_id
      JOIN waste_categories wc  ON wc.category_id  = cr.category_id
      JOIN barangays        b   ON b.barangay_id   = cr.barangay_id
      LEFT JOIN users       u   ON u.user_id       = cr.assigned_to
     {$whereSql}
     ORDER BY cr.submitted_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

// -------------------------------------------------------------
// Reference data for filter dropdowns
// -------------------------------------------------------------
$categories = get_waste_categories();
$statuses   = get_request_statuses();

$pageTitle = 'My Collection Requests';
$pageCss   = ['my-requests.css'];
$pageJs    = ['my-requests.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// Helper: build a URL that preserves current filters.
function requests_url(array $overrides = []): string
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
    return BASE_URL . '/modules/collection/my-requests.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">My Collection Requests</span>
    </nav>
    <div class="page-header__row">
        <div>
            <h1 class="page-title">My Collection Requests</h1>
            <p class="text-muted">
                You have <strong><?= (int) $total ?></strong>
                request<?= $total === 1 ? '' : 's' ?> in total.
            </p>
        </div>
        <a class="btn btn-primary"
           href="<?= e(BASE_URL) ?>/modules/collection/request-collection.php">
            + New Request
        </a>
    </div>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/collection/my-requests.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by description or location"
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
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/collection/my-requests.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <h3>No collection requests found</h3>
            <?php if ($q !== '' || $categoryId > 0 || $statusId > 0): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/collection/my-requests.php">Clear Filters</a>
            <?php else: ?>
                <p>You have not submitted any collection requests yet.</p>
                <a class="btn btn-primary"
                   href="<?= e(BASE_URL) ?>/modules/collection/request-collection.php">
                    Request a Collection
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="request-list" role="list">
            <?php foreach ($requests as $r): ?>
                <?php
                    $canCancel = ((int) $r['status_id'] === 1); // Pending only
                    $staffName = trim(($r['staff_first'] ?? '') . ' ' . ($r['staff_last'] ?? ''));
                ?>
                <li class="request-card" data-request-id="<?= (int) $r['request_id'] ?>">
                    <div class="request-card__header">
                        <span class="request-card__category"
                              style="border-left-color: <?= e($r['color_code'] ?: '#6BA24B') ?>;">
                            <?= e($r['category_name']) ?>
                        </span>
                        <span class="<?= e(request_status_class($r['status_name'])) ?>">
                            <?= e($r['status_name']) ?>
                        </span>
                    </div>

                    <p class="request-card__description">
                        <?= e(mb_strimwidth($r['description'], 0, 220, '…')) ?>
                    </p>

                    <div class="request-card__meta">
                        <span class="request-card__meta-item">
                            <span aria-hidden="true">📍</span>
                            <?= e($r['location']) ?>
                        </span>
                        <span class="request-card__meta-item">
                            <span aria-hidden="true">🏘</span>
                            <?= e($r['barangay_name']) ?>
                        </span>
                        <?php if (!empty($r['preferred_date'])): ?>
                            <span class="request-card__meta-item">
                                <span aria-hidden="true">📅</span>
                                Preferred: <?= e(format_date($r['preferred_date'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($staffName !== ''): ?>
                            <span class="request-card__meta-item">
                                <span aria-hidden="true">👤</span>
                                Assigned to <?= e($staffName) ?>
                            </span>
                        <?php endif; ?>
                        <span class="request-card__meta-item request-card__meta-item--muted">
                            Submitted <?= e(time_ago($r['submitted_at'])) ?>
                        </span>
                    </div>

                    <div class="request-card__actions">
                        <button type="button"
                                class="btn btn-outline btn-sm"
                                data-toggle-details="<?= (int) $r['request_id'] ?>"
                                aria-expanded="false"
                                aria-controls="request-details-<?= (int) $r['request_id'] ?>">
                            Details
                        </button>

                        <?php if ($canCancel): ?>
                            <button type="button"
                                    class="btn btn-danger btn-sm"
                                    data-cancel-request="<?= (int) $r['request_id'] ?>">
                                Cancel
                            </button>
                        <?php else: ?>
                            <span class="request-card__locked" title="Only pending requests can be cancelled.">
                                🔒 Not cancellable
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="request-card__details hidden"
                         id="request-details-<?= (int) $r['request_id'] ?>">
                        <dl class="details-list">
                            <div class="details-list__row">
                                <dt>Request ID</dt>
                                <dd>#<?= (int) $r['request_id'] ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Submitted on</dt>
                                <dd><?= e(format_datetime($r['submitted_at'])) ?></dd>
                            </div>
                            <?php if ($r['updated_at'] && $r['updated_at'] !== $r['submitted_at']): ?>
                                <div class="details-list__row">
                                    <dt>Last updated</dt>
                                    <dd><?= e(format_datetime($r['updated_at'])) ?></dd>
                                </div>
                            <?php endif; ?>
                            <div class="details-list__row">
                                <dt>Barangay</dt>
                                <dd><?= e($r['barangay_name']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Specific location</dt>
                                <dd><?= e($r['location']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Category</dt>
                                <dd><?= e($r['category_name']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Status</dt>
                                <dd>
                                    <span class="<?= e(request_status_class($r['status_name'])) ?>">
                                        <?= e($r['status_name']) ?>
                                    </span>
                                </dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Preferred date</dt>
                                <dd><?= e(format_date($r['preferred_date'])) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Assigned to</dt>
                                <dd><?= e($staffName !== '' ? $staffName : '—') ?></dd>
                            </div>
                            <div class="details-list__row details-list__row--full">
                                <dt>Full description</dt>
                                <dd><?= nl2br(e($r['description'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Request pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(requests_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(requests_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(requests_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(requests_url(['page' => $pg['total_pages']])) ?>"><?= (int) $pg['total_pages'] ?></a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(requests_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- Hidden cancel form -->
<form id="cancelRequestForm"
      method="post"
      action="<?= e(BASE_URL) ?>/actions/collection/cancel-request.php"
      class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="request_id" id="cancelRequestId" value="">
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>