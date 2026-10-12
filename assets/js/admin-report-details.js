/**
 * assets/js/admin-report-details.js
 * ---------------------------------------------------------------
 * Admin report detail page:
 *   - Photo lightbox with keyboard navigation.
 *   - Confirm status change before submit.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Lightbox ----------
        var gallery   = document.getElementById('photoGallery');
        var lightbox  = document.getElementById('lightbox');
        var lbImg     = lightbox ? lightbox.querySelector('.lightbox__img') : null;
        var lbCaption = lightbox ? lightbox.querySelector('.lightbox__caption') : null;
        var btnPrev   = lightbox ? lightbox.querySelector('.lightbox__nav--prev') : null;
        var btnNext   = lightbox ? lightbox.querySelector('.lightbox__nav--next') : null;
        var btnClose  = lightbox ? lightbox.querySelector('.lightbox__close') : null;

        var photos  = [];
        var current = 0;

        if (gallery && lightbox) {
            photos = Array.prototype.map.call(
                gallery.querySelectorAll('.photo-gallery__item'),
                function (item) {
                    return {
                        src:  item.getAttribute('data-photo-src'),
                        name: item.getAttribute('data-photo-name'),
                    };
                }
            );

            function renderLightbox() {
                if (!photos.length) return;
                var p = photos[current];
                lbImg.src = p.src;
                lbImg.alt = p.name;
                lbCaption.textContent = p.name + ' — ' + (current + 1) + ' / ' + photos.length;
            }
            function openLightbox(index) {
                current = index;
                renderLightbox();
                lightbox.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                btnClose.focus();
            }
            function closeLightbox() {
                lightbox.classList.add('hidden');
                document.body.style.overflow = '';
            }
            function next() { current = (current + 1) % photos.length; renderLightbox(); }
            function prev() { current = (current - 1 + photos.length) % photos.length; renderLightbox(); }

            gallery.querySelectorAll('.photo-gallery__item').forEach(function (item) {
                item.addEventListener('click', function () {
                    var idx = parseInt(item.getAttribute('data-photo-index'), 10) || 0;
                    openLightbox(idx);
                });
            });

            btnClose.addEventListener('click', closeLightbox);
            if (photos.length > 1) {
                btnPrev.addEventListener('click', prev);
                btnNext.addEventListener('click', next);
            } else {
                btnPrev.style.display = 'none';
                btnNext.style.display = 'none';
            }

            lightbox.addEventListener('click', function (evt) {
                if (evt.target === lightbox) closeLightbox();
            });

            document.addEventListener('keydown', function (evt) {
                if (lightbox.classList.contains('hidden')) return;
                if (evt.key === 'Escape')     closeLightbox();
                if (evt.key === 'ArrowRight' && photos.length > 1) next();
                if (evt.key === 'ArrowLeft'  && photos.length > 1) prev();
            });
        }

        // ---------- Status form confirmation ----------
        var statusForm = document.getElementById('statusForm');
        if (statusForm) {
            var updateBtn = document.getElementById('updateStatusBtn');
            statusForm.addEventListener('submit', function (evt) {
                var sel = statusForm.querySelector('#new_status_id');
                if (!sel || !sel.value) {
                    evt.preventDefault();
                    alert('Please select a new status.');
                    return;
                }
                var label = sel.options[sel.selectedIndex].text;
                if (!window.confirm('Change status to "' + label + '"?\n\n' +
                                    'The resident will be notified.')) {
                    evt.preventDefault();
                    return;
                }
                updateBtn.disabled = true;
                updateBtn.textContent = 'Updating…';
            });
        }
    });
})();