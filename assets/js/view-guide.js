/**
 * assets/js/view-guide.js
 * ---------------------------------------------------------------
 * Waste guide detail page:
 *   - Top reading progress bar while scrolling through the guide.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var article = document.querySelector('.guide-view__content');
        if (!article) return;

        var bar = document.createElement('div');
        bar.className = 'reading-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        function update() {
            var rect = article.getBoundingClientRect();
            var total = rect.height - window.innerHeight;
            var scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 0));
            var pct = total > 0 ? (scrolled / total) * 100 : 0;
            bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
        }

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    });
})();