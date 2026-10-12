<?php
/**
 * includes/footer.php
 * ---------------------------------------------------------------------------
 * Shared page footer and script loading.
 *
 * Loads:
 *   - global.js
 *   - header.js
 *   - footer.js
 *   - page-specific JS (deferred)
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

$pageJs = $pageJs ?? [];
?>
</main>

<footer class="app-footer">
    <div class="app-footer__inner">
        <div class="app-footer__brand">
            <img src="<?= e(BASE_URL) ?>/assets/images/trashwiselogo.png"
                 alt="TrashWise Logo" class="app-footer__logo" width="48" height="48">
            <div>
                <strong>TrashWise</strong>
                <p>Community Waste Management for Minglanilla, Cebu</p>
            </div>
        </div>

        <nav class="app-footer__links" aria-label="Footer">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <a href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">Waste Guide</a>
            <a href="<?= e(BASE_URL) ?>/modules/locations/disposal-locations.php">Disposal Locations</a>
            <a href="<?= e(BASE_URL) ?>/modules/announcements/announcements.php">Announcements</a>
        </nav>

        <p class="app-footer__copy">
            &copy; <?= date('Y') ?> TrashWise. All rights reserved.
        </p>
    </div>
</footer>

<script src="<?= e(BASE_URL) ?>/assets/js/global.js" defer></script>
<script src="<?= e(BASE_URL) ?>/assets/js/header.js" defer></script>
<script src="<?= e(BASE_URL) ?>/assets/js/footer.js" defer></script>

<?php foreach ($pageJs as $js): ?>
    <script src="<?= e(BASE_URL) ?>/assets/js/<?= e($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>