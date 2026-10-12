<?php
/**
 * modules/locations/disposal-locations.php
 * ---------------------------------------------------------------
 * Public disposal facilities view with an interactive map.
 *
 * Access:
 *   - Public (guests + authenticated users).
 *
 * Filters:
 *   - barangay_id  optional
 *
 * Only facilities with is_active = 1 are shown.
 * Coordinates are pulled from the database and passed to Leaflet.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

enforce_session_timeout();

// -------------------------------------------------------------
// Filter
// -------------------------------------------------------------
$barangayFilter = input_int('barangay_id', 'GET');

$where  = ['dl.is_active = 1'];
$params = [];

if ($barangayFilter > 0) {
    $where[] = 'dl.barangay_id = ?';
    $params[] = $barangayFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------------
// Fetch facilities
// -------------------------------------------------------------
$facilities = db_all("
    SELECT dl.location_id, dl.location_name, dl.facility_type, dl.address,
           dl.latitude, dl.longitude, dl.accepted_categories,
           dl.operating_hours, dl.contact_number,
           b.barangay_id, b.barangay_name
      FROM disposal_locations dl
      JOIN barangays b ON b.barangay_id = dl.barangay_id
     {$whereSql}
     ORDER BY b.barangay_name ASC, dl.location_name ASC
", $params);

// -------------------------------------------------------------
// Prepare marker payload for Leaflet.
// Only include rows that have usable coordinates.
// -------------------------------------------------------------
$markers = [];
foreach ($facilities as $f) {
    if ($f['latitude'] === null || $f['longitude'] === null) {
        continue;
    }
    $markers[] = [
        'id'          => (int) $f['location_id'],
        'name'        => $f['location_name'],
        'type'        => $f['facility_type'],
        'address'     => $f['address'],
        'barangay'    => $f['barangay_name'],
        'lat'         => (float) $f['latitude'],
        'lng'         => (float) $f['longitude'],
        'categories'  => $f['accepted_categories'],
        'hours'       => $f['operating_hours'],
        'contact'     => $f['contact_number'],
    ];
}

$barangays = get_barangays();

$pageTitle = 'Disposal Locations';
$pageCss   = ['disposal-locations.css'];
$pageJs    = ['disposal-locations.js'];
$bodyClass = 'page-map';

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="">

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Disposal Locations</span>
    </nav>
    <h1 class="page-title">Disposal Locations</h1>
    <p class="text-muted">
        Find designated waste disposal facilities and collection points across Minglanilla, Cebu.
    </p>
</section>

<section class="container">
    <form method="get"
          action="<?= e(BASE_URL) ?>/modules/locations/disposal-locations.php"
          class="filters filters--inline">
        <div class="filters__row">
            <div class="filters__field">
                <label class="sr-only" for="barangay_id">Barangay</label>
                <select class="form-select" id="barangay_id" name="barangay_id">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= (int) $b['barangay_id'] ?>"
                            <?= $barangayFilter === (int) $b['barangay_id'] ? 'selected' : '' ?>>
                            <?= e($b['barangay_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($barangayFilter > 0): ?>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/locations/disposal-locations.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($facilities)): ?>
        <div class="empty-state">
            <h3>No disposal facilities found</h3>
            <?php if ($barangayFilter > 0): ?>
                <p>Try a different barangay or reset the filter.</p>
                <a class="btn btn-outline"
                   href="<?= e(BASE_URL) ?>/modules/locations/disposal-locations.php">Show All</a>
            <?php else: ?>
                <p>No disposal facilities are currently registered.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="facilities-layout">
            <!-- Sidebar list -->
            <aside class="facilities-sidebar" aria-label="Facility list">
                <div class="facilities-sidebar__header">
                    <h2 class="facilities-sidebar__title">
                        <?= count($facilities) ?> facilit<?= count($facilities) === 1 ? 'y' : 'ies' ?>
                    </h2>
                    <p class="facilities-sidebar__hint">Click a facility to locate it on the map.</p>
                </div>

                <ul class="facility-list" id="facilityList" role="list">
                    <?php foreach ($facilities as $f): ?>
                        <?php
                            $hasCoords = $f['latitude'] !== null && $f['longitude'] !== null;
                        ?>
                        <li class="facility-item"
                            data-facility-id="<?= (int) $f['location_id'] ?>"
                            data-has-coords="<?= $hasCoords ? '1' : '0' ?>">
                            <button type="button"
                                    class="facility-item__button"
                                    <?= $hasCoords ? '' : 'disabled aria-disabled="true"' ?>>
                                <span class="facility-item__name"><?= e($f['location_name']) ?></span>
                                <?php if (!empty($f['facility_type'])): ?>
                                    <span class="facility-item__type"><?= e($f['facility_type']) ?></span>
                                <?php endif; ?>
                                <span class="facility-item__address">
                                    <?= e($f['address']) ?>
                                </span>
                                <span class="facility-item__barangay"><?= e($f['barangay_name']) ?></span>

                                <?php if (!empty($f['accepted_categories'])): ?>
                                    <span class="facility-item__categories">
                                        Accepts: <?= e($f['accepted_categories']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($f['operating_hours'])): ?>
                                    <span class="facility-item__hours">
                                        🕒 <?= e($f['operating_hours']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($f['contact_number'])): ?>
                                    <span class="facility-item__contact">
                                        📞 <?= e($f['contact_number']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!$hasCoords): ?>
                                    <span class="facility-item__warning">
                                        No coordinates available
                                    </span>
                                <?php endif; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Map -->
            <div class="facilities-map-wrapper">
                <div id="facilitiesMap"
                     class="facilities-map"
                     role="region"
                     aria-label="Map of disposal facilities"></div>

                <?php if (empty($markers)): ?>
                    <div class="facilities-map__empty">
                        <p>No facilities have valid coordinates yet.
                           Please check back later or contact the administrator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Marker data for Leaflet -->
<script>
    window.TRASHWISE_MARKERS = <?= json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>