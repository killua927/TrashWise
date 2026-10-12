/**
 * assets/js/dashboard.js
 * ---------------------------------------------------------------
 * Resident dashboard behaviour:
 *   - Personalises the greeting by time of day.
 *   - Lightweight client-side "mark as read" for dashboard
 *     notification items when clicked (server-side action is
 *     still required for persistence).
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Time-based greeting ----------
        var greetingEl = document.querySelector('[data-greeting-time]');
        if (greetingEl) {
            var hour = new Date().getHours();
            var greeting = 'Hello';
            if (hour < 12) greeting = 'Good morning';
            else if (hour < 18) greeting = 'Good afternoon';
            else greeting = 'Good evening';
            greetingEl.textContent = greeting;
        }

        // ---------- Notification click: mark visual read ----------
        document.querySelectorAll('.activity-list__item.is-unread a').forEach(function (link) {
            link.addEventListener('click', function () {
                // Visual feedback only. Server-side action marks it read.
                var item = link.closest('.activity-list__item');
                if (item) item.classList.remove('is-unread');
            });
        });
    });
})();