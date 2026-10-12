/**
 * assets/js/manage-users.js
 * ---------------------------------------------------------------
 * Admin user list page:
 *   - Auto-submit filters on dropdown change.
 *   - Debounced search input.
 *   - Confirm user activate/deactivate.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Filters ----------
        var form = document.querySelector('.filters');
        if (form) {
            function resetPage() {
                var pageInput = form.querySelector('input[name="page"]');
                if (pageInput) pageInput.value = '1';
            }

            form.querySelectorAll('select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    resetPage();
                    form.submit();
                });
            });

            var searchInput = form.querySelector('input[name="q"]');
            if (searchInput) {
                var timer = null;
                searchInput.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        resetPage();
                        form.submit();
                    }, 450);
                });
            }
        }

        // ---------- Toggle user status ----------
        document.querySelectorAll('[data-toggle-user-form]').forEach(function (toggleForm) {
            toggleForm.addEventListener('submit', function (evt) {
                var btn = toggleForm.querySelector('[data-toggle-user-button]');
                if (!btn) return;

                var name   = btn.getAttribute('data-user-name') || 'this user';
                var action = btn.getAttribute('data-action') || 'change';

                var msg = action === 'deactivate'
                    ? 'Deactivate ' + name + '?\n\nThey will not be able to log in until reactivated.'
                    : 'Activate ' + name + '?\n\nThey will regain access to their account.';

                if (!window.confirm(msg)) {
                    evt.preventDefault();
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Working…';
            });
        });
    });
})();