<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------------------
 * Authentication, authorization, and session lifecycle helpers.
 *
 * Responsibilities:
 *   - Server-side 30-minute inactivity timeout.
 *   - require_login(): redirect guests to login.
 *   - require_role(): block users without an allowed role.
 *   - require_owner_or_elevated(): row-level ownership guard.
 *   - current_user_id(), current_role(), is_*() helpers.
 *   - login_user(): populate session, regenerate ID, update DB.
 *   - logout_user(): destroy session, clear cookie, clean DB row.
 *
 * Depends on:
 *   - config/app.php (constants, session bootstrap, CSRF)
 *   - config/database.php (db(), query helpers)
 *   - includes/functions.php (redirect, flash)
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

require_once __DIR__ . '/functions.php';

// -------------------------------------------------------------------------
// Session state helpers
// -------------------------------------------------------------------------

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_role(): ?string
{
    return $_SESSION['role_name'] ?? null;
}

function current_role_id(): ?int
{
    return isset($_SESSION['role_id']) ? (int) $_SESSION['role_id'] : null;
}

function current_user_name(): string
{
    return (string) ($_SESSION['full_name'] ?? 'User');
}

function has_role(string $role): bool
{
    return current_role() === $role;
}

function is_admin(): bool   { return has_role(ROLE_ADMIN); }
function is_staff(): bool   { return has_role(ROLE_STAFF); }
function is_resident(): bool { return has_role(ROLE_RESIDENT); }

/**
 * Return the path to the role-appropriate dashboard.
 */
function dashboard_path_for(?string $role): string
{
    // All roles currently share dashboard.php, which renders role-specific
    // content. Kept as a function so future role-specific dashboards can be
    // introduced without touching call sites.
    return 'dashboard.php';
}

// -------------------------------------------------------------------------
// Inactivity timeout
// -------------------------------------------------------------------------

/**
 * Enforce the 30-minute inactivity timeout. Call this from require_login()
 * or at the top of any page that must enforce the timeout.
 */
function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $now  = time();
    $last = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : $now;

    if (($now - $last) > SESSION_TIMEOUT) {
        logout_user(false);
        // Redirect with a clear reason.
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = $now;

    // Best-effort: keep the DB session record fresh for auditing.
    if (!empty($_SESSION['db_session_id'])) {
        try {
            db_execute(
                "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?",
                [$_SESSION['db_session_id']]
            );
        } catch (Throwable $e) {
            error_log('[TrashWise session update] ' . $e->getMessage());
        }
    }
}

// -------------------------------------------------------------------------
// Access guards
// -------------------------------------------------------------------------

/**
 * Require an authenticated user. Guests are redirected to login.
 */
function require_login(): void
{
    enforce_session_timeout();

    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        $q    = $next ? '?next=' . urlencode($next) : '';
        header('Location: ' . BASE_URL . '/login.php' . $q);
        exit;
    }
}

/**
 * Require one of the given roles.
 *
 * @param string|array $roles
 */
function require_role($roles): void
{
    require_login();

    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array(current_role(), $allowed, true)) {
        http_response_code(403);

        // Render a friendly 403 using shared layout.
        $pageTitle = 'Access Denied';
        require __DIR__ . '/header.php';
        echo '<section class="container" style="padding:2rem 1rem;">'
           . '<div class="alert alert-error" role="alert">'
           . '<strong>Access denied.</strong> You do not have permission to view this page.'
           . '</div>'
           . '<p><a class="btn btn-primary" href="' . e(BASE_URL) . '/dashboard.php">Back to dashboard</a></p>'
           . '</section>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * Allow only the owner of a record OR Admin/Staff.
 */
function require_owner_or_elevated(int $ownerId): void
{
    require_login();
    $uid = current_user_id();

    if ($uid === $ownerId || is_admin() || is_staff()) {
        return;
    }

    http_response_code(403);
    exit('Forbidden.');
}

// -------------------------------------------------------------------------
// Login / logout
// -------------------------------------------------------------------------

/**
 * Populate the session after successful credential verification.
 *
 * @param array $user Row from `users` joined with `roles.role_name`.
 */
function login_user(array $user): void
{
    // Regenerate session ID to prevent fixation.
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['user_id'];
    $_SESSION['role_id']       = (int) $user['role_id'];
    $_SESSION['role_name']     = (string) $user['role_name'];
    $_SESSION['full_name']     = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['email']         = (string) $user['email'];
    $_SESSION['barangay_id']   = isset($user['barangay_id']) ? (int) $user['barangay_id'] : null;
    $_SESSION['last_activity'] = time();

    rotate_csrf();

    // Record last login + create a DB session row.
    try {
        db_execute("UPDATE users SET last_login_at = NOW() WHERE user_id = ?", [(int) $user['user_id']]);

        $sid = session_id();
        db_execute("
            INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_activity)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                user_id       = VALUES(user_id),
                ip_address    = VALUES(ip_address),
                user_agent    = VALUES(user_agent),
                last_activity = NOW()
        ", [
            $sid,
            (int) $user['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);

        $_SESSION['db_session_id'] = $sid;
    } catch (Throwable $e) {
        error_log('[TrashWise login session] ' . $e->getMessage());
    }
}

/**
 * Destroy the current session and (optionally) redirect.
 *
 * @param bool $redirect  When true, redirect to login with a flash message.
 */
function logout_user(bool $redirect = true): void
{
    // Remove the DB session row first.
    if (!empty($_SESSION['db_session_id'])) {
        try {
            db_execute("DELETE FROM user_sessions WHERE session_id = ?", [$_SESSION['db_session_id']]);
        } catch (Throwable $e) {
            error_log('[TrashWise logout] ' . $e->getMessage());
        }
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    if ($redirect) {
        // Start a fresh session just to carry the flash message.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        flash('info', 'You have been logged out.');
        redirect('login.php');
    }
}