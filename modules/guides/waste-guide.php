<?php
/**
 * modules/guides/waste-guide.php
 * ---------------------------------------------------------------
 * Public list of published waste management guides.
 *
 * Access:
 *   - Public (guests + authenticated users).
 *
 * Filters:
 *   - q            free text on title/content
 *   - category_id  waste category
 *   - page         pagination
 *
 * Only guides with is_published = 1 are shown.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// No login required. Session timeout runs only for logged-in users.
enforce_session_timeout();

// -------------------------------------------------------------
// Filters
// -------------------------------------------------------------
$q          = input_string('q', 'GET');
$categoryId = input_int('category_id', 'GET');
$page       = max(1, input_int('page', 'GET', 1));
$perPage    = 9;

$where  = ['wg.is_published = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(wg.title LIKE ? OR wg.content LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'wg.category_id = ?';
    $params[] = $categoryId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------------
// Count + paginate
// -------------------------------------------------------------
$total = (int) db_scalar("
    SELECT COUNT(*) FROM waste_guides wg {$whereSql}
", $params);

$pg = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page of guides
// -------------------------------------------------------------
$guides = db_all("
    SELECT wg.guide_id, wg.title, wg.content, wg.created_at, wg.updated_at,
           wc.category_id, wc.category_name, wc.color_code,
           u.first_name, u.last_name
      FROM waste_guides wg
      JOIN waste_categories wc ON wc.category_id = wg.category_id
      JOIN users            u  ON u.user_id      = wg.created_by
     {$whereSql}
     ORDER BY wg.updated_at DESC, wg.guide_id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

$categories = get_waste_categories();

$pageTitle = 'Waste Guide';
$pageCss   = ['waste-guide.css'];
$pageJs    = ['waste-guide.js'];

require_once __DIR__ . '/../../includes/header.php';

// URL builder preserving filters.
function guide_list_url(array $overrides = []): string
{
    $base = [
        'q'           => input_string('q', 'GET'),
        'category_id' => input_int('category_id', 'GET'),
        'page'        => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/guides/waste-guide.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}

// Short preview helper.
function guide_preview(string $content, int $len = 160): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
    return mb_strimwidth($plain, 0, $len, '…');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Waste Guide</span>
    </nav>
    <h1 class="page-title">Waste Management Guides</h1>
    <p class="text-muted">
        Learn how to properly segregate and dispose of different types of waste.
        Guides are maintained by TrashWise administrators.
    </p>
</section>

<section class="container">
    <form method="get" action="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search guides by title or content"
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
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== '' || $categoryId > 0): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($guides)): ?>
        <div class="empty-state">
            <h3>No guides found</h3>
            <?php if ($q !== '' || $categoryId > 0): ?>
                <p>Try adjusting your search or clearing the filters.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/guides/waste-guide.php">Clear Filters</a>
            <?php else: ?>
                <p>No guides have been published yet. Please check back later.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="guide-grid">
            <?php foreach ($guides as $g): ?>
                <?php
                    $url = BASE_URL . '/modules/guides/view-guide.php?id=' . (int) $g['guide_id'];
                    $author = trim($g['first_name'] . ' ' . $g['last_name']);
                ?>
                <article class="guide-card">
                    <span class="guide-card__category"
                          style="border-left-color: <?= e($g['color_code'] ?: '#6BA24B') ?>;">
                        <?= e($g['category_name']) ?>
                    </span>

                    <h2 class="guide-card__title">
                        <a href="<?= e($url) ?>"><?= e($g['title']) ?></a>
                    </h2>

                    <p class="guide-card__excerpt">
                        <?= e(guide_preview($g['content'], 160)) ?>
                    </p>

                    <footer class="guide-card__footer">
                        <span class="guide-card__author">By <?= e($author) ?></span>
                        <span class="guide-card__date"><?= e(time_ago($g['updated_at'] ?? $g['created_at'])) ?></span>
                    </footer>

                    <a class="btn btn-outline btn-sm guide-card__cta" href="<?= e($url) ?>">
                        Read Guide →
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Guide pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(guide_list_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(guide_list_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(guide_list_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(guide_list_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(guide_list_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>