<?php
/**
 * login.php
 * ---------------------------------------------------------------
 * Login page for all user roles.
 * Shows a session-expiry notice when redirected with ?timeout=1.
 * Redirects authenticated users to their dashboard.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_path_for(current_role()));
}

$timedOut  = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$prefill   = input_string('email', 'GET');   // Preserve email after failed login
$nextRaw   = input_string('next', 'GET');
$nextSafe  = '';

if ($nextRaw !== '') {
    // Only allow same-host internal redirects.
    $host = parse_url(BASE_URL, PHP_URL_HOST);
    $nextHost = parse_url($nextRaw, PHP_URL_HOST);
    if ($nextHost === null || $nextHost === $host) {
        $nextSafe = $nextRaw;
    }
}

$pageTitle = 'Log In';
$pageCss   = ['login.css'];
$pageJs    = ['login.js'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth">
    <div class="container auth__inner">
        <div class="auth__card card card--neumorphic">
            <h1 class="auth__title">Welcome back</h1>
            <p class="auth__subtitle">Log in to continue to TrashWise.</p>

            <?php if ($timedOut): ?>
                <div class="alert alert-warning" role="alert">
                    Your session expired after 30 minutes of inactivity. Please log in again.
                </div>
            <?php endif; ?>

            <form action="<?= e(BASE_URL) ?>/actions/authentication/login-action.php"
                  method="post" class="auth__form" id="loginForm" novalidate>
                <?= csrf_field() ?>
                <?php if ($nextSafe !== ''): ?>
                    <input type="hidden" name="next" value="<?= e($nextSafe) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           autocomplete="email" required maxlength="255"
                           value="<?= e($prefill) ?>">
                    <span class="form-error" data-error-for="email"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-wrap">
                        <input type="password" class="form-control" id="password"
                               name="password" autocomplete="current-password"
                               required minlength="6">
                        <button type="button" class="password-toggle"
                                data-target="password" aria-label="Show password">👁</button>
                    </div>
                    <span class="form-error" data-error-for="password"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg auth__submit">Log In</button>
            </form>

            <p class="auth__footer">
                Don't have an account?
                <a href="<?= e(BASE_URL) ?>/register.php">Register as a resident</a>
            </p>
        </div>

        <aside class="auth__aside" aria-hidden="true">
            <div class="auth__aside-card card">
                <h3>Why TrashWise?</h3>
                <ul>
                    <li>Report waste concerns in seconds.</li>
                    <li>Track status until resolution.</li>
                    <li>Request collections conveniently.</li>
                    <li>Stay updated with announcements.</li>
                </ul>
            </div>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>