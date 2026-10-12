/**
 * assets/js/request-collection.js
 * ---------------------------------------------------------------
 * Collection request form:
 *   - Client-side validation for fast feedback.
 *   - Prevent past preferred_date.
 *   - Lock submit button to prevent double POST.
 *
 * Server-side validation remains authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('collectionRequestForm');
        if (!form) return;

        var submit = document.getElementById('submitRequestBtn');

        function showError(name, message) {
            var el = form.querySelector('[data-error-for="' + name + '"]');
            var field = form.querySelector('[name="' + name + '"]');
            if (el) el.textContent = message || '';
            if (field) field.classList.toggle('is-invalid', Boolean(message));
        }

        form.addEventListener('submit', function (evt) {
            var ok = true;

            if (!form.category_id.value) {
                showError('category_id', 'Please select a waste category.');
                ok = false;
            } else showError('category_id', '');

            if (!form.barangay_id.value) {
                showError('barangay_id', 'Please select a barangay.');
                ok = false;
            } else showError('barangay_id', '');

            if (!form.location.value.trim()) {
                showError('location', 'Location is required.');
                ok = false;
            } else showError('location', '');

            var desc = form.description.value.trim();
            if (!desc) {
                showError('description', 'Description is required.');
                ok = false;
            } else if (desc.length < 10) {
                showError('description', 'Please provide at least 10 characters.');
                ok = false;
            } else showError('description', '');

            var dateVal = form.preferred_date.value;
            if (dateVal) {
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var chosen = new Date(dateVal + 'T00:00:00');
                if (chosen < today) {
                    showError('preferred_date', 'Preferred date cannot be in the past.');
                    ok = false;
                } else showError('preferred_date', '');
            } else showError('preferred_date', '');

            if (!ok) {
                evt.preventDefault();
                return;
            }

            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
            submit.textContent = 'Submitting…';
        });

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('input', function () {
                showError(el.name, '');
            });
        });
    });
})();