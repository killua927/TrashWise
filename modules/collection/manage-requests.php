<?php
/**
 * modules/collection/manage-requests.php
 * ---------------------------------------------------------------
 * Admin and Staff list of all collection requests.
 *
 * Guards:
 *   - require_role([ROLE_ADMIN, ROLE_STAFF])
 *
 * Filters:
 *   - q            free text (resident name, location, description)
 *   - status_id    request status
 *   - category_id  waste category
 *   - barangay_id  barangay
 *   - assigned_to  staff user
 *   - page         pagination
 *
 * Actions:
 *   - Update status/assignment → actions/collection/update-request.php
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
$q          = input_string('q', 'GET');
$statusId   = input_int('status_id', 'GET');
$categoryId = input_int('category_id', 'GET');
$barangayId = input_int('barangay_id', 'GET');
$assignedTo = input_int('assigned_to', 'GET');
$page       = max(1, input_int('page', 'GET', 1));
$perPage    = 15;

// -------------------------------------------------------------
// Build WHERE
// -------------------------------------------------------------
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(cr.description LIKE ? OR cr.location LIKE ?
                 OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($statusId > 0)   { $where[] = 'cr.status_id = ?';   $params[] = $statusId; }
if ($categoryId > 0) { $where[] = 'cr.category_id = ?'; $params[] = $categoryId; }
if ($barangayId > 0) { $where[] = 'cr.barangay_id = ?'; $params[] = $barangayId; }
if ($assignedTo > 0) { $where[] = 'cr.assigned_to = ?'; $params[] = $assignedTo; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// -------------------------------------------------------------
// Count + paginate
// -------------------------------------------------------------
$total = (int) db_scalar("
    SELECT COUNT(*)
      FROM collection_requests cr
      JOIN users u ON u.user_id = cr.user_id
    {$whereSql}
", $params);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page
// -------------------------------------------------------------
$requests = db_all("
    SELECT cr.request_id, cr.description, cr.location, cr.preferred_date,
           cr.submitted_at, cr.updated_at, cr.assigned_to,
           rs.status_id, rs.status_name,
           wc.category_name, wc.color_code,
           b.barangay_name,
           u.first_name AS resident_first, u.last_name AS resident_last,
           u.email AS resident_email, u.phone AS resident_phone,
           s.first_name AS staff_first, s.last_name AS staff_last
      FROM collection_requests cr
      JOIN request_statuses  rs ON rs.status_id   = cr.status_id
      JOIN waste_categories  wc ON wc.category_id = cr.category_id
      JOIN barangays         b  ON b.barangay_id  = cr.barangay_id
      JOIN users             u  ON u.user_id      = cr.user_id
      LEFT JOIN users        s  ON s.user_id      = cr.assigned_to
     {$whereSql}
     ORDER BY cr.submitted_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

// -------------------------------------------------------------
// Reference data
// -------------------------------------------------------------
$categories   = get_waste_categories();
$statuses     = get_request_statuses();
$barangays    = get_barangays();

// Staff users for assignment dropdown.
$staffUsers = db_all("
    SELECT user_id, first_name, last_name
      FROM users
     WHERE role_id = ? AND is_active = 1
     ORDER BY first_name, last_name
", [ROLE_ID_STAFF]);

$pageTitle = 'Manage Collection Requests';
$pageCss   = ['manage-requests.css'];
$pageJs    = ['manage-requests.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// URL helper preserving filters.
function admin_requests_url(array $overrides = []): string
{
    $base = [
        'q'           => input_string('q', 'GET'),
        'status_id'   => input_int('status_id', 'GET'),
        'category_id' => input_int('category_id', 'GET'),
        'barangay_id' => input_int('barangay_id', 'GET'),
        'assigned_to' => input_int('assigned_to', 'GET'),
        'page'        => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/collection/manage-requests.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}

// Allowed transitions per current status_id.
function allowed_transitions(int $currentId): array
{
    switch ($currentId) {
        case 1: return [2 => 'Scheduled', 4 => 'Cancelled'];
        case 2: return [3 => 'Completed', 4 => 'Cancelled'];
        default: return [];
    }
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Collection Requests</span>
    </nav>

    <div class="page-header__row">
        <div>
            <h1 class="page-title">Manage Collection Requests</h1>
            <p class="text-muted">
                <strong><?= (int) $total ?></strong>
                request<?= $total === 1 ? '' : 's' ?> match the current filters.
            </p>
        </div>
    </div>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by resident, location, or description"
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
            <div class="filters__field">
                <label class="sr-only" for="assigned_to">Assigned Staff</label>
                <select class="form-select" id="assigned_to" name="assigned_to">
                    <option value="">Any Staff</option>
                    <?php foreach ($staffUsers as $s): ?>
                        <option value="<?= (int) $s['user_id'] ?>"
                            <?= $assignedTo === (int) $s['user_id'] ? 'selected' : '' ?>>
                            <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q !== '' || $statusId > 0 || $categoryId > 0 || $barangayId > 0 || $assignedTo > 0): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <h3>No collection requests found</h3>
            <?php if ($q !== '' || $statusId > 0 || $categoryId > 0 || $barangayId > 0 || $assignedTo > 0): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php">Clear Filters</a>
            <?php else: ?>
                <p>No collection requests have been submitted yet.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-request-list">
            <?php foreach ($requests as $r): ?>
                <?php
                    $currentStatus = (int) $r['status_id'];
                    $transitions   = allowed_transitions($currentStatus);
                    $staffName     = trim(($r['staff_first'] ?? '') . ' ' . ($r['staff_last'] ?? ''));
                    $canAssign     = ($currentStatus === 1 || $currentStatus === 2);
                ?>
                <article class="admin-request-card"
                         data-request-id="<?= (int) $r['request_id'] ?>">
                    <header class="admin-request-card__header">
                        <div class="admin-request-card__title-group">
                            <span class="admin-request-card__id">#<?= (int) $r['request_id'] ?></span>
                            <span class="admin-request-card__category"
                                  style="border-left-color: <?= e($r['color_code'] ?: '#6BA24B') ?>;">
                                <?= e($r['category_name']) ?>
                            </span>
                            <span class="<?= e(request_status_class($r['status_name'])) ?>">
                                <?= e($r['status_name']) ?>
                            </span>
                        </div>
                        <button type="button"
                                class="btn btn-outline btn-sm"
                                data-toggle-admin-details="<?= (int) $r['request_id'] ?>"
                                aria-expanded="false"
                                aria-controls="admin-request-details-<?= (int) $r['request_id'] ?>">
                            Details
                        </button>
                    </header>

                    <div class="admin-request-card__body">
                        <p class="admin-request-card__description">
                            <?= e(mb_strimwidth($r['description'], 0, 220, '…')) ?>
                        </p>

                        <div class="admin-request-card__meta">
                            <span class="admin-request-card__meta-item">
                                👤 <?= e($r['resident_first'] . ' ' . $r['resident_last']) ?>
                            </span>
                            <span class="admin-request-card__meta-item">
                                📍 <?= e($r['barangay_name']) ?> — <?= e($r['location']) ?>
                            </span>
                            <?php if (!empty($r['preferred_date'])): ?>
                                <span class="admin-request-card__meta-item">
                                    📅 Preferred: <?= e(format_date($r['preferred_date'])) ?>
                                </span>
                            <?php endif; ?>
                            <span class="admin-request-card__meta-item">
                                🧑‍🔧 <?= e($staffName !== '' ? $staffName : 'Unassigned') ?>
                            </span>
                            <span class="admin-request-card__meta-item admin-request-card__meta-item--muted">
                                Submitted <?= e(time_ago($r['submitted_at'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Inline action bar -->
                    <?php if (!empty($transitions) || $canAssign): ?>
                        <form method="post"
                              action="<?= e(BASE_URL) ?>/actions/collection/update-request.php"
                              class="admin-request-card__actions inline-form"
                              data-update-request-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                            <input type="hidden" name="return_to"
                                   value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">

                            <?php if (!empty($transitions)): ?>
                                <div class="action-field">
                                    <label class="action-field__label" for="new_status_<?= (int) $r['request_id'] ?>">
                                        New Status
                                    </label>
                                    <select class="form-select form-select--sm"
                                            id="new_status_<?= (int) $r['request_id'] ?>"
                                            name="new_status_id">
                                        <option value="">— unchanged —</option>
                                        <?php foreach ($transitions as $sid => $label): ?>
                                            <option value="<?= (int) $sid ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($canAssign): ?>
                                <div class="action-field">
                                    <label class="action-field__label" for="assigned_to_<?= (int) $r['request_id'] ?>">
                                        Assign to
                                    </label>
                                    <select class="form-select form-select--sm"
                                            id="assigned_to_<?= (int) $r['request_id'] ?>"
                                            name="assigned_to">
                                        <option value="0">— Unassigned —</option>
                                        <?php foreach ($staffUsers as $s): ?>
                                            <option value="<?= (int) $s['user_id'] ?>"
                                                <?= ((int) $r['assigned_to'] === (int) $s['user_id']) ? 'selected' : '' ?>>
                                                <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="action-field action-field--grow">
                                <label class="action-field__label" for="remarks_<?= (int) $r['request_id'] ?>">
                                    Remarks (optional)
                                </label>
                                <input type="text" class="form-control form-control--sm"
                                       id="remarks_<?= (int) $r['request_id'] ?>"
                                       name="remarks" maxlength="500"
                                       placeholder="Short note to the resident">
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm">
                                Save
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="admin-request-card__terminal">
                            This request is <strong><?= e($r['status_name']) ?></strong> and cannot be changed further.
                        </p>
                    <?php endif; ?>

                    <!-- Expanded detail -->
                    <div class="admin-request-card__details hidden"
                         id="admin-request-details-<?= (int) $r['request_id'] ?>">
                        <dl class="details-list">
                            <div class="details-list__row">
                                <dt>Resident</dt>
                                <dd><?= e($r['resident_first'] . ' ' . $r['resident_last']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Email</dt>
                                <dd><?= e($r['resident_email']) ?></dd>
                            </div>
                            <?php if (!empty($r['resident_phone'])): ?>
                                <div class="details-list__row">
                                    <dt>Phone</dt>
                                    <dd><?= e($r['resident_phone']) ?></dd>
                                </div>
                            <?php endif; ?>
                            <div class="details-list__row">
                                <dt>Barangay</dt>
                                <dd><?= e($r['barangay_name']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Location</dt>
                                <dd><?= e($r['location']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Category</dt>
                                <dd><?= e($r['category_name']) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Preferred Date</dt>
                                <dd><?= e(format_date($r['preferred_date'])) ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Assigned Staff</dt>
                                <dd><?= e($staffName !== '' ? $staffName : 'Unassigned') ?></dd>
                            </div>
                            <div class="details-list__row">
                                <dt>Submitted</dt>
                                <dd><?= e(format_datetime($r['submitted_at'])) ?></dd>
                            </div>
                            <?php if ($r['updated_at'] && $r['updated_at'] !== $r['submitted_at']): ?>
                                <div class="details-list__row">
                                    <dt>Last Updated</dt>
                                    <dd><?= e(format_datetime($r['updated_at'])) ?></dd>
                                </div>
                            <?php endif; ?>
                            <div class="details-list__row details-list__row--full">
                                <dt>Full Description</dt>
                                <dd><?= nl2br(e($r['description'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Request pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(admin_requests_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(admin_requests_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(admin_requests_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(admin_requests_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(admin_requests_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>