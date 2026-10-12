/**
 * assets/js/waste-guide.js
 * ---------------------------------------------------------------
 * Waste guide list page:
 *   - Auto-submit filter form when category changes.
 *   - Debounced search input (submits ~400ms after typing stops).
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.filters');
        if (!form) return;

        // Auto-submit on category change.
        var categorySelect = form.querySelector('select[name="category_id"]');
        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                var pageInput = form.querySelector('input[name="page"]');
                if (pageInput) pageInput.value = '1';
                form.submit();
            });
        }

        // Debounced search input.
        var searchInput = form.querySelector('input[name="q"]');
        if (searchInput) {
            var timer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var pageInput = form.querySelector('input[name="page"]');
                    if (pageInput) pageInput.value = '1';
                    form.submit();
                }, 400);
            });
        }
    });
})();