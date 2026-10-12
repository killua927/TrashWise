<?php
/**
 * actions/authentication/login-action.php
 * ---------------------------------------------------------------
 * Handles the login form POST.
 *
 * Security:
 *   - CSRF verification
 *   - Prepared statements
 *   - password_verify()
 *   - Session ID regeneration (via login_user())
 *   - Generic error messages to prevent enumeration
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

verify_csrf();

$email    = input_string('email');
$password = (string) ($_POST['password'] ?? '');
$next     = input_string('next');

// Validate.
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    flash('error', 'Please enter a valid email and password.');
    $q = '?email=' . urlencode($email);
    if ($next !== '') $q .= '&next=' . urlencode($next);
    redirect('login.php' . $q);
}

// Look up the user.
$user = db_one("
    SELECT u.user_id, u.role_id, u.barangay_id, u.first_name, u.last_name,
           u.email, u.password_hash, u.is_active, r.role_name
      FROM users u
      JOIN roles r ON u.role_id = r.role_id
     WHERE u.email = ?
     LIMIT 1
", [$email]);

// Constant-time-ish check: always run password_verify if a user was found.
$valid = false;
if ($user) {
    $valid = password_verify($password, $user['password_hash']);
}

if (!$user || !$valid) {
    flash('error', 'Invalid email or password.');
    $q = '?email=' . urlencode($email);
    if ($next !== '') $q .= '&next=' . urlencode($next);
    redirect('login.php' . $q);
}

if ((int) $user['is_active'] !== 1) {
    flash('error', 'This account has been deactivated. Please contact an administrator.');
    redirect('login.php');
}

// Success.
login_user($user);

flash('success', 'Welcome back, ' . $user['first_name'] . '!');

// Safe redirect: only allow same-host internal paths.
if ($next !== '') {
    $host = parse_url(BASE_URL, PHP_URL_HOST);
    $nextHost = parse_url($next, PHP_URL_HOST);
    if ($nextHost === null || $nextHost === $host) {
        redirect($next);
    }
}

redirect(dashboard_path_for($user['role_name']));