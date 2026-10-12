<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------------
 * Shared helper functions used across TrashWise.
 *
 * Includes:
 *   - HTML escaping
 *   - Input reading helpers
 *   - Safe redirects
 *   - Flash messages
 *   - Reference data (barangays, categories, statuses)
 *   - Status badge helpers
 *   - Date formatting
 *   - Pagination math
 *   - Image upload validation and storage
 *
 * This file does NOT start sessions or open database connections; it depends
 * on config/app.php (constants + session) and config/database.php (PDO).
 * ---------------------------------------------------------------------------
 */

if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// -------------------------------------------------------------------------
// Output escaping
// -------------------------------------------------------------------------

/**
 * Escape a value for safe HTML output.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -------------------------------------------------------------------------
// Input helpers
// -------------------------------------------------------------------------

function input_string(string $key, string $method = 'POST'): string
{
    $source = $method === 'GET' ? $_GET : $_POST;
    $value  = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function input_int(string $key, string $method = 'POST', int $default = 0): int
{
    $source = $method === 'GET' ? $_GET : $_POST;
    return isset($source[$key]) ? (int) $source[$key] : $default;
}

// -------------------------------------------------------------------------
// Redirects and flash messages
// -------------------------------------------------------------------------

/**
 * Redirect to an internal path and stop execution.
 */
function redirect(string $path): void
{
    $path = ltrim($path, '/');

    // If the caller passed an absolute URL, use it as-is.
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . BASE_URL . '/' . $path);
    exit;
}

/**
 * Store a flash message for the next request.
 * Types: success, error, warning, info.
 */
function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear all flash messages.
 */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

// -------------------------------------------------------------------------
// Reference data
// -------------------------------------------------------------------------

/**
 * Return all active barangays ordered by name.
 */
function get_barangays(): array
{
    return db_all("
        SELECT barangay_id, barangay_name
          FROM barangays
         WHERE is_active = 1
         ORDER BY barangay_name ASC
    ");
}

/**
 * Return a barangay name by ID (cached per request).
 */
function get_barangay_name(int $barangayId): string
{
    static $cache = [];
    if (array_key_exists($barangayId, $cache)) {
        return $cache[$barangayId];
    }
    $name = db_scalar("SELECT barangay_name FROM barangays WHERE barangay_id = ?", [$barangayId]);
    $cache[$barangayId] = $name ?: 'Unknown';
    return $cache[$barangayId];
}

function get_waste_categories(): array
{
    return db_all("
        SELECT category_id, category_name, description, color_code
          FROM waste_categories
         ORDER BY category_id ASC
    ");
}

function get_concern_types(): array
{
    return db_all("
        SELECT concern_type_id, type_name, description
          FROM concern_types
         ORDER BY concern_type_id ASC
    ");
}

function get_report_statuses(): array
{
    return db_all("
        SELECT status_id, status_name, sort_order, is_locked
          FROM report_statuses
         ORDER BY sort_order ASC
    ");
}

function get_request_statuses(): array
{
    return db_all("
        SELECT status_id, status_name, sort_order
          FROM request_statuses
         ORDER BY sort_order ASC
    ");
}

// -------------------------------------------------------------------------
// Status badge helpers
// -------------------------------------------------------------------------

function report_status_class(string $status): string
{
    switch ($status) {
        case REPORT_STATUS_PENDING:     return 'status-badge status-pending';
        case REPORT_STATUS_IN_PROGRESS: return 'status-badge status-progress';
        case REPORT_STATUS_VERIFIED:    return 'status-badge status-verified';
        case REPORT_STATUS_RESOLVED:    return 'status-badge status-resolved';
        case REPORT_STATUS_REJECTED:    return 'status-badge status-rejected';
        default:                        return 'status-badge';
    }
}

function request_status_class(string $status): string
{
    switch ($status) {
        case REQUEST_STATUS_PENDING:   return 'status-badge status-pending';
        case REQUEST_STATUS_SCHEDULED: return 'status-badge status-progress';
        case REQUEST_STATUS_COMPLETED: return 'status-badge status-resolved';
        case REQUEST_STATUS_CANCELLED: return 'status-badge status-rejected';
        default:                       return 'status-badge';
    }
}

// -------------------------------------------------------------------------
// Date formatting
// -------------------------------------------------------------------------

function format_datetime(?string $value): string
{
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date('M j, Y g:i A', $ts) : '—';
}

function format_date(?string $value): string
{
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date('M j, Y', $ts) : '—';
}

function time_ago(?string $value): string
{
    if (!$value) return '—';
    $ts = strtotime($value);
    if (!$ts) return '—';
    $diff = time() - $ts;

    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';
    return date('M j, Y', $ts);
}

// -------------------------------------------------------------------------
// Pagination
// -------------------------------------------------------------------------

/**
 * Calculate pagination metadata.
 *
 * @return array{total:int,per_page:int,current_page:int,total_pages:int,offset:int}
 */
function paginate(int $total, int $perPage, int $currentPage): array
{
    $perPage     = max(1, $perPage);
    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}

// -------------------------------------------------------------------------
// File upload validation
// -------------------------------------------------------------------------

/**
 * Validate an uploaded image.
 *
 * @return array{ok:bool,error?:string,mime?:string,ext?:string}
 */
function validate_image_upload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload payload.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['ok' => false, 'error' => 'File exceeds the maximum allowed size.'];
        default:
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }

    if ($file['size'] <= 0 || $file['size'] > MAX_PHOTO_SIZE) {
        $mb = MAX_PHOTO_SIZE / 1024 / 1024;
        return ['ok' => false, 'error' => "File size must be between 1 byte and {$mb} MB."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, ALLOWED_IMAGE_MIMES)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and WebP images are allowed.'];
    }

    return ['ok' => true, 'mime' => $mime, 'ext' => ALLOWED_IMAGE_MIMES[$mime]];
}

/**
 * Move an uploaded image into uploads/reports/{report_id}/.
 *
 * @return array{ok:bool,error?:string,path?:string,filename?:string}
 */
function save_report_photo(array $file, int $reportId): array
{
    $check = validate_image_upload($file);
    if (!$check['ok']) {
        return $check;
    }

    $subDir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $reportId;

    if (!is_dir($subDir) && !mkdir($subDir, 0775, true) && !is_dir($subDir)) {
        return ['ok' => false, 'error' => 'Unable to create upload directory.'];
    }

    $filename = sprintf(
        'report_%d_%s_%s.%s',
        $reportId,
        date('YmdHis'),
        bin2hex(random_bytes(4)),
        $check['ext']
    );

    $target = $subDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Failed to store the uploaded file.'];
    }

    return [
        'ok'       => true,
        'path'     => 'uploads/reports/' . $reportId . '/' . $filename,
        'filename' => $filename,
    ];
}

// -------------------------------------------------------------------------
// Small output helpers
// -------------------------------------------------------------------------

/**
 * Return the first letter of a name for avatar display.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
    }
    return $out !== '' ? $out : '?';
}