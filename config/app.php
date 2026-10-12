<?php
/**
 * config/app.php
 * ---------------------------------------------------------------------------
 * Global application configuration for TrashWise.
 *
 * Responsibilities:
 *   - Environment and debug flags.
 *   - Base URL and filesystem paths.
 *   - Upload rules (photo evidence).
 *   - Role constants.
 *   - Report and request status constants.
 *   - Session bootstrap (secure cookies, HttpOnly, SameSite).
 *   - CSRF token generation and verification.
 *   - Error display policy.
 *
 * Notes:
 *   - This file must be included BEFORE config/database.php.
 *   - It is idempotent: multiple includes will not restart the session
 *     or redefine constants.
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

// -------------------------------------------------------------------------
// Environment
// -------------------------------------------------------------------------
defined('APP_ENV')   || define('APP_ENV',   getenv('TRASHWISE_ENV') ?: 'development');
defined('APP_DEBUG') || define('APP_DEBUG', APP_ENV === 'development');

// -------------------------------------------------------------------------
// Base URL and filesystem paths
// -------------------------------------------------------------------------
// Automatically detect the base URL if not set. Works under XAMPP htdocs.
if (!defined('BASE_URL')) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Trim trailing "/config" or "/includes" if the file is called directly.
    $script  = preg_replace('#/(config|includes|actions|modules|assets)(/.*)?$#', '', $script);
    $script  = rtrim($script, '/');
    define('BASE_URL', $scheme . '://' . $host . $script);
}

defined('APP_ROOT')   || define('APP_ROOT', dirname(__DIR__));
defined('UPLOAD_DIR') || define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports');
defined('UPLOAD_URL') || define('UPLOAD_URL', BASE_URL . '/uploads/reports');

// -------------------------------------------------------------------------
// Upload rules (photo evidence per report)
// -------------------------------------------------------------------------
defined('MIN_PHOTOS_PER_REPORT') || define('MIN_PHOTOS_PER_REPORT', 2);
defined('MAX_PHOTOS_PER_REPORT') || define('MAX_PHOTOS_PER_REPORT', 20);
defined('MAX_PHOTO_SIZE')        || define('MAX_PHOTO_SIZE', 5 * 1024 * 1024);
defined('ALLOWED_IMAGE_MIMES')   || define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);

// -------------------------------------------------------------------------
// Session
// -------------------------------------------------------------------------
defined('SESSION_NAME')    || define('SESSION_NAME', 'TRASHWISE_SID');
defined('SESSION_TIMEOUT') || define('SESSION_TIMEOUT', 1800); // 30 minutes
defined('SESSION_WARNING') || define('SESSION_WARNING', 1680); // 28 minutes

// -------------------------------------------------------------------------
// Roles — must match roles.role_name in the database
// -------------------------------------------------------------------------
defined('ROLE_ADMIN')    || define('ROLE_ADMIN',    'Admin');
defined('ROLE_STAFF')    || define('ROLE_STAFF',    'Staff');
defined('ROLE_RESIDENT') || define('ROLE_RESIDENT', 'Resident');

defined('ROLE_ID_ADMIN')    || define('ROLE_ID_ADMIN',    1);
defined('ROLE_ID_STAFF')    || define('ROLE_ID_STAFF',    2);
defined('ROLE_ID_RESIDENT') || define('ROLE_ID_RESIDENT', 3);

// -------------------------------------------------------------------------
// Report statuses — must match report_statuses.status_name
// -------------------------------------------------------------------------
defined('REPORT_STATUS_PENDING')     || define('REPORT_STATUS_PENDING',     'Pending');
defined('REPORT_STATUS_IN_PROGRESS') || define('REPORT_STATUS_IN_PROGRESS', 'In Progress');
defined('REPORT_STATUS_VERIFIED')    || define('REPORT_STATUS_VERIFIED',    'Verified');
defined('REPORT_STATUS_RESOLVED')    || define('REPORT_STATUS_RESOLVED',    'Resolved');
defined('REPORT_STATUS_REJECTED')    || define('REPORT_STATUS_REJECTED',    'Rejected');

defined('REPORT_STATUS_ID_PENDING')     || define('REPORT_STATUS_ID_PENDING',     1);
defined('REPORT_STATUS_ID_IN_PROGRESS') || define('REPORT_STATUS_ID_IN_PROGRESS', 2);
defined('REPORT_STATUS_ID_VERIFIED')    || define('REPORT_STATUS_ID_VERIFIED',    3);
defined('REPORT_STATUS_ID_RESOLVED')    || define('REPORT_STATUS_ID_RESOLVED',    4);
defined('REPORT_STATUS_ID_REJECTED')    || define('REPORT_STATUS_ID_REJECTED',    5);

// -------------------------------------------------------------------------
// Collection request statuses
// -------------------------------------------------------------------------
defined('REQUEST_STATUS_PENDING')   || define('REQUEST_STATUS_PENDING',   'Pending');
defined('REQUEST_STATUS_SCHEDULED') || define('REQUEST_STATUS_SCHEDULED', 'Scheduled');
defined('REQUEST_STATUS_COMPLETED') || define('REQUEST_STATUS_COMPLETED', 'Completed');
defined('REQUEST_STATUS_CANCELLED') || define('REQUEST_STATUS_CANCELLED', 'Cancelled');

// -------------------------------------------------------------------------
// Session bootstrap (runs once)
// -------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// -------------------------------------------------------------------------
// CSRF helpers
// -------------------------------------------------------------------------

/**
 * Return the current CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return the HTML hidden input for the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify a submitted CSRF token. Terminates the request on failure.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Invalid or expired security token. Please reload the page and try again.');
    }
}

/**
 * Rotate the CSRF token (call after successful login or logout).
 */
function rotate_csrf(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------------------------------------------------
// Error display policy
// -------------------------------------------------------------------------
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}