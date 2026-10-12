/**
 * assets/js/index.js
 * ---------------------------------------------------------------
 * Landing page interactions:
 *   - Smooth scroll for in-page anchors.
 *   - Reveal-on-scroll animation with IntersectionObserver.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (evt) {
                var target = document.querySelector(link.getAttribute('href'));
                if (!target) return;
                evt.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if ('IntersectionObserver' in window) {
            var style = document.createElement('style');
            style.textContent = '.is-visible{opacity:1 !important;transform:none !important;}';
            document.head.appendChild(style);

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('.feature-card, .steps__item').forEach(function (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(12px)';
                el.style.transition = 'opacity 400ms ease, transform 400ms ease';
                observer.observe(el);
            });
        }
    });
})();