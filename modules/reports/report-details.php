<?php
/**
 * modules/reports/report-details.php
 * ---------------------------------------------------------------
 * Full detail view of a waste report.
 *
 * Access:
 *   - Resident: only their own reports.
 *   - Admin / Staff: any report (future use).
 *
 * Loads:
 *   - Report row + joins (category, concern type, status, barangay)
 *   - Submitter info (name, email)
 *   - All photos from report_photos
 *   - Status history from report_status_history
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$reportId = input_int('id', 'GET');
if ($reportId <= 0) {
    flash('error', 'Invalid report reference.');
    redirect('modules/reports/my-reports.php');
}

// -------------------------------------------------------------
// Fetch report
// -------------------------------------------------------------
$report = db_one("
    SELECT wr.report_id, wr.user_id, wr.barangay_id, wr.title, wr.description,
           wr.location, wr.latitude, wr.longitude, wr.is_locked,
           wr.locked_at, wr.locked_by, wr.submitted_at, wr.updated_at,
           rs.status_id, rs.status_name, rs.is_locked AS status_locks,
           wc.category_id, wc.category_name, wc.color_code,
           ct.concern_type_id, ct.type_name,
           b.barangay_name,
           u.first_name, u.last_name, u.email
      FROM waste_reports wr
      JOIN report_statuses  rs ON rs.status_id       = wr.status_id
      JOIN waste_categories wc ON wc.category_id     = wr.category_id
      JOIN concern_types    ct ON ct.concern_type_id = wr.concern_type_id
      JOIN barangays        b  ON b.barangay_id      = wr.barangay_id
      JOIN users            u  ON u.user_id          = wr.user_id
     WHERE wr.report_id = ?
     LIMIT 1
", [$reportId]);

if (!$report) {
    flash('error', 'Report not found.');
    redirect('modules/reports/my-reports.php');
}

// -------------------------------------------------------------
// Ownership / role check
// -------------------------------------------------------------
$isOwner = ((int) $report['user_id'] === current_user_id());
if (!$isOwner && !is_admin() && !is_staff()) {
    http_response_code(403);
    exit('You do not have permission to view this report.');
}

// Residents can only view their own.
if (is_resident() && !$isOwner) {
    http_response_code(403);
    exit('Forbidden.');
}

// -------------------------------------------------------------
// Photos
// -------------------------------------------------------------
$photos = db_all("
    SELECT photo_id, file_path, original_filename, uploaded_at
      FROM report_photos
     WHERE report_id = ?
     ORDER BY uploaded_at ASC, photo_id ASC
", [$reportId]);

// -------------------------------------------------------------
// Status history
// -------------------------------------------------------------
$history = db_all("
    SELECT h.history_id, h.remarks, h.changed_at,
           os.status_name AS old_status_name,
           ns.status_name AS new_status_name,
           u.first_name, u.last_name
      FROM report_status_history h
      LEFT JOIN report_statuses os ON os.status_id = h.old_status_id
      JOIN report_statuses      ns ON ns.status_id = h.new_status_id
      JOIN users                u  ON u.user_id    = h.changed_by
     WHERE h.report_id = ?
     ORDER BY h.changed_at ASC, h.history_id ASC
", [$reportId]);

// -------------------------------------------------------------
// Locked state
// -------------------------------------------------------------
$isLocked  = ((int) $report['is_locked'] === 1);
$canEdit   = $isOwner && !$isLocked;
$canDelete = $isOwner && !$isLocked;

// -------------------------------------------------------------
// Locked-by user (optional)
// -------------------------------------------------------------
$lockedBy = null;
if (!empty($report['locked_by'])) {
    $lockedBy = db_one("
        SELECT first_name, last_name FROM users WHERE user_id = ?
    ", [(int) $report['locked_by']]);
}

$pageTitle = 'Report Details';
$pageCss   = ['report-details.css'];
$pageJs    = ['report-details.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">My Reports</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">#<?= (int) $report['report_id'] ?></span>
    </nav>

    <div class="page-header__row">
        <div>
            <h1 class="page-title"><?= e($report['title']) ?></h1>
            <div class="report-meta">
                <span class="<?= e(report_status_class($report['status_name'])) ?>">
                    <?= e($report['status_name']) ?>
                </span>
                <span class="report-meta__category"
                      style="border-left-color: <?= e($report['color_code'] ?: '#6BA24B') ?>;">
                    <?= e($report['category_name']) ?>
                </span>
                <span class="report-meta__item">
                    <?= e($report['type_name']) ?>
                </span>
            </div>
        </div>

        <div class="page-header__actions">
            <?php if ($canEdit): ?>
                <a class="btn btn-secondary"
                   href="<?= e(BASE_URL) ?>/modules/reports/edit-report.php?id=<?= (int) $report['report_id'] ?>">
                    Edit Report
                </a>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-danger"
                        id="deleteReportBtn"
                        data-report-id="<?= (int) $report['report_id'] ?>"
                        data-report-title="<?= e($report['title']) ?>">
                    Delete Report
                </button>
            <?php endif; ?>
            <a class="btn btn-outline"
               href="<?= e(BASE_URL) ?>/modules/reports/my-reports.php">
                Back
            </a>
        </div>
    </div>
</section>

<section class="container report-details">
    <?php if ($isLocked): ?>
        <div class="alert alert-info" role="status">
            <strong>This report is locked.</strong>
            It has been marked
            <em><?= e($report['status_name']) ?></em>
            <?php if (!empty($report['locked_at'])): ?>
                on <?= e(format_datetime($report['locked_at'])) ?>
            <?php endif; ?>
            <?php if ($lockedBy): ?>
                by <?= e($lockedBy['first_name'] . ' ' . $lockedBy['last_name']) ?>
            <?php endif; ?>.
            Locked reports cannot be edited or deleted.
        </div>
    <?php endif; ?>

    <div class="report-details__grid">
        <!-- Main content -->
        <div class="report-details__main">
            <article class="card">
                <h2 class="card-title">Description</h2>
                <p class="report-details__description">
                    <?= nl2br(e($report['description'])) ?>
                </p>
            </article>

            <!-- Photo gallery -->
            <article class="card">
                <h2 class="card-title">
                    Photo Evidence
                    <span class="card-title__count">(<?= count($photos) ?>)</span>
                </h2>

                <?php if (empty($photos)): ?>
                    <div class="empty-state empty-state--compact">
                        <p>No photos are attached to this report.</p>
                    </div>
                <?php else: ?>
                    <div class="photo-gallery" id="photoGallery">
                        <?php foreach ($photos as $index => $p): ?>
                            <button type="button"
                                    class="photo-gallery__item"
                                    data-photo-index="<?= (int) $index ?>"
                                    data-photo-src="<?= e(BASE_URL . '/' . ltrim($p['file_path'], '/')) ?>"
                                    data-photo-name="<?= e($p['original_filename']) ?>"
                                    aria-label="View <?= e($p['original_filename']) ?>">
                                <img src="<?= e(BASE_URL . '/' . ltrim($p['file_path'], '/')) ?>"
                                     alt="<?= e($p['original_filename']) ?>"
                                     loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Status history -->
            <article class="card">
                <h2 class="card-title">Status History</h2>

                <?php if (empty($history)): ?>
                    <div class="empty-state empty-state--compact">
                        <p>No status changes recorded yet.</p>
                    </div>
                <?php else: ?>
                    <ol class="timeline">
                        <?php foreach ($history as $h): ?>
                            <li class="timeline__item">
                                <div class="timeline__marker" aria-hidden="true"></div>
                                <div class="timeline__content">
                                    <div class="timeline__header">
                                        <?php if ($h['old_status_name']): ?>
                                            <span class="timeline__transition">
                                                <span class="timeline__from"><?= e($h['old_status_name']) ?></span>
                                                <span aria-hidden="true">→</span>
                                                <span class="timeline__to"><?= e($h['new_status_name']) ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="timeline__transition">
                                                <span class="timeline__to"><?= e($h['new_status_name']) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($h['remarks'])): ?>
                                        <p class="timeline__remarks"><?= nl2br(e($h['remarks'])) ?></p>
                                    <?php endif; ?>
                                    <p class="timeline__meta">
                                        By <?= e($h['first_name'] . ' ' . $h['last_name']) ?>
                                        · <?= e(format_datetime($h['changed_at'])) ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </article>
        </div>

        <!-- Sidebar -->
        <aside class="report-details__sidebar">
            <article class="card">
                <h3 class="card-title">Report Information</h3>
                <dl class="details-list">
                    <div class="details-list__row">
                        <dt>Report ID</dt>
                        <dd>#<?= (int) $report['report_id'] ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Submitted by</dt>
                        <dd><?= e($report['first_name'] . ' ' . $report['last_name']) ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Submitted on</dt>
                        <dd><?= e(format_datetime($report['submitted_at'])) ?></dd>
                    </div>
                    <?php if ($report['updated_at'] && $report['updated_at'] !== $report['submitted_at']): ?>
                        <div class="details-list__row">
                            <dt>Last updated</dt>
                            <dd><?= e(format_datetime($report['updated_at'])) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="details-list__row">
                        <dt>Barangay</dt>
                        <dd><?= e($report['barangay_name']) ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Specific location</dt>
                        <dd><?= e($report['location']) ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Status</dt>
                        <dd>
                            <span class="<?= e(report_status_class($report['status_name'])) ?>">
                                <?= e($report['status_name']) ?>
                            </span>
                        </dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Locked</dt>
                        <dd><?= $isLocked ? 'Yes' : 'No' ?></dd>
                    </div>
                </dl>
            </article>
        </aside>
    </div>
</section>

<!-- Photo lightbox -->
<div class="lightbox hidden" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <button type="button" class="lightbox__close" aria-label="Close viewer">×</button>
    <button type="button" class="lightbox__nav lightbox__nav--prev" aria-label="Previous photo">‹</button>
    <img class="lightbox__img" src="" alt="">
    <button type="button" class="lightbox__nav lightbox__nav--next" aria-label="Next photo">›</button>
    <p class="lightbox__caption" aria-live="polite"></p>
</div>

<!-- Hidden delete form -->
<form id="deleteReportForm"
      method="post"
      action="<?= e(BASE_URL) ?>/actions/reports/delete-report.php"
      class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="report_id" id="deleteReportId" value="">
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>