/**
 * assets/js/manage-guides.js
 * ---------------------------------------------------------------
 * Admin guide management:
 *   - Auto-submit filters on dropdown change.
 *   - Debounced search input.
 *   - Confirm publish/unpublish.
 *   - Confirm delete with irreversibility warning.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Filters ----------
        var form = document.querySelector('.filters');
        if (form) {
            function resetPage() {
                var p = form.querySelector('input[name="page"]');
                if (p) p.value = '1';
            }
            form.querySelectorAll('select').forEach(function (sel) {
                sel.addEventListener('change', function () { resetPage(); form.submit(); });
            });
            var searchInput = form.querySelector('input[name="q"]');
            if (searchInput) {
                var timer = null;
                searchInput.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () { resetPage(); form.submit(); }, 450);
                });
            }
        }

        // ---------- Publish/unpublish confirmation ----------
        document.querySelectorAll('[data-toggle-guide-form]').forEach(function (tForm) {
            tForm.addEventListener('submit', function (evt) {
                var btn = tForm.querySelector('[data-toggle-guide-button]');
                if (!btn) return;

                var title  = btn.getAttribute('data-guide-title') || 'this guide';
                var action = btn.getAttribute('data-action') || 'change';

                var msg = action === 'publish'
                    ? 'Publish "' + title + '"?\n\nResidents will be able to see this guide.'
                    : 'Unpublish "' + title + '"?\n\nResidents will no longer see this guide.';

                if (!window.confirm(msg)) {
                    evt.preventDefault();
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Working…';
            });
        });

        // ---------- Delete confirmation ----------
        document.querySelectorAll('[data-delete-guide-form]').forEach(function (dForm) {
            dForm.addEventListener('submit', function (evt) {
                var btn = dForm.querySelector('[data-delete-guide-button]');
                if (!btn) return;

                var title = btn.getAttribute('data-guide-title') || 'this guide';

                var msg = 'Delete "' + title + '"?\n\n' +
                          'This permanently removes the guide. This action cannot be undone.';

                if (!window.confirm(msg)) {
                    evt.preventDefault();
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Deleting…';
            });
        });
    });
})();