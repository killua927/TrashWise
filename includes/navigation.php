<?php
/**
 * includes/navigation.php
 * ---------------------------------------------------------------------------
 * Role-aware top navigation.
 *
 * Guests see: Home, Waste Guide, Locations, Log In, Register.
 * Residents see: Dashboard, Report Waste, My Reports, Request Collection,
 *                Announcements, Notifications, Profile, Log Out.
 * Staff see:     Dashboard, Manage Reports, Manage Requests, Announcements,
 *                Analytics, Notifications, Profile, Log Out.
 * Admin see:     Staff items + Users, Barangays, Guides, Locations.
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

require_once __DIR__ . '/auth.php';

$loggedIn = is_logged_in();

// Unread notifications for logged-in users.
$unreadCount = 0;
if ($loggedIn) {
    try {
        $unreadCount = (int) db_scalar(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [current_user_id()]
        );
    } catch (Throwable $e) {
        $unreadCount = 0;
    }
}
?>
<header class="app-header">
    <div class="app-header__inner">
        <a class="app-brand" href="<?= e(BASE_URL) ?>/index.php" aria-label="TrashWise home">
            <img src="<?= e(BASE_URL) ?>/assets/images/trashwiselogo.png"
                 alt="TrashWise Logo" class="app-brand__logo" width="40" height="40">
            <span class="app-brand__name">TrashWise</span>
        </a>

        <button type="button" class="nav-toggle" id="navToggleBtn"
                aria-label="Toggle navigation" aria-expanded="false" aria-controls="primaryNav">
            <span></span><span></span><span></span>
        </button>

        <nav class="app-nav" id="primaryNav" aria-label="Primary">
            <ul class="app-nav__list">
                <?php if (!$loggedIn): ?>
                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/index.php">Home</a></li>
                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">Waste Guide</a></li>
                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/locations/disposal-locations.php">Locations</a></li>
                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/login.php">Log In</a></li>
                    <li><a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/register.php">Register</a></li>
                <?php else: ?>
                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a></li>

                    <?php if (is_resident()): ?>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/reports/report-waste.php">Report Waste</a></li>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">My Reports</a></li>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/collection/request-collection.php">Request Collection</a></li>
                    <?php endif; ?>

                    <?php if (is_staff() || is_admin()): ?>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/reports/manage-reports.php">Manage Reports</a></li>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/collection/manage-requests.php">Manage Requests</a></li>
                        <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/analytics/analytics.php">Analytics</a></li>
                    <?php endif; ?>

                    <?php if (is_admin()): ?>
                        <li class="app-nav__dropdown">
                            <button type="button" class="app-nav__link app-nav__link--button"
                                    aria-haspopup="true" aria-expanded="false">
                                Admin ▾
                            </button>
                            <ul class="app-nav__submenu">
                                <li><a href="<?= e(BASE_URL) ?>/modules/administration/manage-users.php">Users</a></li>
                                <li><a href="<?= e(BASE_URL) ?>/modules/administration/manage-barangays.php">Barangays</a></li>
                                <li><a href="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php">Waste Guides</a></li>
                                <li><a href="<?= e(BASE_URL) ?>/modules/locations/manage-locations.php">Locations</a></li>
                                <li><a href="<?= e(BASE_URL) ?>/modules/announcements/manage-announcements.php">Announcements</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <li><a class="app-nav__link" href="<?= e(BASE_URL) ?>/modules/announcements/announcements.php">Announcements</a></li>

                    <li>
                        <a class="app-nav__link app-nav__link--icon"
                           href="<?= e(BASE_URL) ?>/modules/notifications/notifications.php"
                           aria-label="Notifications">
                            <span aria-hidden="true">🔔</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="nav-badge"><?= (int) $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="app-nav__user">
                        <span class="app-nav__user-name"><?= e(current_user_name()) ?></span>
                        <a class="app-nav__link app-nav__link--small"
                           href="<?= e(BASE_URL) ?>/profile.php">Profile</a>
                        <a class="app-nav__link app-nav__link--small app-nav__link--logout"
                           href="<?= e(BASE_URL) ?>/logout.php">Log Out</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>