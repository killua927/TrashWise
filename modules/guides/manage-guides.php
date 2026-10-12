<?php
/**
 * modules/guides/manage-guides.php
 * ---------------------------------------------------------------
 * Admin guide management list.
 *
 * Guards:
 *   - require_role(ROLE_ADMIN)
 *
 * Filters:
 *   - q           free text on title/content
 *   - category_id waste category
 *   - status      all | published | draft
 *   - page        pagination
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
$categoryId = input_int('category_id', 'GET');
$status     = input_string('status', 'GET');
if (!in_array($status, ['all', 'published', 'draft'], true)) {
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
    $where[] = '(wg.title LIKE ? OR wg.content LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'wg.category_id = ?';
    $params[] = $categoryId;
}
if ($status === 'published') { $where[] = 'wg.is_published = 1'; }
if ($status === 'draft')     { $where[] = 'wg.is_published = 0'; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// -------------------------------------------------------------
// Count + paginate
// -------------------------------------------------------------
$total = (int) db_scalar("SELECT COUNT(*) FROM waste_guides wg {$whereSql}", $params);
$pg    = paginate($total, $perPage, $page);

// -------------------------------------------------------------
// Fetch page
// -------------------------------------------------------------
$guides = db_all("
    SELECT wg.guide_id, wg.title, wg.content, wg.is_published,
           wg.created_at, wg.updated_at,
           wc.category_name, wc.color_code,
           u.first_name, u.last_name
      FROM waste_guides wg
      JOIN waste_categories wc ON wc.category_id = wg.category_id
      JOIN users            u  ON u.user_id      = wg.created_by
     {$whereSql}
     ORDER BY wg.updated_at DESC, wg.guide_id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
", $params);

$categories = get_waste_categories();

$pageTitle = 'Manage Guides';
$pageCss   = ['manage-guides.css'];
$pageJs    = ['manage-guides.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';

// URL builder.
function manage_guides_url(array $overrides = []): string
{
    $base = [
        'q'           => input_string('q', 'GET'),
        'category_id' => input_int('category_id', 'GET'),
        'status'      => input_string('status', 'GET'),
        'page'        => input_int('page', 'GET', 1),
    ];
    $merged = array_filter(array_merge($base, $overrides), function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return BASE_URL . '/modules/guides/manage-guides.php' .
        ($merged ? '?' . http_build_query($merged) : '');
}
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Waste Guides</span>
    </nav>

    <div class="page-header__row">
        <div>
            <h1 class="page-title">Manage Waste Guides</h1>
            <p class="text-muted">
                <strong><?= (int) $total ?></strong>
                guide<?= $total === 1 ? '' : 's' ?> match the current filters.
            </p>
        </div>
        <a class="btn btn-primary"
           href="<?= e(BASE_URL) ?>/modules/guides/edit-guide.php">
            + New Guide
        </a>
    </div>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php"
          class="filters" role="search">
        <div class="filters__row">
            <div class="filters__field filters__field--grow">
                <label class="sr-only" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       placeholder="Search by title or content"
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
                <label class="sr-only" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all"       <?= $status === 'all'       ? 'selected' : '' ?>>All Status</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q !== '' || $categoryId > 0 || $status !== 'all'): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($guides)): ?>
        <div class="empty-state">
            <h3>No guides found</h3>
            <?php if ($q !== '' || $categoryId > 0 || $status !== 'all'): ?>
                <p>Try adjusting your filters or resetting the search.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/guides/manage-guides.php">Clear Filters</a>
            <?php else: ?>
                <p>No waste guides have been created yet.</p>
                <a class="btn btn-primary"
                   href="<?= e(BASE_URL) ?>/modules/guides/edit-guide.php">Create Your First Guide</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table manage-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guides as $g): ?>
                        <tr>
                            <td>#<?= (int) $g['guide_id'] ?></td>
                            <td>
                                <span class="cell-title"><?= e(mb_strimwidth($g['title'], 0, 70, '…')) ?></span>
                                <span class="cell-sub">
                                    <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags($g['content'])), 0, 80, '…')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="cell-category"
                                      style="border-left-color: <?= e($g['color_code'] ?: '#6BA24B') ?>;">
                                    <?= e($g['category_name']) ?>
                                </span>
                            </td>
                            <td><?= e($g['first_name'] . ' ' . $g['last_name']) ?></td>
                            <td>
                                <?php if ((int) $g['is_published'] === 1): ?>
                                    <span class="status-badge status-verified">Published</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(time_ago($g['updated_at'] ?: $g['created_at'])) ?></td>
                            <td class="text-right">
                                <div class="action-group">
                                    <a class="btn btn-outline btn-sm"
                                       href="<?= e(BASE_URL) ?>/modules/guides/edit-guide.php?id=<?= (int) $g['guide_id'] ?>">
                                        Edit
                                    </a>
                                    <form method="post"
                                          action="<?= e(BASE_URL) ?>/actions/guides/toggle-guide.php"
                                          class="inline-form"
                                          data-toggle-guide-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="guide_id" value="<?= (int) $g['guide_id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= (int) $g['is_published'] === 1 ? 'btn-secondary' : 'btn-primary' ?>"
                                                data-toggle-guide-button
                                                data-guide-title="<?= e($g['title']) ?>"
                                                data-action="<?= (int) $g['is_published'] === 1 ? 'unpublish' : 'publish' ?>">
                                            <?= (int) $g['is_published'] === 1 ? 'Unpublish' : 'Publish' ?>
                                        </button>
                                    </form>
                                    <form method="post"
                                          action="<?= e(BASE_URL) ?>/actions/guides/delete-guide.php"
                                          class="inline-form"
                                          data-delete-guide-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="guide_id" value="<?= (int) $g['guide_id'] ?>">
                                        <button type="submit"
                                                class="btn btn-danger btn-sm"
                                                data-delete-guide-button
                                                data-guide-title="<?= e($g['title']) ?>">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pg['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Guide pagination">
                <?php if ($pg['current_page'] > 1): ?>
                    <a href="<?= e(manage_guides_url(['page' => $pg['current_page'] - 1])) ?>"
                       aria-label="Previous page">‹</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $pg['current_page'] - 2);
                    $end   = min($pg['total_pages'], $pg['current_page'] + 2);
                    if ($start > 1) {
                        echo '<a href="' . e(manage_guides_url(['page' => 1])) . '">1</a>';
                        if ($start > 2) echo '<span>…</span>';
                    }
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $pg['current_page']): ?>
                        <span class="is-active" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(manage_guides_url(['page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                    <?php if ($end < $pg['total_pages'] - 1) echo '<span>…</span>'; ?>
                    <a href="<?= e(manage_guides_url(['page' => $pg['total_pages']])) ?>">
                        <?= (int) $pg['total_pages'] ?>
                    </a>
                <?php endif; ?>

                <?php if ($pg['current_page'] < $pg['total_pages']): ?>
                    <a href="<?= e(manage_guides_url(['page' => $pg['current_page'] + 1])) ?>"
                       aria-label="Next page">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>