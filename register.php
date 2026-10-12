<?php
/**
 * register.php
 * ---------------------------------------------------------------
 * Resident registration.
 * Auto-activates the account (no Admin approval).
 * Email verification columns are present but left unverified
 * until OTP verification is implemented.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_path_for(current_role()));
}

$barangays = get_barangays();

// Preserve submitted values on validation failure.
$old = $_SESSION['old_register'] ?? [];
unset($_SESSION['old_register']);

$pageTitle = 'Register';
$pageCss   = ['register.css'];
$pageJs    = ['register.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth">
    <div class="container auth__inner">
        <div class="auth__card card card--neumorphic">
            <h1 class="auth__title">Create a resident account</h1>
            <p class="auth__subtitle">Register to report waste concerns and request collection.</p>

            <form action="<?= e(BASE_URL) ?>/actions/authentication/register-action.php"
                  method="post" class="auth__form" id="registerForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                               maxlength="100" required value="<?= e($old['first_name'] ?? '') ?>">
                        <span class="form-error" data-error-for="first_name"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                               maxlength="100" required value="<?= e($old['last_name'] ?? '') ?>">
                        <span class="form-error" data-error-for="last_name"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           autocomplete="email" maxlength="255" required
                           value="<?= e($old['email'] ?? '') ?>">
                    <span class="form-error" data-error-for="email"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="barangay_id">Barangay <span class="required">*</span></label>
                    <select class="form-select" id="barangay_id" name="barangay_id" required>
                        <option value="">Select your barangay</option>
                        <?php foreach ($barangays as $b): ?>
                            <option value="<?= (int) $b['barangay_id'] ?>"
                                <?= (isset($old['barangay_id']) && (int) $old['barangay_id'] === (int) $b['barangay_id']) ? 'selected' : '' ?>>
                                <?= e($b['barangay_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" data-error-for="barangay_id"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="8" required autocomplete="new-password">
                        <span class="form-hint">Minimum 8 characters.</span>
                        <span class="form-error" data-error-for="password"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               minlength="8" required autocomplete="new-password">
                        <span class="form-error" data-error-for="confirm_password"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone (optional)</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           maxlength="20" pattern="[0-9+\-() ]*" value="<?= e($old['phone'] ?? '') ?>">
                    <span class="form-error" data-error-for="phone"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address (optional)</label>
                    <input type="text" class="form-control" id="address" name="address"
                           maxlength="500" value="<?= e($old['address'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-lg auth__submit">Create Account</button>
            </form>

            <p class="auth__footer">
                Already have an account? <a href="<?= e(BASE_URL) ?>/login.php">Log in</a>
            </p>
        </div>

        <aside class="auth__aside" aria-hidden="true">
            <div class="auth__aside-card card">
                <h3>Getting started</h3>
                <p class="auth__aside-text">
                    After registering, you can immediately submit waste reports,
                    request collections, and track every update through notifications.
                </p>
            </div>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>