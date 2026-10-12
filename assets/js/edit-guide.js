/**
 * assets/js/edit-guide.js
 * ---------------------------------------------------------------
 * Guide create/edit form validation. Server-side validation
 * remains authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('editGuideForm');
        if (!form) return;

        var submit = document.getElementById('saveGuideBtn');

        function showError(name, message) {
            var el = form.querySelector('[data-error-for="' + name + '"]');
            var input = form.querySelector('[name="' + name + '"]');
            if (el) el.textContent = message || '';
            if (input) input.classList.toggle('is-invalid', Boolean(message));
        }

        form.addEventListener('submit', function (evt) {
            var ok = true;

            if (!form.category_id.value) {
                showError('category_id', 'Please select a category.');
                ok = false;
            } else showError('category_id', '');

            if (!form.title.value.trim()) {
                showError('title', 'Title is required.');
                ok = false;
            } else showError('title', '');

            var content = form.content.value.trim();
            if (!content) {
                showError('content', 'Content is required.');
                ok = false;
            } else if (content.length < 20) {
                showError('content', 'Please provide at least 20 characters of content.');
                ok = false;
            } else showError('content', '');

            if (!ok) {
                evt.preventDefault();
                return;
            }

            submit.disabled = true;
            submit.textContent = 'Saving…';
        });

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('input', function () {
                showError(el.name, '');
            });
        });
    });
})();