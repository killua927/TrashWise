<?php
/**
 * actions/authentication/register-action.php
 * ---------------------------------------------------------------
 * Handles the resident registration POST.
 *
 * Security:
 *   - CSRF verification
 *   - Server-side validation
 *   - Duplicate email check
 *   - password_hash(PASSWORD_DEFAULT)
 *   - Role forced to Resident (never accepted from the form)
 *   - Transactional insert + admin notification
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

verify_csrf();

// Collect.
$firstName = input_string('first_name');
$lastName  = input_string('last_name');
$email     = input_string('email');
$phone     = input_string('phone');
$address   = input_string('address');
$barangay  = input_int('barangay_id');
$password  = (string) ($_POST['password'] ?? '');
$confirm   = (string) ($_POST['confirm_password'] ?? '');

// Preserve form values on failure.
$_SESSION['old_register'] = [
    'first_name'  => $firstName,
    'last_name'   => $lastName,
    'email'       => $email,
    'phone'       => $phone,
    'address'     => $address,
    'barangay_id' => $barangay,
];

$errors = [];

if ($firstName === '' || mb_strlen($firstName) > 100) $errors[] = 'First name is required (max 100 characters).';
if ($lastName === ''  || mb_strlen($lastName)  > 100) $errors[] = 'Last name is required (max 100 characters).';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    $errors[] = 'A valid email address is required.';
}
if ($barangay <= 0) $errors[] = 'Please select your barangay.';
if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirm) $errors[] = 'Passwords do not match.';
if ($phone !== '' && !preg_match('/^[0-9+\-() ]{6,20}$/', $phone)) $errors[] = 'Phone number format is invalid.';

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('register.php');
}

// Verify barangay is active.
if (!db_exists("SELECT 1 FROM barangays WHERE barangay_id = ? AND is_active = 1", [$barangay])) {
    flash('error', 'The selected barangay is not available.');
    redirect('register.php');
}

// Duplicate email check.
if (db_exists("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$email])) {
    flash('error', 'An account with that email already exists. Please log in instead.');
    redirect('register.php');
}

// Hash and insert inside a transaction.
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $newUserId = db_transaction(function () use ($hash, $barangay, $firstName, $lastName, $email, $phone, $address) {
        $userId = db_insert("
            INSERT INTO users
                (role_id, barangay_id, first_name, last_name, email, password_hash,
                 phone, address, is_email_verified, is_active)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ", [
            ROLE_ID_RESIDENT,           // Enforced server-side — never from the form.
            $barangay,
            $firstName,
            $lastName,
            $email,
            $hash,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
        ]);

        // Notify all admins of the new registration.
        db_execute("
            INSERT INTO notifications (user_id, title, message, link, channel)
            SELECT user_id,
                   'New resident registered',
                   CONCAT(?, ' has created a new account.'),
                   '/modules/administration/manage-users.php',
                   'in_app'
              FROM users
             WHERE role_id = ?
        ", [$firstName . ' ' . $lastName, ROLE_ID_ADMIN]);

        return $userId;
    });

    unset($_SESSION['old_register']);
    flash('success', 'Your account was created successfully. Please log in.');
    redirect('login.php');

} catch (Throwable $e) {
    error_log('[TrashWise register] ' . $e->getMessage());
    flash('error', 'Registration failed. Please try again later.');
    redirect('register.php');
}