/**
 * assets/js/manage-reports.js
 * ---------------------------------------------------------------
 * Admin Manage Reports page:
 *   - Auto-submit filter form when a dropdown changes.
 *   - Debounced search input (submits ~450ms after typing stops).
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.filters');
        if (!form) return;

        // Reset page on any filter change.
        function resetPage() {
            var pageInput = form.querySelector('input[name="page"]');
            if (pageInput) pageInput.value = '1';
        }

        // Auto-submit selects.
        form.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                resetPage();
                form.submit();
            });
        });

        // Debounced search input.
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
    });
})();