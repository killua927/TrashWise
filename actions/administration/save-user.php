<?php
/**
 * actions/administration/save-user.php
 * ---------------------------------------------------------------
 * Create or update a user account (Admin only).
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_ADMIN).
 *   - CSRF required.
 *   - Create mode: password required, hashed with password_hash().
 *   - Edit mode: email read-only, password NOT changed here.
 *   - Email uniqueness enforced server-side.
 *   - Role must be a valid role_id (1=Admin, 2=Staff, 3=Resident).
 *   - Barangay validated if provided.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/administration/manage-users.php');
}

require_role(ROLE_ADMIN);
verify_csrf();

$userId = input_int('user_id');       // 0 for create, > 0 for edit
$isEdit = $userId > 0;

// -------------------------------------------------------------
// Collect input
// -------------------------------------------------------------
$firstName  = input_string('first_name');
$lastName   = input_string('last_name');
$email      = input_string('email');
$phone      = input_string('phone');
$address    = input_string('address');
$roleId     = input_int('role_id');
$barangayId = input_int('barangay_id');
$password   = (string) ($_POST['password'] ?? '');
$confirm    = (string) ($_POST['confirm_password'] ?? '');

// Preserve form values on failure.
$_SESSION['old_edit_user'] = [
    'first_name'  => $firstName,
    'last_name'   => $lastName,
    'email'       => $email,
    'phone'       => $phone,
    'address'     => $address,
    'role_id'     => $roleId,
    'barangay_id' => $barangayId,
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
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    $errors[] = 'A valid email address is required.';
}
if ($phone !== '' && !preg_match('/^[0-9+\-() ]{6,20}$/', $phone)) {
    $errors[] = 'Phone number format is invalid.';
}
if (mb_strlen($address) > 500) {
    $errors[] = 'Address must be 500 characters or fewer.';
}

// Role must exist and be one of the three known roles.
if ($roleId <= 0 || !db_exists("SELECT 1 FROM roles WHERE role_id = ?", [$roleId])) {
    $errors[] = 'Please select a valid role.';
}

// Barangay validated if provided.
if ($barangayId > 0
    && !db_exists("SELECT 1 FROM barangays WHERE barangay_id = ? AND is_active = 1", [$barangayId])) {
    $errors[] = 'The selected barangay is not available.';
}

// Password required only on create.
if (!$isEdit) {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
}

// Email uniqueness.
if (!$errors) {
    if ($isEdit) {
        $dupe = db_scalar(
            "SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1",
            [$email, $userId]
        );
    } else {
        $dupe = db_scalar(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
    }
    if ($dupe) {
        $errors[] = 'Another user already uses that email address.';
    }
}

// Edit mode: verify user exists.
if ($isEdit && !$errors) {
    if (!db_exists("SELECT 1 FROM users WHERE user_id = ?", [$userId])) {
        $errors[] = 'The user you are trying to edit does not exist.';
    }
}

if ($errors) {
    flash('error', implode(' ', $errors));
    $url = $isEdit
        ? 'modules/administration/edit-user.php?id=' . $userId
        : 'modules/administration/edit-user.php';
    redirect($url);
}

// -------------------------------------------------------------
// Create or update
// -------------------------------------------------------------
try {
    if ($isEdit) {
        db_execute("
            UPDATE users
               SET first_name  = ?,
                   last_name   = ?,
                   phone       = ?,
                   address     = ?,
                   role_id     = ?,
                   barangay_id = ?
             WHERE user_id = ?
        ", [
            $firstName,
            $lastName,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
            $roleId,
            $barangayId > 0 ? $barangayId : null,
            $userId,
        ]);

        flash('success', 'User updated successfully.');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $newId = db_insert("
            INSERT INTO users
                (role_id, barangay_id, first_name, last_name, email,
                 password_hash, phone, address, is_email_verified, is_active)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ", [
            $roleId,
            $barangayId > 0 ? $barangayId : null,
            $firstName,
            $lastName,
            $email,
            $hash,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
        ]);

        flash('success', 'User created successfully.');
    }

    unset($_SESSION['old_edit_user']);
    redirect('modules/administration/manage-users.php');

} catch (Throwable $e) {
    error_log('[TrashWise save-user] ' . $e->getMessage());
    flash('error', 'Unable to save the user right now. Please try again.');
    $url = $isEdit
        ? 'modules/administration/edit-user.php?id=' . $userId
        : 'modules/administration/edit-user.php';
    redirect($url);
}