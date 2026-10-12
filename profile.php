<?php
/**
 * profile.php
 * ---------------------------------------------------------------
 * User profile page.
 *
 * Access:
 *   - require_login() (Resident, Staff, Admin).
 *
 * Loads:
 *   - User row scoped to current_user_id.
 *   - Barangay list (active only).
 *
 * Renders:
 *   - Profile edit form (permitted fields).
 *   - Password change form.
 *
 * Restricted fields (email, role, verification status, is_active)
 * are shown read-only. Server-side handlers never update them here.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$userId = current_user_id();

// -------------------------------------------------------------
// Load the current user's row (scoped to the current user)
// -------------------------------------------------------------
$user = db_one("
    SELECT user_id, role_id, barangay_id, first_name, last_name,
           email, phone, address,
           is_email_verified, email_verified_at,
           is_active, created_at, updated_at, last_login_at
      FROM users
     WHERE user_id = ?
     LIMIT 1
", [$userId]);

if (!$user) {
    // Should never happen for an authenticated user; log out defensively.
    logout_user(true);
}

// Barangay list for the dropdown.
$barangays = get_barangays();

// Preserve old input on validation failure.
$oldProfile  = $_SESSION['old_profile']  ?? [];
$oldPassword = $_SESSION['old_password'] ?? [];
unset($_SESSION['old_profile'], $_SESSION['old_password']);

$val = function (string $key, $fallback = '') use ($oldProfile, $user) {
    if (array_key_exists($key, $oldProfile)) {
        return $oldProfile[$key];
    }
    return $user[$key] ?? $fallback;
};

$pageTitle = 'Profile';
$pageCss   = ['profile.css'];
$pageJs    = ['profile.js'];
$bodyClass = 'is-authenticated';

require_once __DIR__ . '/includes/header.php';
?>

<section class="container page-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">Profile</span>
    </nav>
    <h1 class="page-title">My Profile</h1>
    <p class="text-muted">
        Manage your personal information and account password.
    </p>
</section>

<section class="container profile">
    <div class="profile__grid">

        <!-- Profile information -->
        <article class="card profile__card">
            <header class="card-head">
                <h2 class="card-title">Personal Information</h2>
                <p class="card-subtitle">
                    Update your contact details below.
                </p>
            </header>

            <form action="<?= e(BASE_URL) ?>/actions/profile/update-profile.php"
                  method="post" id="profileForm" novalidate>
                <?= csrf_field() ?>

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

                <div class="form-group">
                    <label class="form-label" for="email_display">Email Address</label>
                    <input type="email" class="form-control" id="email_display"
                           value="<?= e($user['email']) ?>" readonly
                           aria-readonly="true">
                    <span class="form-hint">
                        Email is read-only.
                        <?php if ((int) $user['is_email_verified'] === 1): ?>
                            ✓ Verified.
                        <?php else: ?>
                            Verification is not yet enabled for this account.
                        <?php endif; ?>
                        Contact an administrator to change your email.
                    </span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="barangay_id">Barangay <span class="required">*</span></label>
                    <select class="form-select" id="barangay_id" name="barangay_id" required>
                        <option value="">Select your barangay</option>
                        <?php foreach ($barangays as $b): ?>
                            <option value="<?= (int) $b['barangay_id'] ?>"
                                <?= ((int) $val('barangay_id')) === (int) $b['barangay_id'] ? 'selected' : '' ?>>
                                <?= e($b['barangay_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" data-error-for="barangay_id"></span>
                </div>

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

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                        Save Changes
                    </button>
                </div>
            </form>
        </article>

        <!-- Account info + password -->
        <aside class="profile__aside">

            <!-- Account overview -->
            <article class="card profile__card">
                <header class="card-head">
                    <h2 class="card-title">Account</h2>
                </header>
                <dl class="details-list">
                    <div class="details-list__row">
                        <dt>User ID</dt>
                        <dd>#<?= (int) $user['user_id'] ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Role</dt>
                        <dd><span class="role-pill"><?= e(current_role()) ?></span></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Status</dt>
                        <dd>
                            <?php if ((int) $user['is_active'] === 1): ?>
                                <span class="status-badge status-verified">Active</span>
                            <?php else: ?>
                                <span class="status-badge status-rejected">Inactive</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Email verified</dt>
                        <dd><?= ((int) $user['is_email_verified'] === 1) ? 'Yes' : 'No' ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Member since</dt>
                        <dd><?= e(format_date($user['created_at'])) ?></dd>
                    </div>
                    <div class="details-list__row">
                        <dt>Last login</dt>
                        <dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
                    </div>
                </dl>
            </article>

            <!-- Change password -->
            <article class="card profile__card">
                <header class="card-head">
                    <h2 class="card-title">Change Password</h2>
                    <p class="card-subtitle">
                        Use a strong, unique password of at least 8 characters.
                    </p>
                </header>

                <form action="<?= e(BASE_URL) ?>/actions/profile/change-password.php"
                      method="post" id="passwordForm" novalidate autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="current_password"
                               name="current_password" required
                               autocomplete="current-password">
                        <span class="form-error" data-error-for="current_password"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="new_password"
                               name="new_password" required minlength="8"
                               autocomplete="new-password">
                        <span class="form-hint">Minimum 8 characters.</span>
                        <span class="form-error" data-error-for="new_password"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="confirm_password"
                               name="confirm_password" required minlength="8"
                               autocomplete="new-password">
                        <span class="form-error" data-error-for="confirm_password"></span>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary" id="savePasswordBtn">
                            Update Password
                        </button>
                    </div>
                </form>
            </article>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>