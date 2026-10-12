/**
 * assets/js/report-waste.js
 * ---------------------------------------------------------------
 * Waste report submission form:
 *   - Live photo preview with remove buttons
 *   - Minimum 2 photos validation
 *   - Per-file size and type check
 *   - Field validation for faster feedback
 *   - Submit-button lock to prevent double submission
 *
 * Server-side validation remains authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    var MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    var MIN_PHOTOS    = 2;
    var ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    document.addEventListener('DOMContentLoaded', function () {
        var form    = document.getElementById('reportForm');
        if (!form) return;

        var input   = document.getElementById('photos');
        var preview = document.getElementById('photoPreview');
        var submit  = document.getElementById('submitBtn');

        // Keep a live list of selected files (File objects).
        var selectedFiles = [];

        // ---------- Helpers ----------
        function showError(name, message) {
            var el = form.querySelector('[data-error-for="' + name + '"]');
            var field = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
            if (el) el.textContent = message || '';
            if (field) field.classList.toggle('is-invalid', Boolean(message));
        }

        function updateCounter() {
            var counter = document.getElementById('photoCounter');
            if (!counter) {
                counter = document.createElement('p');
                counter.id = 'photoCounter';
                counter.className = 'photo-counter';
                preview.parentNode.appendChild(counter);
            }

            var n = selectedFiles.length;
            if (n === 0) {
                counter.textContent = 'No photos selected yet.';
                counter.classList.remove('photo-counter--error');
            } else if (n < MIN_PHOTOS) {
                counter.textContent = n + ' of ' + MIN_PHOTOS + ' minimum photos selected.';
                counter.classList.add('photo-counter--error');
            } else {
                counter.textContent = n + ' photo' + (n === 1 ? '' : 's') + ' ready to upload.';
                counter.classList.remove('photo-counter--error');
            }
        }

        function renderPreview() {
            preview.innerHTML = '';
            selectedFiles.forEach(function (file, index) {
                var item = document.createElement('div');
                item.className = 'photo-item';

                var img = document.createElement('img');
                img.className = 'photo-item__img';
                img.alt = file.name;
                img.src = URL.createObjectURL(file);

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'photo-item__remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    selectedFiles.splice(index, 1);
                    syncInput();
                    renderPreview();
                    updateCounter();
                });

                var name = document.createElement('span');
                name.className = 'photo-item__name';
                name.textContent = file.name;

                item.appendChild(img);
                item.appendChild(remove);
                item.appendChild(name);
                preview.appendChild(item);
            });
        }

        /**
         * Sync our in-memory selectedFiles back into the <input> using
         * DataTransfer so the browser submits exactly what we kept.
         */
        function syncInput() {
            try {
                var dt = new DataTransfer();
                selectedFiles.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            } catch (e) {
                // DataTransfer not supported — fall back to leaving the
                // original input intact. Remove buttons will not persist,
                // but server-side validation still protects the request.
                console.warn('DataTransfer not supported; file removal will not persist.', e);
            }
        }

        // ---------- File input ----------
        input.addEventListener('change', function () {
            var newFiles = Array.prototype.slice.call(input.files);

            for (var i = 0; i < newFiles.length; i++) {
                var f = newFiles[i];

                if (ALLOWED_MIMES.indexOf(f.type) === -1) {
                    showError('photos', '"' + f.name + '" is not a JPG, PNG, or WebP image.');
                    continue;
                }
                if (f.size > MAX_FILE_SIZE) {
                    showError('photos', '"' + f.name + '" exceeds the 5 MB limit.');
                    continue;
                }
                selectedFiles.push(f);
            }

            showError('photos', '');
            syncInput();
            renderPreview();
            updateCounter();
        });

        // ---------- Submit ----------
        form.addEventListener('submit', function (evt) {
            var ok = true;

            if (!form.title.value.trim()) {
                showError('title', 'Title is required.');
                ok = false;
            } else showError('title', '');

            if (!form.category_id.value) {
                showError('category_id', 'Please select a waste category.');
                ok = false;
            } else showError('category_id', '');

            if (!form.concern_type_id.value) {
                showError('concern_type_id', 'Please select a concern type.');
                ok = false;
            } else showError('concern_type_id', '');

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
                showError('description', 'Please describe the concern in at least 10 characters.');
                ok = false;
            } else showError('description', '');

            if (selectedFiles.length < MIN_PHOTOS) {
                showError('photos', 'At least ' + MIN_PHOTOS + ' photos are required.');
                ok = false;
            } else showError('photos', '');

            if (!ok) {
                evt.preventDefault();
                return;
            }

            // Prevent double submission.
            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
            submit.textContent = 'Submitting…';
        });

        // Clear individual errors on input change.
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('input', function () {
                showError(el.name.replace('[]', ''), '');
            });
        });

        // Init counter on page load.
        updateCounter();
    });
})();