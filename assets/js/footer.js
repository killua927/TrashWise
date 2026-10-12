/**
 * assets/js/footer.js
 * ---------------------------------------------------------------
 * Footer behaviour. Currently only updates the copyright year
 * dynamically if a placeholder is present.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var yearEl = document.querySelector('[data-current-year]');
        if (yearEl) {
            yearEl.textContent = new Date().getFullYear();
        }
    });
})();