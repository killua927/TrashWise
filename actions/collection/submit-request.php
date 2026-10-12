<?php
/**
 * actions/collection/submit-request.php
 * ---------------------------------------------------------------
 * Handle the collection request POST.
 *
 * Rules:
 *   - Only POST.
 *   - require_role(ROLE_RESIDENT).
 *   - CSRF required.
 *   - category_id, barangay_id, location, description required.
 *   - preferred_date optional; must not be in the past.
 *   - Reference IDs validated.
 *
 * Transaction:
 *   - INSERT collection_requests (status = Pending)
 *   - INSERT notifications for admins
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/collection/request-collection.php');
}

require_role(ROLE_RESIDENT);
verify_csrf();

$userId = current_user_id();

// -------------------------------------------------------------
// Collect input
// -------------------------------------------------------------
$categoryId    = input_int('category_id');
$barangayId    = input_int('barangay_id');
$location      = input_string('location');
$description   = input_string('description');
$preferredDate = input_string('preferred_date'); // YYYY-MM-DD or ''

// Preserve input on failure.
$_SESSION['old_request'] = [
    'category_id'    => $categoryId,
    'barangay_id'    => $barangayId,
    'location'       => $location,
    'description'    => $description,
    'preferred_date' => $preferredDate,
];

// -------------------------------------------------------------
// Validation
// -------------------------------------------------------------
$errors = [];

if ($categoryId <= 0) $errors[] = 'Please select a waste category.';
if ($barangayId <= 0) $errors[] = 'Please select a barangay.';
if ($location === '' || mb_strlen($location) > 500) {
    $errors[] = 'Location is required (max 500 characters).';
}
if ($description === '' || mb_strlen($description) < 10 || mb_strlen($description) > 2000) {
    $errors[] = 'Description must be between 10 and 2000 characters.';
}

// Preferred date must be today or later.
$preferredSql = null;
if ($preferredDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $preferredDate);
    if (!$dt || $dt->format('Y-m-d') !== $preferredDate) {
        $errors[] = 'Preferred date format is invalid.';
    } else {
        $today = new DateTime('today');
        if ($dt < $today) {
            $errors[] = 'Preferred date cannot be in the past.';
        } else {
            $preferredSql = $preferredDate;
        }
    }
}

// Reference data validation.
if (!$errors) {
    if (!db_exists("SELECT 1 FROM waste_categories WHERE category_id = ?", [$categoryId])) {
        $errors[] = 'The selected waste category is invalid.';
    }
    if (!db_exists("SELECT 1 FROM barangays WHERE barangay_id = ? AND is_active = 1", [$barangayId])) {
        $errors[] = 'The selected barangay is not available.';
    }
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('modules/collection/request-collection.php');
}

// -------------------------------------------------------------
// Transaction
// -------------------------------------------------------------
try {
    db_transaction(function () use (
        $userId, $barangayId, $categoryId, $description, $location, $preferredSql
    ) {
        $requestId = db_insert("
            INSERT INTO collection_requests
                (user_id, barangay_id, category_id, status_id,
                 description, location, preferred_date)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)
        ", [
            $userId,
            $barangayId,
            $categoryId,
            1, // Pending
            $description,
            $location,
            $preferredSql,
        ]);

        // Notify all admins of the new request.
        db_execute("
            INSERT INTO notifications (user_id, title, message, link, channel)
            SELECT user_id,
                   'New collection request submitted',
                   CONCAT('A new collection request (#', ?, ') was submitted.'),
                   ?,
                   'in_app'
              FROM users
             WHERE role_id = ?
        ", [
            $requestId,
            '/modules/collection/my-requests.php',
            ROLE_ID_ADMIN,
        ]);

        return $requestId;
    });

    unset($_SESSION['old_request']);
    flash('success', 'Your collection request has been submitted. Staff will review and schedule it.');
    redirect('modules/collection/my-requests.php');

} catch (Throwable $e) {
    error_log('[TrashWise submit-request] ' . $e->getMessage());
    flash('error', 'Unable to submit the request right now. Please try again.');
    redirect('modules/collection/request-collection.php');
}