/**
 * assets/js/login.js
 * ---------------------------------------------------------------
 * Login form behaviour:
 *   - Password visibility toggle.
 *   - Client-side validation for faster feedback.
 *   - Server-side validation remains authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('loginForm');
        if (!form) return;

        form.querySelectorAll('.password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                if (!input) return;
                var isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                btn.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
                btn.textContent = isPwd ? '🙈' : '👁';
            });
        });

        function showError(name, message) {
            var el = form.querySelector('[data-error-for="' + name + '"]');
            var input = form.querySelector('[name="' + name + '"]');
            if (el) el.textContent = message || '';
            if (input) input.classList.toggle('is-invalid', Boolean(message));
        }

        form.addEventListener('submit', function (evt) {
            var ok = true;
            var email = form.email.value.trim();
            var pwd   = form.password.value;

            if (!email) { showError('email', 'Email is required.'); ok = false; }
            else if (!/^\S+@\S+\.\S+$/.test(email)) { showError('email', 'Enter a valid email address.'); ok = false; }
            else showError('email', '');

            if (!pwd) { showError('password', 'Password is required.'); ok = false; }
            else if (pwd.length < 6) { showError('password', 'Password must be at least 6 characters.'); ok = false; }
            else showError('password', '');

            if (!ok) evt.preventDefault();
        });

        form.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('input', function () { showError(input.name, ''); });
        });
    });
})();