/**
 * assets/js/my-reports.js
 * ---------------------------------------------------------------
 * My Reports page behaviour:
 *   - Delete confirmation dialog (submits the hidden delete form).
 *   - Auto-submit filter form when a dropdown changes.
 *   - Preserve search text on form submit.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Delete confirmation ----------
        var deleteForm = document.getElementById('deleteReportForm');
        var deleteId   = document.getElementById('deleteReportId');

        document.querySelectorAll('[data-delete-report]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id    = btn.getAttribute('data-delete-report');
                var title = btn.getAttribute('data-delete-title') || 'this report';

                var msg = 'Delete "' + title + '"?\n\n' +
                          'This action cannot be undone. Any uploaded photos will also be removed.';

                if (!window.confirm(msg)) return;

                deleteId.value = id;
                deleteForm.submit();
            });
        });

        // ---------- Filter auto-submit ----------
        var filterForm = document.querySelector('.filters');
        if (filterForm) {
            filterForm.querySelectorAll('select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    // Reset to first page when filters change.
                    var pageInput = filterForm.querySelector('input[name="page"]');
                    if (pageInput) pageInput.value = '1';
                    filterForm.submit();
                });
            });
        }
    });
})();