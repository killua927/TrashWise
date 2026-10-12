/**
 * assets/js/my-requests.js
 * ---------------------------------------------------------------
 * My Collection Requests page:
 *   - Toggle inline request details.
 *   - Auto-submit filter form when selects change.
 *   - Cancel confirmation → submit hidden form.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Details toggle ----------
        document.querySelectorAll('[data-toggle-details]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id      = btn.getAttribute('data-toggle-details');
                var target  = document.getElementById('request-details-' + id);
                if (!target) return;

                var isOpen = !target.classList.contains('hidden');
                if (isOpen) {
                    target.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Details';
                } else {
                    target.classList.remove('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    btn.textContent = 'Hide details';
                }
            });
        });

        // ---------- Filter auto-submit ----------
        var filterForm = document.querySelector('.filters');
        if (filterForm) {
            filterForm.querySelectorAll('select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var pageInput = filterForm.querySelector('input[name="page"]');
                    if (pageInput) pageInput.value = '1';
                    filterForm.submit();
                });
            });
        }

        // ---------- Cancel confirmation ----------
        var cancelForm = document.getElementById('cancelRequestForm');
        var cancelId   = document.getElementById('cancelRequestId');

        document.querySelectorAll('[data-cancel-request]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-cancel-request');

                var msg = 'Cancel this collection request?\n\n' +
                          'You will need to submit a new request if you still need the collection.';
                if (!window.confirm(msg)) return;

                cancelId.value = id;
                cancelForm.submit();
            });
        });
    });
})();