<?php
/**
 * modules/administration/manage-users.php
 * ---------------------------------------------------------------
 * Admin user management list.
 *
 * Guards:
 *   - require_role(ROLE_ADMIN)
 *
 * Filters:
 *   - q           free text (name, email)
 *   - role_id     role
 *   - barangay_id barangay
 *   - status      active | inactive | all
 *   - page        pagination
 *
 * Actions:
 *   - Edit user → edit-user.php?id=...
 *   - Toggle active → toggle-user-status.php (POST)
 *   - New user → edit-user.php
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_ADMIN);

// -------------------------------------------------------------
// Filters
// -------------------------------------------------------------
$q          = input_string('q', 'GET');
$roleId     = input_int('role_id', 'GET');
$barangayId = input_int('barangay_id', 'GET');
$status     = input_string('status', 'GET');
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}
$page    = max(1, input_int('page', 'GET', 1));
$perPage = 15;

// -------------------------------------------------------------
// Build WHERE
// -------------------------------------------------------------
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ?
                 OR u.email LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($roleId > 0)     { $where[] = 'u.role_id = ?';     $params[] = $roleId; }
if ($barangayId > 0) { $where[] = 'u.barangay_id = ?'; $params[] = $barangayId; }
if ($status === 'active')   { $where[] = 'u.is_active = 1'; }
if ($status === 'inactive') { $where[] = 'u.is_active = 0'; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// -------------------------------------------------------------
// Count + paginate
// -------------------------------------------------------------
$total = (int) db_scalar("SELECT COUNT(*) FROM users u {$whereSql}", $params);
$pg    = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch users
// -------------------------------------------------------------
$users = db_all("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
           u.is_active, u.is_email_verified, u.created_at, u.last_login_at,
           r.role_id, r.role_name,
           b.barangay_name
      FROM users u
      JOIN roles r ON r.role_id = u.role_id
      LEFT JOIN barangays b ON b.barangay_id = u.barangay_id
     {$whereSql}
     ORDER BY u.created_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

$roles     = db_all("SELECT role_id, role_name FROM roles ORDER BY role_id");
$barangays = get_barangays();

$pageTitle = 'Manage Users';
$pageCss   = ['manage-users.css'];
$pageJs    = ['manage-users.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// URL helper preserving filters.
function users_url(array $overrides = []): string
{
    $base = [
        'q'           => input_string('q', 'GET'),
        'role_id'     => input_int('role_id', 'GET'),
        'barangay_id' => input_int('barangay_id', 'GET'),
        'status'      => input_string('status', 'GET'),
        'page'        => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/administration/manage-users.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Users</span>
    </nav>

    <div class="page-header__row">
        <div>
            <h1 class="page-title">Manage Users</h1>
            <p class="text-muted">
                <strong><?= (int) $total ?></strong>
                user<?= $total === 1 ? '' : 's' ?> match the current filters.
            </p>
        </div>
        <a class="btn btn-primary"
           href="<?= e(BASE_URL) ?>/modules/administration/edit-user.php">
            + New User
        </a>
    </div>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/administration/manage-users.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by name or email"
                       value="<?= e($q) ?>">
            </div>
            <div class="filters__field">
                <label class="sr-only" for="role_id">Role</label>
                <select class="form-select" id="role_id" name="role_id">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['role_id'] ?>"
                            <?= $roleId === (int) $r['role_id'] ? 'selected' : '' ?>>
                            <?= e($r['role_name']) ?>
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
                <label class="sr-only" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all"      <?= $status === 'all'      ? 'selected' : '' ?>>All Status</option>
                    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q !== '' || $roleId > 0 || $barangayId > 0 || $status !== 'all'): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/administration/manage-users.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <h3>No users found</h3>
            <?php if ($q !== '' || $roleId > 0 || $barangayId > 0 || $status !== 'all'): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/administration/manage-users.php">Clear Filters</a>
            <?php else: ?>
                <p>No users have been registered yet.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table manage-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Barangay</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="<?= (int) $u['is_active'] === 0 ? 'is-inactive' : '' ?>">
                            <td>#<?= (int) $u['user_id'] ?></td>
                            <td>
                                <span class="cell-name"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
                                <?php if (!empty($u['phone'])): ?>
                                    <span class="cell-sub"><?= e($u['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="role-pill role-pill--<?= e(strtolower($u['role_name'])) ?>">
                                    <?= e($u['role_name']) ?>
                                </span>
                            </td>
                            <td><?= e($u['barangay_name'] ?? '—') ?></td>
                            <td>
                                <?php if ((int) $u['is_active'] === 1): ?>
                                    <span class="status-badge status-verified">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-rejected">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(format_date($u['created_at'])) ?></td>
                            <td class="text-right">
                                <a class="btn btn-outline btn-sm"
                                   href="<?= e(BASE_URL) ?>/modules/administration/edit-user.php?id=<?= (int) $u['user_id'] ?>">
                                    Edit
                                </a>
                                <?php if ((int) $u['user_id'] !== (int) current_user_id()): ?>
                                    <form method="post"
                                          action="<?= e(BASE_URL) ?>/actions/administration/toggle-user-status.php"
                                          class="inline-form"
                                          data-toggle-user-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-danger' : 'btn-secondary' ?>"
                                                data-toggle-user-button
                                                data-user-name="<?= e($u['first_name'] . ' ' . $u['last_name']) ?>"
                                                data-action="<?= (int) $u['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                            <?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="cell-hint">(you)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="User pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(users_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(users_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(users_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(users_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(users_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>