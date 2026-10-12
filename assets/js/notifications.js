/**
 * assets/js/notifications.js
 * ---------------------------------------------------------------
 * Notification center behaviour:
 *   - Confirm "mark all as read".
 *   - Optional progressive enhancement: mark-as-read form submits
 *     normally; JS only removes the "unread" visual state after
 *     a successful submission if the page reloads client-side.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Confirm "mark all as read".
        var markAllForm = document.getElementById('markAllForm');
        if (markAllForm) {
            markAllForm.addEventListener('submit', function (evt) {
                if (!window.confirm('Mark every notification as read?')) {
                    evt.preventDefault();
                }
            });
        }

        // Disable single mark-as-read button after submit to prevent
        // accidental duplicate POSTs.
        document.querySelectorAll('[data-mark-read-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Marking…';
                }
            });
        });
    });
})();