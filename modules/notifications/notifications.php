<?php
/**
 * modules/notifications/notifications.php
 * ---------------------------------------------------------------
 * Notification center for authenticated users.
 *
 * Access:
 *   - require_login() (Resident, Staff, Admin).
 *
 * Scope:
 *   - Only notifications where user_id = current_user_id.
 *
 * Filters:
 *   - status: all | unread | read
 *   - page:   pagination
 *
 * Actions:
 *   - Mark a single notification as read.
 *   - Mark all notifications as read.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$userId = current_user_id();

// -------------------------------------------------------------
// Filters
// -------------------------------------------------------------
$statusFilter = input_string('status', 'GET');   // all | unread | read
if (!in_array($statusFilter, ['all', 'unread', 'read'], true)) {
    $statusFilter = 'all';
}
$page    = max(1, input_int('page', 'GET', 1));
$perPage = 15;

// -------------------------------------------------------------
// Build WHERE
// -------------------------------------------------------------
$where  = ['user_id = ?'];
$params = [$userId];

if ($statusFilter === 'unread') {
    $where[] = 'is_read = 0';
} elseif ($statusFilter === 'read') {
    $where[] = 'is_read = 1';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------------
// Counts + pagination
// -------------------------------------------------------------
$total       = (int) db_scalar("SELECT COUNT(*) FROM notifications {$whereSql}", $params);
$unreadTotal = (int) db_scalar("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page of notifications
// -------------------------------------------------------------
$notifications = db_all("
    SELECT notification_id, title, message, link, channel, is_read, created_at
      FROM notifications
     {$whereSql}
     ORDER BY is_read ASC, created_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

$pageTitle = 'Notifications';
$pageCss   = ['notifications.css'];
$pageJs    = ['notifications.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// URL builder preserving filters.
function notif_url(array $overrides = []): string
{
    $base = [
        'status' => input_string('status', 'GET'),
        'page'   => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/notifications/notifications.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Notifications</span>
    </nav>
    <div class="page-header__row">
        <div>
            <h1 class="page-title">Notifications</h1>
            <p class="text-muted">
                You have <strong><?= (int) $unreadTotal ?></strong>
                unread notification<?= $unreadTotal === 1 ? '' : 's' ?>.
            </p>
        </div>
        <?php if ($unreadTotal > 0): ?>
            <form method="post"
                  action="<?= e(BASE_URL) ?>/actions/notifications/mark-read.php"
                  class="inline-form"
                  id="markAllForm">
                <?= csrf_field() ?>
                <input type="hidden" name="mark_all" value="1">
                <button type="submit" class="btn btn-outline">
                    Mark all as read
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="container">
    <nav class="tabs" aria-label="Notification filters">
        <a class="tabs__item <?= $statusFilter === 'all'    ? 'is-active' : '' ?>"
           href="<?= e(notif_url(['status' => 'all',    'page' => 1])) ?>">All</a>
        <a class="tabs__item <?= $statusFilter === 'unread' ? 'is-active' : '' ?>"
           href="<?= e(notif_url(['status' => 'unread', 'page' => 1])) ?>">
            Unread
            <?php if ($unreadTotal > 0): ?>
                <span class="tabs__badge"><?= (int) $unreadTotal ?></span>
            <?php endif; ?>
        </a>
        <a class="tabs__item <?= $statusFilter === 'read'   ? 'is-active' : '' ?>"
           href="<?= e(notif_url(['status' => 'read',   'page' => 1])) ?>">Read</a>
    </nav>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <h3>No notifications
                <?php if ($statusFilter === 'unread'): ?>unread<?php endif; ?>
                <?php if ($statusFilter === 'read'): ?>read<?php endif; ?>
            </h3>
            <p>
                <?php if ($statusFilter === 'all'): ?>
                    When your reports or collection requests are updated, you will see notifications here.
                <?php else: ?>
                    Nothing to show for this filter.
                    <a href="<?= e(notif_url(['status' => 'all', 'page' => 1])) ?>">View all notifications</a>.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <ul class="notification-list" role="list">
            <?php foreach ($notifications as $n): ?>
                <?php $unread = ((int) $n['is_read'] === 0); ?>
                <li class="notification-item <?= $unread ? 'is-unread' : '' ?>"
                    data-notification-id="<?= (int) $n['notification_id'] ?>">

                    <div class="notification-item__icon" aria-hidden="true">
                        <?= $unread ? '🔔' : '✓' ?>
                    </div>

                    <div class="notification-item__body">
                        <div class="notification-item__header">
                            <?php if (!empty($n['link'])): ?>
                                <a class="notification-item__title"
                                   href="<?= e(BASE_URL . $n['link']) ?>">
                                    <?= e($n['title']) ?>
                                </a>
                            <?php else: ?>
                                <span class="notification-item__title"><?= e($n['title']) ?></span>
                            <?php endif; ?>

                            <?php if ($unread): ?>
                                <span class="notification-item__pill" aria-label="Unread">New</span>
                            <?php endif; ?>
                        </div>

                        <p class="notification-item__message">
                            <?= e($n['message']) ?>
                        </p>

                        <div class="notification-item__meta">
                            <span><?= e(format_datetime($n['created_at'])) ?></span>
                            <span aria-hidden="true">·</span>
                            <span><?= e(time_ago($n['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="notification-item__actions">
                        <?php if ($unread): ?>
                            <form method="post"
                                  action="<?= e(BASE_URL) ?>/actions/notifications/mark-read.php"
                                  class="inline-form"
                                  data-mark-read-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    Mark as read
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($n['link'])): ?>
                            <a class="btn btn-secondary btn-sm"
                               href="<?= e(BASE_URL . $n['link']) ?>">
                                View
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Notification pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(notif_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(notif_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(notif_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(notif_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(notif_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>