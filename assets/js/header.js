/**
 * assets/js/header.js
 * ---------------------------------------------------------------
 * Header behaviour:
 *   - Mobile navigation toggle.
 *   - Admin dropdown open/close.
 *   - Active link highlighting.
 *   - Close on Escape / outside click.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('navToggleBtn');
        var nav    = document.getElementById('primaryNav');

        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                var isOpen = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            document.addEventListener('click', function (evt) {
                if (!nav.classList.contains('is-open')) return;
                if (nav.contains(evt.target) || toggle.contains(evt.target)) return;
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });

            document.addEventListener('keydown', function (evt) {
                if (evt.key === 'Escape' && nav.classList.contains('is-open')) {
                    nav.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        }

        // Admin dropdown.
        document.querySelectorAll('.app-nav__dropdown').forEach(function (dd) {
            var btn = dd.querySelector('.app-nav__link--button');
            if (!btn) return;

            btn.addEventListener('click', function (evt) {
                evt.stopPropagation();
                var open = dd.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('click', function (evt) {
                if (!dd.contains(evt.target)) {
                    dd.classList.remove('is-open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', function (evt) {
                if (evt.key === 'Escape') {
                    dd.classList.remove('is-open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });

        // Highlight active link.
        var path = window.location.pathname;
        document.querySelectorAll('.app-nav__link').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            if (!href || href === '#') return;
            var last = href.split('/').pop();
            if (last && path.endsWith(last)) {
                link.setAttribute('aria-current', 'page');
                link.style.fontWeight = '700';
                link.style.color = 'var(--tw-primary)';
            }
        });
    });
})();