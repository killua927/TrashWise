<?php
/**
 * actions/profile/update-profile.php
 * ---------------------------------------------------------------
 * Update the current user's permitted profile fields.
 *
 * Permitted columns:
 *   - first_name, last_name, phone, address, barangay_id
 *
 * Restricted (never touched here):
 *   - email, role_id, is_active, is_email_verified, password_hash
 *
 * Rules:
 *   - Only POST.
 *   - require_login().
 *   - CSRF required.
 *   - Barangay must be active.
 *   - Phone format validated when provided.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

require_login();
verify_csrf();

$userId = current_user_id();

// -------------------------------------------------------------
// Collect input
// -------------------------------------------------------------
$firstName = input_string('first_name');
$lastName  = input_string('last_name');
$phone     = input_string('phone');
$address   = input_string('address');
$barangay  = input_int('barangay_id');

// Preserve input on failure.
$_SESSION['old_profile'] = [
    'first_name'  => $firstName,
    'last_name'   => $lastName,
    'phone'       => $phone,
    'address'     => $address,
    'barangay_id' => $barangay,
];

// -------------------------------------------------------------
// Validation
// -------------------------------------------------------------
$errors = [];

if ($firstName === '' || mb_strlen($firstName) > 100) {
    $errors[] = 'First name is required (max 100 characters).';
}
if ($lastName === '' || mb_strlen($lastName) > 100) {
    $errors[] = 'Last name is required (max 100 characters).';
}
if ($barangay <= 0) {
    $errors[] = 'Please select your barangay.';
}
if ($phone !== '' && !preg_match('/^[0-9+\-() ]{6,20}$/', $phone)) {
    $errors[] = 'Phone number format is invalid.';
}
if (mb_strlen($address) > 500) {
    $errors[] = 'Address must be 500 characters or fewer.';
}

if (!$errors) {
    if (!db_exists("SELECT 1 FROM barangays WHERE barangay_id = ? AND is_active = 1", [$barangay])) {
        $errors[] = 'The selected barangay is not available.';
    }
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('profile.php');
}

// -------------------------------------------------------------
// Update permitted columns only
// -------------------------------------------------------------
try {
    db_execute("
        UPDATE users
           SET first_name = ?,
               last_name  = ?,
               phone      = ?,
               address    = ?,
               barangay_id = ?
         WHERE user_id = ?
    ", [
        $firstName,
        $lastName,
        $phone !== '' ? $phone : null,
        $address !== '' ? $address : null,
        $barangay,
        $userId,
    ]);

    // Keep the session values in sync for the navbar and default barangay.
    $_SESSION['full_name']   = trim($firstName . ' ' . $lastName);
    $_SESSION['barangay_id'] = $barangay;

    unset($_SESSION['old_profile']);
    flash('success', 'Your profile has been updated.');
    redirect('profile.php');

} catch (Throwable $e) {
    error_log('[TrashWise update-profile] ' . $e->getMessage());
    flash('error', 'Unable to update your profile right now. Please try again.');
    redirect('profile.php');
}