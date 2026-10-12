<?php
/**
 * modules/announcements/announcements.php
 * ---------------------------------------------------------------
 * Public and authenticated list of active announcements.
 *
 * Access:
 *   - Public (guests + authenticated users).
 *
 * Rules:
 *   - Only is_active = 1 shown.
 *   - Hidden if expires_at is in the past.
 *   - Barangay-scoped announcements shown only if barangay_id matches
 *     the user's barangay OR the user is not logged in (system-wide only).
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

enforce_session_timeout();

$userId       = current_user_id();
$barangayId   = isset($_SESSION['barangay_id']) ? (int) $_SESSION['barangay_id'] : 0;

// Build WHERE based on login state.
$where  = [
    'a.is_active = 1',
    '(a.expires_at IS NULL OR a.expires_at > NOW())',
];
$params = [];

if ($userId && $barangayId > 0) {
    // Logged-in with a barangay: system-wide OR their barangay.
    $where[] = '(a.barangay_id IS NULL OR a.barangay_id = ?)';
    $params[] = $barangayId;
} else {
    // Guest: only system-wide (barangay_id IS NULL).
    $where[] = 'a.barangay_id IS NULL';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$announcements = db_all("
    SELECT a.announcement_id, a.title, a.content, a.published_at,
           a.expires_at, a.barangay_id,
           b.barangay_name,
           u.first_name, u.last_name
      FROM announcements a
      JOIN users    u ON u.user_id = a.created_by
      LEFT JOIN barangays b ON b.barangay_id = a.barangay_id
     {$whereSql}
     ORDER BY a.published_at DESC
     LIMIT 50
", $params);

$pageTitle = 'Announcements';
$pageCss   = ['announcements.css'];
$pageJs    = ['announcements.js'];
$bodyClass = $userId ? 'is-authenticated' : '';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Announcements</span>
    </nav>
    <h1 class="page-title">Community Announcements</h1>
    <p class="text-muted">
        Waste management activities, collection schedules, clean-up drives,
        and other community updates.
    </p>
</section>

<section class="container">
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <h3>No announcements at the moment</h3>
            <p>Please check back later or subscribe to in-system notifications.</p>
        </div>
    <?php else: ?>
        <ul class="announcement-list" role="list">
            <?php foreach ($announcements as $a): ?>
                <li class="announcement-item card">
                    <header class="announcement-item__header">
                        <h2 class="announcement-item__title"><?= e($a['title']) ?></h2>
                        <div class="announcement-item__meta">
                            <span class="announcement-item__date">
                                <?= e(format_datetime($a['published_at'])) ?>
                            </span>
                            <?php if (!empty($a['barangay_name'])): ?>
                                <span class="announcement-item__scope">
                                    📍 <?= e($a['barangay_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="announcement-item__scope">
                                    🌐 System-wide
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($a['expires_at'])): ?>
                                <span class="announcement-item__expires">
                                    Expires <?= e(format_date($a['expires_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="announcement-item__content">
                        <?= nl2br(e($a['content'])) ?>
                    </div>

                    <footer class="announcement-item__footer">
                        <span>Posted by <?= e($a['first_name'] . ' ' . $a['last_name']) ?></span>
                    </footer>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>