/**
 * assets/js/edit-user.js
 * ---------------------------------------------------------------
 * User create/edit form validation. Server-side validation remains
 * authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('editUserForm');
        if (!form) return;

        var submit = document.getElementById('saveUserBtn');
        var isEdit = !!form.querySelector('input[name="user_id"]');

        function showError(name, message) {
            var el = form.querySelector('[data-error-for="' + name + '"]');
            var input = form.querySelector('[name="' + name + '"]');
            if (el) el.textContent = message || '';
            if (input) input.classList.toggle('is-invalid', Boolean(message));
        }

        form.addEventListener('submit', function (evt) {
            var ok = true;

            if (!form.first_name.value.trim()) {
                showError('first_name', 'First name is required.');
                ok = false;
            } else showError('first_name', '');

            if (!form.last_name.value.trim()) {
                showError('last_name', 'Last name is required.');
                ok = false;
            } else showError('last_name', '');

            var email = form.email.value.trim();
            if (!email) {
                showError('email', 'Email is required.');
                ok = false;
            } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                showError('email', 'Enter a valid email address.');
                ok = false;
            } else showError('email', '');

            if (!isEdit) {
                var pwd = form.password.value;
                if (!pwd) {
                    showError('password', 'Password is required.');
                    ok = false;
                } else if (pwd.length < 8) {
                    showError('password', 'Minimum 8 characters.');
                    ok = false;
                } else showError('password', '');

                if (!form.confirm_password.value) {
                    showError('confirm_password', 'Please confirm the password.');
                    ok = false;
                } else if (form.confirm_password.value !== pwd) {
                    showError('confirm_password', 'Passwords do not match.');
                    ok = false;
                } else showError('confirm_password', '');
            }

            if (!form.role_id.value) {
                showError('role_id', 'Please select a role.');
                ok = false;
            } else showError('role_id', '');

            var phone = form.phone.value.trim();
            if (phone && !/^[0-9+\-() ]{6,20}$/.test(phone)) {
                showError('phone', 'Enter a valid phone number.');
                ok = false;
            } else showError('phone', '');

            if (!ok) {
                evt.preventDefault();
                return;
            }

            submit.disabled = true;
            submit.textContent = isEdit ? 'Saving…' : 'Creating…';
        });

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('input', function () {
                showError(el.name, '');
            });
        });
    });
})();