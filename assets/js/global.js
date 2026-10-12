/**
 * assets/js/global.js
 * ---------------------------------------------------------------
 * Global UI behaviour:
 *   - Auto-dismiss flash messages.
 *   - Session timeout warning modal at 28 min of inactivity.
 *   - Confirmation dialog helper for data-confirm elements.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    // -------------------------------------------------------------
    // Flash messages
    // -------------------------------------------------------------
    function dismissFlash(el) {
        if (!el || !el.parentNode) return;
        el.style.transition = 'opacity 200ms ease, transform 200ms ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 220);
    }

    function initFlashMessages() {
        document.querySelectorAll('[data-flash]').forEach(function (flash) {
            var closeBtn = flash.querySelector('.flash__close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { dismissFlash(flash); });
            }
            setTimeout(function () { dismissFlash(flash); }, 6000);
        });
    }

    // -------------------------------------------------------------
    // Session timeout warning
    // -------------------------------------------------------------
    var SESSION_TIMEOUT_MS = 30 * 60 * 1000;
    var WARNING_AT_MS      = 28 * 60 * 1000;
    var lastActivity       = Date.now();
    var warningShown       = false;

    function trackActivity() {
        lastActivity = Date.now();
        if (warningShown) {
            var modal = document.getElementById('sessionWarning');
            if (modal) {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
            }
            warningShown = false;
        }
    }

    ['click', 'keypress', 'mousemove', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, trackActivity, { passive: true });
    });

    function showSessionWarning() {
        var modal = document.getElementById('sessionWarning');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'sessionWarning';
            modal.className = 'session-warning';
            modal.setAttribute('role', 'alertdialog');
            modal.setAttribute('aria-live', 'assertive');
            modal.innerHTML =
                '<div class="session-warning__box">' +
                '<h3>Session expiring soon</h3>' +
                '<p>You have been inactive for a while. ' +
                'You will be logged out automatically if you do not continue.</p>' +
                '<button type="button" class="btn btn-primary" id="sessionContinue">Continue Session</button>' +
                '</div>';
            document.body.appendChild(modal);
            modal.querySelector('#sessionContinue').addEventListener('click', trackActivity);
        }
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }

    function checkIdle() {
        var idle = Date.now() - lastActivity;
        if (idle >= WARNING_AT_MS && idle < SESSION_TIMEOUT_MS && !warningShown) {
            showSessionWarning();
            warningShown = true;
        }
        if (idle >= SESSION_TIMEOUT_MS) {
            // Reload so the next server request enforces timeout.
            window.location.reload();
        }
    }

    // Only run the timeout check when a logged-in user is on the page.
    if (document.body.classList.contains('is-authenticated')) {
        setInterval(checkIdle, 30000);
    }

    // -------------------------------------------------------------
    // Confirmation dialog helper
    // -------------------------------------------------------------
    document.addEventListener('click', function (evt) {
        var target = evt.target.closest('[data-confirm]');
        if (!target) return;
        var msg = target.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) {
            evt.preventDefault();
            evt.stopPropagation();
        }
    });

    // -------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', initFlashMessages);
})();