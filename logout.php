<?php
/**
 * logout.php
 * ---------------------------------------------------------------
 * Securely destroy the current session and redirect to login.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user(true);