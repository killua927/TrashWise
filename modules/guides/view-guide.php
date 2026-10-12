<?php
/**
 * modules/guides/view-guide.php
 * ---------------------------------------------------------------
 * Public detail view of a single published waste guide.
 *
 * Access:
 *   - Public (guests + authenticated).
 *
 * Only guides with is_published = 1 are visible.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

enforce_session_timeout();

$guideId = input_int('id', 'GET');
if ($guideId <= 0) {
    flash('error', 'Invalid guide reference.');
    redirect('modules/guides/waste-guide.php');
}

// -------------------------------------------------------------
// Fetch guide (published only)
// -------------------------------------------------------------
$guide = db_one("
    SELECT wg.guide_id, wg.title, wg.content, wg.created_at, wg.updated_at,
           wc.category_id, wc.category_name, wc.color_code,
           u.first_name, u.last_name
      FROM waste_guides wg
      JOIN waste_categories wc ON wc.category_id = wg.category_id
      JOIN users            u  ON u.user_id      = wg.created_by
     WHERE wg.guide_id = ? AND wg.is_published = 1
     LIMIT 1
", [$guideId]);

if (!$guide) {
    flash('error', 'The requested guide is not available.');
    redirect('modules/guides/waste-guide.php');
}

// -------------------------------------------------------------
// Related guides (same category, excluding current)
// -------------------------------------------------------------
$related = db_all("
    SELECT guide_id, title, created_at, updated_at
      FROM waste_guides
     WHERE category_id = ? AND is_published = 1 AND guide_id <> ?
     ORDER BY updated_at DESC, guide_id DESC
     LIMIT 4
", [(int) $guide['category_id'], $guideId]);

$author = trim($guide['first_name'] . ' ' . $guide['last_name']);
$lastUpdated = $guide['updated_at'] ?: $guide['created_at'];

$pageTitle = $guide['title'];
$pageCss   = ['view-guide.css'];
$pageJs    = ['view-guide.js'];

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">Waste Guide</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page"><?= e(mb_strimwidth($guide['title'], 0, 60, '…')) ?></span>
    </nav>
</section>

<section class="container guide-view">
    <div class="guide-view__grid">
        <!-- Main article -->
        <article class="guide-view__main card">
            <span class="guide-view__category"
                  style="border-left-color: <?= e($guide['color_code'] ?: '#6BA24B') ?>;">
                <?= e($guide['category_name']) ?>
            </span>

            <h1 class="guide-view__title"><?= e($guide['title']) ?></h1>

            <div class="guide-view__meta">
                <span>By <strong><?= e($author) ?></strong></span>
                <span aria-hidden="true">·</span>
                <span>Updated <?= e(format_datetime($lastUpdated)) ?></span>
                <?php if ($guide['created_at'] !== $guide['updated_at']): ?>
                    <span aria-hidden="true">·</span>
                    <span>Published <?= e(format_date($guide['created_at'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="guide-view__content">
                <?= nl2br(e($guide['content'])) ?>
            </div>

            <footer class="guide-view__footer">
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">
                    ← Back to all guides
                </a>
            </footer>
        </article>

        <!-- Sidebar: related guides -->
        <aside class="guide-view__sidebar">
            <div class="card">
                <h2 class="card-title">
                    More in <?= e($guide['category_name']) ?>
                </h2>

                <?php if (empty($related)): ?>
                    <p class="text-muted">No other guides in this category yet.</p>
                <?php else: ?>
                    <ul class="related-guides">
                        <?php foreach ($related as $r): ?>
                            <li class="related-guides__item">
                                <a href="<?= e(BASE_URL) ?>/modules/guides/view-guide.php?id=<?= (int) $r['guide_id'] ?>">
                                    <?= e($r['title']) ?>
                                </a>
                                <span class="related-guides__date">
                                    <?= e(time_ago($r['updated_at'] ?: $r['created_at'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>