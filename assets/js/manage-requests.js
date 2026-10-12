/**
 * assets/js/manage-requests.js
 * ---------------------------------------------------------------
 * Admin Manage Requests page:
 *   - Expand/collapse inline details.
 *   - Auto-submit filter dropdowns.
 *   - Debounced search input.
 *   - Confirm inline status/assignment updates.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Detail toggle ----------
        document.querySelectorAll('[data-toggle-admin-details]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id     = btn.getAttribute('data-toggle-admin-details');
                var target = document.getElementById('admin-request-details-' + id);
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

        // ---------- Inline update confirmation ----------
        document.querySelectorAll('[data-update-request-form]').forEach(function (updateForm) {
            updateForm.addEventListener('submit', function (evt) {
                var statusSel   = updateForm.querySelector('select[name="new_status_id"]');
                var assignedSel = updateForm.querySelector('select[name="assigned_to"]');

                var newStatus = statusSel ? statusSel.value : '';
                var newAssign = assignedSel ? assignedSel.value : '';

                if (!newStatus && !newAssign) {
                    evt.preventDefault();
                    window.alert('Choose a new status or change the assigned staff before saving.');
                    return;
                }

                var msg = 'Save this change?';
                if (newStatus) {
                    var label = statusSel.options[statusSel.selectedIndex].text;
                    msg += '\n• Status → ' + label;
                }
                if (assignedSel) {
                    var staffLabel = assignedSel.options[assignedSel.selectedIndex].text;
                    msg += '\n• Assigned → ' + staffLabel;
                }
                msg += '\n\nThe resident will be notified if the status changes.';

                if (!window.confirm(msg)) {
                    evt.preventDefault();
                    return;
                }

                var btn = updateForm.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
            });
        });
    });
})();