/**
 * assets/js/profile.js
 * ---------------------------------------------------------------
 * Profile page validation:
 *   - Profile form: required fields + phone pattern.
 *   - Password form: current required, new ≥ 8 chars, new == confirm.
 *
 * Server-side validation remains authoritative.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // -------------------------------------------------------------
        // Profile form
        // -------------------------------------------------------------
        var profileForm = document.getElementById('profileForm');
        if (profileForm) {
            var saveBtn = document.getElementById('saveProfileBtn');

            function showErr(form, name, message) {
                var el = form.querySelector('[data-error-for="' + name + '"]');
                var input = form.querySelector('[name="' + name + '"]');
                if (el) el.textContent = message || '';
                if (input) input.classList.toggle('is-invalid', Boolean(message));
            }

            profileForm.addEventListener('submit', function (evt) {
                var ok = true;

                if (!profileForm.first_name.value.trim()) {
                    showErr(profileForm, 'first_name', 'First name is required.');
                    ok = false;
                } else showErr(profileForm, 'first_name', '');

                if (!profileForm.last_name.value.trim()) {
                    showErr(profileForm, 'last_name', 'Last name is required.');
                    ok = false;
                } else showErr(profileForm, 'last_name', '');

                if (!profileForm.barangay_id.value) {
                    showErr(profileForm, 'barangay_id', 'Please select a barangay.');
                    ok = false;
                } else showErr(profileForm, 'barangay_id', '');

                var phone = profileForm.phone.value.trim();
                if (phone && !/^[0-9+\-() ]{6,20}$/.test(phone)) {
                    showErr(profileForm, 'phone', 'Enter a valid phone number.');
                    ok = false;
                } else showErr(profileForm, 'phone', '');

                if (!ok) {
                    evt.preventDefault();
                    return;
                }
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';
            });

            profileForm.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.addEventListener('input', function () {
                    showErr(profileForm, el.name, '');
                });
            });
        }

        // -------------------------------------------------------------
        // Password form
        // -------------------------------------------------------------
        var pwdForm = document.getElementById('passwordForm');
        if (pwdForm) {
            var pwdBtn = document.getElementById('savePasswordBtn');

            function showPwdErr(name, message) {
                var el = pwdForm.querySelector('[data-error-for="' + name + '"]');
                var input = pwdForm.querySelector('[name="' + name + '"]');
                if (el) el.textContent = message || '';
                if (input) input.classList.toggle('is-invalid', Boolean(message));
            }

            pwdForm.addEventListener('submit', function (evt) {
                var ok = true;

                if (!pwdForm.current_password.value) {
                    showPwdErr('current_password', 'Current password is required.');
                    ok = false;
                } else showPwdErr('current_password', '');

                var np = pwdForm.new_password.value;
                if (!np) {
                    showPwdErr('new_password', 'New password is required.');
                    ok = false;
                } else if (np.length < 8) {
                    showPwdErr('new_password', 'Password must be at least 8 characters.');
                    ok = false;
                } else showPwdErr('new_password', '');

                if (!pwdForm.confirm_password.value) {
                    showPwdErr('confirm_password', 'Please confirm your new password.');
                    ok = false;
                } else if (pwdForm.confirm_password.value !== np) {
                    showPwdErr('confirm_password', 'Passwords do not match.');
                    ok = false;
                } else showPwdErr('confirm_password', '');

                if (!ok) {
                    evt.preventDefault();
                    return;
                }
                pwdBtn.disabled = true;
                pwdBtn.textContent = 'Updating…';
            });

            pwdForm.querySelectorAll('input').forEach(function (el) {
                el.addEventListener('input', function () {
                    showPwdErr(el.name, '');
                });
            });
        }
    });
})();