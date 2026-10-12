/**
 * assets/js/disposal-locations.js
 * ---------------------------------------------------------------
 * Disposal facilities map (Leaflet + OpenStreetMap).
 *
 * Responsibilities:
 *   - Initialize the map centered on Minglanilla, Cebu.
 *   - Plot markers from window.TRASHWISE_MARKERS.
 *   - Custom divIcon with two states (default / active).
 *   - Popup with facility name, type, address, hours, contact.
 *   - Sidebar ↔ marker synchronization.
 *   - Fit bounds to visible markers.
 *
 * No GPS tracking, routing, or navigation.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    // Minglanilla, Cebu approximate center.
    var DEFAULT_CENTER = [10.2449, 123.7956];
    var DEFAULT_ZOOM   = 13;

    document.addEventListener('DOMContentLoaded', function () {
        var mapEl = document.getElementById('facilitiesMap');
        if (!mapEl || typeof window.L === 'undefined') return;

        var markers = window.TRASHWISE_MARKERS || [];
        var listEl  = document.getElementById('facilityList');

        // ---------- Map ----------
        var map = L.map(mapEl, {
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            scrollWheelZoom: true,
            attributionControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        if (markers.length === 0) return;

        // ---------- Custom marker icon ----------
        function makeIcon(active) {
            return L.divIcon({
                className: 'tw-marker-wrapper',
                html: '<div class="tw-marker' + (active ? ' tw-marker--active' : '') + '"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
                popupAnchor: [0, -14],
            });
        }

        // ---------- Popup content ----------
        function popupHtml(m) {
            var html = '<h3>' + escapeHtml(m.name) + '</h3>';
            if (m.type)     html += '<p><strong>Type:</strong> ' + escapeHtml(m.type) + '</p>';
            if (m.address)  html += '<p>' + escapeHtml(m.address) + '</p>';
            if (m.barangay) html += '<p><strong>Barangay:</strong> ' + escapeHtml(m.barangay) + '</p>';
            if (m.categories) html += '<p><strong>Accepts:</strong> ' + escapeHtml(m.categories) + '</p>';
            if (m.hours)    html += '<p><strong>Hours:</strong> ' + escapeHtml(m.hours) + '</p>';
            if (m.contact)  html += '<p><strong>Contact:</strong> ' + escapeHtml(m.contact) + '</p>';
            return html;
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ---------- Add markers ----------
        var leafletMarkers = {};   // { facilityId: marker }
        var bounds = L.latLngBounds([]);

        markers.forEach(function (m) {
            var marker = L.marker([m.lat, m.lng], { icon: makeIcon(false) })
                .addTo(map)
                .bindPopup(popupHtml(m));

            leafletMarkers[m.id] = marker;
            bounds.extend([m.lat, m.lng]);
        });

        // Fit bounds if we have at least one valid point.
        if (bounds.isValid()) {
            map.fitBounds(bounds.pad(0.15));
        }

        // ---------- Sidebar ↔ map sync ----------
        var activeId = null;

        function setActive(id) {
            if (activeId !== null && leafletMarkers[activeId]) {
                leafletMarkers[activeId].setIcon(makeIcon(false));
            }

            document.querySelectorAll('.facility-item').forEach(function (li) {
                li.classList.toggle('is-active',
                    String(li.getAttribute('data-facility-id')) === String(id));
            });

            if (leafletMarkers[id]) {
                leafletMarkers[id].setIcon(makeIcon(true));
            }

            activeId = id;
        }

        function focusFacility(id) {
            var marker = leafletMarkers[id];
            if (!marker) return;

            setActive(id);

            var latlng = marker.getLatLng();
            map.setView(latlng, Math.max(map.getZoom(), 16), { animate: true });
            marker.openPopup();

            // Scroll the sidebar item into view.
            if (listEl) {
                var item = listEl.querySelector('.facility-item[data-facility-id="' + id + '"]');
                if (item && item.scrollIntoView) {
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }

        // Click a sidebar item.
        document.querySelectorAll('.facility-item__button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li = btn.closest('.facility-item');
                if (!li) return;
                if (li.getAttribute('data-has-coords') !== '1') return;
                focusFacility(li.getAttribute('data-facility-id'));
            });
        });

        // Click a marker.
        Object.keys(leafletMarkers).forEach(function (id) {
            leafletMarkers[id].on('click', function () {
                setActive(id);
            });
        });

        // ---------- Keep map size correct on layout changes ----------
        window.addEventListener('resize', function () {
            map.invalidateSize();
        });

        // If the first facility has coordinates, pre-focus it.
        var firstWithCoords = document.querySelector('.facility-item[data-has-coords="1"]');
        if (firstWithCoords) {
            // Uncomment the next line to auto-focus on load:
            // focusFacility(firstWithCoords.getAttribute('data-facility-id'));
        }
    });
})();