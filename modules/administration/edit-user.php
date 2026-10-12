<?php
/**
 * modules/administration/edit-user.php
 * ---------------------------------------------------------------
 * Create or edit a user account.
 *
 * Guards:
 *   - require_role(ROLE_ADMIN)
 *
 * Modes:
 *   - Create: no ?id → empty form, role defaults to Resident.
 *   - Edit:   ?id={id} → pre-filled form.
 *
 * Restricted:
 *   - email is read-only in edit mode (email change requires re-verification).
 *   - password_hash is never touched here in edit mode.
 *   - is_email_verified, email_verified_at, last_login_at are never touched.
 *
 * Submit → actions/administration/save-user.php
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(ROLE_ADMIN);

$editId = input_int('id', 'GET');
$isEdit = $editId > 0;

$user = null;
if ($isEdit) {
    $user = db_one("
        SELECT user_id, role_id, barangay_id, first_name, last_name,
               email, phone, address, is_active, is_email_verified,
               email_verified_at, created_at, last_login_at
          FROM users
         WHERE user_id = ?
         LIMIT 1
    ", [$editId]);

    if (!$user) {
        flash('error', 'User not found.');
        redirect('modules/administration/manage-users.php');
    }
}

$roles     = db_all("SELECT role_id, role_name FROM roles ORDER BY role_id");
$barangays = get_barangays();

// Preserve submitted values on validation failure.
$old = $_SESSION['old_edit_user'] ?? [];
unset($_SESSION['old_edit_user']);

// Default values.
$defaults = [
    'first_name'    => '',
    'last_name'     => '',
    'email'         => '',
    'phone'         => '',
    'address'       => '',
    'role_id'       => ROLE_ID_RESIDENT,
    'barangay_id'   => 0,
];

$val = function (string $key) use ($old, $user, $defaults) {
    if (array_key_exists($key, $old)) return $old[$key];
    if ($user !== null && isset($user[$key])) return $user[$key];
    return $defaults[$key] ?? '';
};

$pageTitle = $isEdit ? 'Edit User' : 'New User';
$pageCss   = ['edit-user.css'];
$pageJs    = ['edit-user.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/modules/administration/dashboard.php">Admin Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?= e(BASE_URL) ?>/modules/administration/manage-users.php">Users</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page"><?= $isEdit ? 'Edit #' . (int) $editId : 'New User' ?></span>
    </nav>
    <h1 class="page-title">
        <?= $isEdit ? 'Edit User' : 'Create New User' ?>
    </h1>
    <p class="text-muted">
        <?= $isEdit
            ? 'Update the user\'s profile and role. Email cannot be changed here.'
            : 'Create a new Resident, Staff, or Admin account.' ?>
    </p>
</section>

<section class="container edit-user">
    <form action="<?= e(BASE_URL) ?>/actions/administration/save-user.php"
          method="post" id="editUserForm" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="user_id" value="<?= (int) $editId ?>">
        <?php endif; ?>

        <!-- Name -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                <input type="text" class="form-control" id="first_name" name="first_name"
                       maxlength="100" required value="<?= e($val('first_name')) ?>">
                <span class="form-error" data-error-for="first_name"></span>
            </div>
            <div class="form-group">
                <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" class="form-control" id="last_name" name="last_name"
                       maxlength="100" required value="<?= e($val('last_name')) ?>">
                <span class="form-error" data-error-for="last_name"></span>
            </div>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label class="form-label" for="email">Email Address <span class="required">*</span></label>
            <input type="email" class="form-control" id="email" name="email"
                   maxlength="255" required <?= $isEdit ? 'readonly aria-readonly="true"' : '' ?>
                   value="<?= e($val('email')) ?>">
            <?php if ($isEdit): ?>
                <span class="form-hint">
                    Email is read-only. Changing email requires re-verification (future OTP flow).
                </span>
            <?php else: ?>
                <span class="form-hint">Must be unique across the system.</span>
            <?php endif; ?>
            <span class="form-error" data-error-for="email"></span>
        </div>

        <!-- Password (create only) -->
        <?php if (!$isEdit): ?>
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
                    <input type="password" class="form-control" id="confirm_password"
                           name="confirm_password" minlength="8" required
                           autocomplete="new-password">
                    <span class="form-error" data-error-for="confirm_password"></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Role -->
        <div class="form-group">
            <label class="form-label" for="role_id">Role <span class="required">*</span></label>
            <select class="form-select" id="role_id" name="role_id" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['role_id'] ?>"
                        <?= ((int) $val('role_id')) === (int) $r['role_id'] ? 'selected' : '' ?>>
                        <?= e($r['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Only Admin, Staff, or Resident roles exist in the system.</span>
        </div>

        <!-- Barangay -->
        <div class="form-group">
            <label class="form-label" for="barangay_id">Barangay (optional)</label>
            <select class="form-select" id="barangay_id" name="barangay_id">
                <option value="">— None —</option>
                <?php foreach ($barangays as $b): ?>
                    <option value="<?= (int) $b['barangay_id'] ?>"
                        <?= ((int) $val('barangay_id')) === (int) $b['barangay_id'] ? 'selected' : '' ?>>
                        <?= e($b['barangay_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Phone / Address -->
        <div class="form-group">
            <label class="form-label" for="phone">Phone (optional)</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   maxlength="20" pattern="[0-9+\-() ]*"
                   value="<?= e($val('phone')) ?>">
            <span class="form-error" data-error-for="phone"></span>
        </div>

        <div class="form-group">
            <label class="form-label" for="address">Address (optional)</label>
            <input type="text" class="form-control" id="address" name="address"
                   maxlength="500" value="<?= e($val('address')) ?>">
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/modules/administration/manage-users.php"
               class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" id="saveUserBtn">
                <?= $isEdit ? 'Save Changes' : 'Create User' ?>
            </button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>