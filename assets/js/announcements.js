/**
 * assets/js/announcements.js
 * ---------------------------------------------------------------
 * Announcement list page.
 *
 * The public announcement list currently needs no interactive logic.
 * This file exists to satisfy the strict file-separation requirement
 * for every PHP page and to provide a place for future enhancements
 * (e.g., expand/collapse long announcements).
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Progressive enhancement placeholder:
        // give each announcement a stable id-based anchor for deep linking.
        document.querySelectorAll('.announcement-item').forEach(function (item, index) {
            if (!item.id) {
                item.id = 'announcement-' + (index + 1);
            }
        });
    });
})();