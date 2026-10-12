/**
 * assets/js/edit-report.js
 * ---------------------------------------------------------------
 * Edit report behaviour:
 *   - Preview newly added photos with remove buttons.
 *   - Track how many existing photos are marked for removal.
 *   - Enforce: (existing - markedForRemoval) + newPhotos ≥ MIN_PHOTOS
 *   - Lock submit button while saving.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    var MAX_FILE_SIZE = 5 * 1024 * 1024;   // 5 MB
    var MIN_PHOTOS    = 2;                 // must match PHP constant
    var ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('editReportForm');
        if (!form) return;

        var existingCheckboxes = form.querySelectorAll('[data-existing-photo]');
        var newInput           = document.getElementById('newPhotos');
        var preview            = document.getElementById('newPhotoPreview');
        var summary            = document.getElementById('photoSummary');
        var submit             = document.getElementById('saveBtn');

        var existingCount = existingCheckboxes.length;
        var newFiles      = [];

        // ---------- Existing photo removal tracking ----------
        function countRemoved() {
            var n = 0;
            existingCheckboxes.forEach(function (cb) {
                if (cb.checked) n++;
            });
            return n;
        }

        function updateSummary() {
            var removed   = countRemoved();
            var remaining = existingCount - removed;
            var total     = remaining + newFiles.length;

            var text = '';
            if (total < MIN_PHOTOS) {
                text = total + ' of ' + MIN_PHOTOS + ' minimum photos. ' +
                       'Add ' + (MIN_PHOTOS - total) + ' more or keep an existing one.';
                summary.classList.add('is-error');
            } else {
                text = total + ' photo' + (total === 1 ? '' : 's') +
                       ' after saving.';
                summary.classList.remove('is-error');
            }
            summary.textContent = text;

            // Also show on the "new photos" legend counter.
            var hint = document.querySelector('.photo-fieldset:last-of-type .form-hint--inline');
            if (hint) {
                // leave static text alone; summary is the live counter
            }
        }

        existingCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', updateSummary);
        });

        // ---------- New photos preview ----------
        function renderPreview() {
            preview.innerHTML = '';
            newFiles.forEach(function (file, index) {
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
                    newFiles.splice(index, 1);
                    syncInput();
                    renderPreview();
                    updateSummary();
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
         * Sync newFiles back into the input via DataTransfer.
         * If DataTransfer is unsupported, the original input is left intact
         * (server-side validation still enforces the min-2 rule).
         */
        function syncInput() {
            try {
                var dt = new DataTransfer();
                newFiles.forEach(function (f) { dt.items.add(f); });
                newInput.files = dt.files;
            } catch (e) {
                console.warn('DataTransfer not supported; remove buttons will not persist.', e);
            }
        }

        newInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(newInput.files);
            for (var i = 0; i < files.length; i++) {
                var f = files[i];
                if (ALLOWED_MIMES.indexOf(f.type) === -1) {
                    alert('"' + f.name + '" is not a JPG, PNG, or WebP image.');
                    continue;
                }
                if (f.size > MAX_FILE_SIZE) {
                    alert('"' + f.name + '" exceeds the 5 MB limit.');
                    continue;
                }
                newFiles.push(f);
            }
            syncInput();
            renderPreview();
            updateSummary();
        });

        // ---------- Submit ----------
        form.addEventListener('submit', function (evt) {
            var remaining = existingCount - countRemoved();
            var total     = remaining + newFiles.length;

            if (total < MIN_PHOTOS) {
                evt.preventDefault();
                alert('At least ' + MIN_PHOTOS + ' photos must remain on the report.');
                updateSummary();
                return;
            }

            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
            submit.textContent = 'Saving…';
        });

        updateSummary();
    });
})();