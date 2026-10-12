-- ============================================================================
-- TrashWise Waste Management System
-- database/trashwise.sql
-- ----------------------------------------------------------------------------
-- Complete MySQL schema for the TrashWise application.
--
-- Target platform : XAMPP (MySQL / MariaDB)
-- Engine          : InnoDB
-- Charset         : utf8mb4 / utf8mb4_unicode_ci
-- Normalization   : Third Normal Form (3NF)
--
-- Import this file via phpMyAdmin after creating the database:
--     CREATE DATABASE trashwise_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--
-- This script is safe to import into a fresh database. If re-importing,
-- drop the database first to avoid duplicate-key conflicts.
--
-- IMPORTANT SECURITY NOTE:
--   No plaintext passwords are inserted. Create the first administrator
--   account using the setup script documented in database/README.md.
--   Sample users included here use placeholder password hashes that MUST
--   be replaced before use (see README.md).
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';   -- Philippine Standard Time
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SECTION 1 — LOOKUP / REFERENCE TABLES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- roles — User role lookup (3NF: eliminates repeating role names in users)
-- ---------------------------------------------------------------------------
CREATE TABLE roles (
    role_id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_name   VARCHAR(50)  NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_id, role_name, description) VALUES
(1, 'Admin',    'Full system access, user and content management'),
(2, 'Staff',    'Handles reports and collection requests'),
(3, 'Resident', 'Submits reports and collection requests');

-- ---------------------------------------------------------------------------
-- barangays — All 19 barangays of Minglanilla, Cebu
-- ---------------------------------------------------------------------------
CREATE TABLE barangays (
    barangay_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    barangay_name VARCHAR(100) NOT NULL,
    municipality  VARCHAR(100) NOT NULL DEFAULT 'Minglanilla',
    province      VARCHAR(100) NOT NULL DEFAULT 'Cebu',
    latitude      DECIMAL(10,8) DEFAULT NULL,
    longitude     DECIMAL(11,8) DEFAULT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (barangay_id),
    UNIQUE KEY uq_barangay_name (barangay_name),
    KEY idx_barangay_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO barangays (barangay_id, barangay_name, latitude, longitude) VALUES
( 1, 'Cadulawan',          10.24470000, 123.79600000),
( 2, 'Calajo-an',          10.24940000, 123.79080000),
( 3, 'Camp 7',             10.25710000, 123.79420000),
( 4, 'Campo 8',            10.26200000, 123.79900000),
( 5, 'Cuanos',             10.23950000, 123.80190000),
( 6, 'Guindarohan',        10.23280000, 123.78830000),
( 7, 'Kinasang-an',        10.24720000, 123.80360000),
( 8, 'Lipata',             10.23510000, 123.79380000),
( 9, 'Minglanilla Proper', 10.24490000, 123.79560000),
(10, 'Pakigne',            10.25090000, 123.80280000),
(11, 'Poblacion Ward I',   10.24370000, 123.79810000),
(12, 'Poblacion Ward II',  10.24230000, 123.79640000),
(13, 'Poblacion Ward III', 10.24090000, 123.79480000),
(14, 'Poblacion Ward IV',  10.23940000, 123.79320000),
(15, 'Tubod',              10.25430000, 123.78710000),
(16, 'Tulay',              10.23660000, 123.80050000),
(17, 'Tunghaan',           10.25170000, 123.78650000),
(18, 'Tungkil',            10.24590000, 123.78940000),
(19, 'Tungkop',            10.23820000, 123.80490000);

-- ---------------------------------------------------------------------------
-- waste_categories — 4 waste categories
-- ---------------------------------------------------------------------------
CREATE TABLE waste_categories (
    category_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description   TEXT         DEFAULT NULL,
    color_code    VARCHAR(20)  DEFAULT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO waste_categories (category_id, category_name, description, color_code) VALUES
(1, 'Biodegradable',     'Waste that decomposes naturally (food scraps, garden waste, paper).', '#6BA24B'),
(2, 'Recyclable',        'Materials that can be processed into new products (bottles, cans, paper).', '#2563eb'),
(3, 'Non-Biodegradable', 'Waste that does not decompose quickly (plastics, glass, metals).', '#d97706'),
(4, 'Hazardous',         'Waste that poses risks to health or environment (chemicals, batteries, medical).', '#dc2626');

-- ---------------------------------------------------------------------------
-- concern_types — 4 concern types
-- ---------------------------------------------------------------------------
CREATE TABLE concern_types (
    concern_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_name       VARCHAR(100) NOT NULL,
    description     TEXT         DEFAULT NULL,
    PRIMARY KEY (concern_type_id),
    UNIQUE KEY uq_concern_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO concern_types (concern_type_id, type_name, description) VALUES
(1, 'Overflowing Bin',          'A collection bin that has exceeded its capacity.'),
(2, 'Improper Garbage Disposal','Waste disposed of incorrectly or in the wrong category.'),
(3, 'Littering',                'Scattered waste in public or private areas.'),
(4, 'Illegal Dumping',          'Waste dumped in unauthorized locations.');

-- ---------------------------------------------------------------------------
-- report_statuses — Report workflow states with lock flag
-- ---------------------------------------------------------------------------
CREATE TABLE report_statuses (
    status_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    status_name VARCHAR(50)  NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    is_locked   TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (status_id),
    UNIQUE KEY uq_report_status_name (status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO report_statuses (status_id, status_name, description, sort_order, is_locked) VALUES
(1, 'Pending',     'Submitted and awaiting review.',                1, 0),
(2, 'In Progress', 'Being handled by staff.',                       2, 0),
(3, 'Verified',    'Confirmed valid and locked from further edits.',3, 1),
(4, 'Resolved',    'Issue has been resolved.',                      4, 1),
(5, 'Rejected',    'Report was rejected after review.',             5, 1);

-- ---------------------------------------------------------------------------
-- request_statuses — Collection request workflow states
-- ---------------------------------------------------------------------------
CREATE TABLE request_statuses (
    status_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    status_name VARCHAR(50)  NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (status_id),
    UNIQUE KEY uq_request_status_name (status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO request_statuses (status_id, status_name, description, sort_order) VALUES
(1, 'Pending',   'Awaiting scheduling.',       1),
(2, 'Scheduled', 'Collection has been scheduled.', 2),
(3, 'Completed', 'Collection completed.',      3),
(4, 'Cancelled', 'Request cancelled.',         4);

-- ============================================================================
-- SECTION 2 — CORE ENTITY TABLES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- users — All system users
-- Note: password_hash stores a bcrypt/argon2 hash generated by PHP.
-- The placeholder values below MUST be replaced before use.
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    user_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id           TINYINT UNSIGNED NOT NULL,
    barangay_id       INT UNSIGNED DEFAULT NULL,
    first_name        VARCHAR(100) NOT NULL,
    last_name         VARCHAR(100) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    phone             VARCHAR(20)  DEFAULT NULL,
    address           VARCHAR(500) DEFAULT NULL,
    is_email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    email_verified_at DATETIME     DEFAULT NULL,
    last_login_at     DATETIME     DEFAULT NULL,
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role     (role_id),
    KEY idx_users_barangay (barangay_id),
    KEY idx_users_active   (is_active),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_users_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- email_verifications — Future OTP tokens
-- Tokens are hashed; expiry is enforced by the application.
-- ---------------------------------------------------------------------------
CREATE TABLE email_verifications (
    verification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    email           VARCHAR(255) NOT NULL,
    otp_hash        VARCHAR(255) NOT NULL,
    token           VARCHAR(64)  NOT NULL,
    purpose         ENUM('registration','email_change','password_reset')
                    NOT NULL DEFAULT 'registration',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_used         TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (verification_id),
    UNIQUE KEY uq_verification_token (token),
    KEY idx_verification_user (user_id, purpose),
    CONSTRAINT fk_verification_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- user_sessions — Server-side session tracking for inactivity timeout
-- ---------------------------------------------------------------------------
CREATE TABLE user_sessions (
    session_id    VARCHAR(128) NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    user_agent    VARCHAR(500) DEFAULT NULL,
    last_activity DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    KEY idx_session_user     (user_id),
    KEY idx_session_activity (last_activity),
    CONSTRAINT fk_session_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 3 — TRANSACTIONAL TABLES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- waste_reports — Resident-submitted waste reports
-- is_locked is set to 1 when the report reaches a locked status.
-- ---------------------------------------------------------------------------
CREATE TABLE waste_reports (
    report_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    barangay_id     INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    concern_type_id INT UNSIGNED NOT NULL,
    status_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    title           VARCHAR(255) NOT NULL,
    description     TEXT         NOT NULL,
    location        VARCHAR(500) NOT NULL,
    latitude        DECIMAL(10,8) DEFAULT NULL,
    longitude       DECIMAL(11,8) DEFAULT NULL,
    is_locked       TINYINT(1)   NOT NULL DEFAULT 0,
    locked_at       DATETIME     DEFAULT NULL,
    locked_by       INT UNSIGNED DEFAULT NULL,
    submitted_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    KEY idx_reports_user     (user_id),
    KEY idx_reports_barangay (barangay_id),
    KEY idx_reports_category (category_id),
    KEY idx_reports_concern  (concern_type_id),
    KEY idx_reports_status   (status_id),
    KEY idx_reports_submitted(submitted_at),
    CONSTRAINT fk_reports_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_category
        FOREIGN KEY (category_id) REFERENCES waste_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_concern
        FOREIGN KEY (concern_type_id) REFERENCES concern_types(concern_type_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_status
        FOREIGN KEY (status_id) REFERENCES report_statuses(status_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_locked_by
        FOREIGN KEY (locked_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- report_photos — Photo evidence per report (min 2 enforced in PHP)
-- ---------------------------------------------------------------------------
CREATE TABLE report_photos (
    photo_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id         INT UNSIGNED NOT NULL,
    file_path         VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size         INT UNSIGNED NOT NULL DEFAULT 0,
    mime_type         VARCHAR(100) NOT NULL,
    uploaded_by       INT UNSIGNED DEFAULT NULL,
    uploaded_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (photo_id),
    KEY idx_photo_report (report_id),
    KEY idx_photo_uploader (uploaded_by),
    CONSTRAINT fk_photo_report
        FOREIGN KEY (report_id) REFERENCES waste_reports(report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_photo_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- report_status_history — Audit trail of report status changes
-- ---------------------------------------------------------------------------
CREATE TABLE report_status_history (
    history_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id     INT UNSIGNED NOT NULL,
    old_status_id TINYINT UNSIGNED DEFAULT NULL,
    new_status_id TINYINT UNSIGNED NOT NULL,
    changed_by    INT UNSIGNED NOT NULL,
    remarks       TEXT         DEFAULT NULL,
    changed_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_history_report (report_id, changed_at),
    KEY idx_history_actor  (changed_by),
    CONSTRAINT fk_history_report
        FOREIGN KEY (report_id) REFERENCES waste_reports(report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_history_old_status
        FOREIGN KEY (old_status_id) REFERENCES report_statuses(status_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_history_new_status
        FOREIGN KEY (new_status_id) REFERENCES report_statuses(status_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- collection_requests — Resident-submitted collection requests
-- ---------------------------------------------------------------------------
CREATE TABLE collection_requests (
    request_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    barangay_id    INT UNSIGNED NOT NULL,
    category_id    INT UNSIGNED NOT NULL,
    status_id      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    assigned_to    INT UNSIGNED DEFAULT NULL,
    description    TEXT         NOT NULL,
    location       VARCHAR(500) NOT NULL,
    preferred_date DATE         DEFAULT NULL,
    submitted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_requests_user     (user_id),
    KEY idx_requests_barangay (barangay_id),
    KEY idx_requests_status   (status_id),
    KEY idx_requests_assigned (assigned_to),
    KEY idx_requests_submitted(submitted_at),
    CONSTRAINT fk_requests_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_requests_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_requests_category
        FOREIGN KEY (category_id) REFERENCES waste_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_requests_status
        FOREIGN KEY (status_id) REFERENCES request_statuses(status_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_requests_assigned
        FOREIGN KEY (assigned_to) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- disposal_locations — Designated disposal facilities per barangay
-- ---------------------------------------------------------------------------
CREATE TABLE disposal_locations (
    location_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    barangay_id         INT UNSIGNED NOT NULL,
    location_name       VARCHAR(255) NOT NULL,
    facility_type       VARCHAR(100) DEFAULT NULL,
    address             VARCHAR(500) NOT NULL,
    latitude            DECIMAL(10,8) DEFAULT NULL,
    longitude           DECIMAL(11,8) DEFAULT NULL,
    accepted_categories VARCHAR(500) DEFAULT NULL,
    operating_hours     VARCHAR(255) DEFAULT NULL,
    contact_number      VARCHAR(20)  DEFAULT NULL,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (location_id),
    KEY idx_locations_barangay (barangay_id),
    KEY idx_locations_active   (is_active),
    CONSTRAINT fk_locations_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- announcements — Community announcements (optionally barangay-scoped)
-- ---------------------------------------------------------------------------
CREATE TABLE announcements (
    announcement_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_by      INT UNSIGNED NOT NULL,
    barangay_id     INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(255) NOT NULL,
    content         TEXT         NOT NULL,
    published_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME     DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (announcement_id),
    KEY idx_announcements_active   (is_active, published_at),
    KEY idx_announcements_barangay (barangay_id),
    CONSTRAINT fk_announcements_user
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_announcements_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- notifications — In-system notifications and email queue markers
-- ---------------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT         NOT NULL,
    link            VARCHAR(500) DEFAULT NULL,
    channel         ENUM('in_app','email','both') NOT NULL DEFAULT 'in_app',
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    email_sent_at   DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- email_queue — Future email delivery queue
-- ---------------------------------------------------------------------------
CREATE TABLE email_queue (
    email_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED DEFAULT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    body            TEXT         NOT NULL,
    status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    last_error      TEXT         DEFAULT NULL,
    scheduled_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at         DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email_id),
    KEY idx_email_status (status, scheduled_at),
    CONSTRAINT fk_email_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- waste_guides — Admin-managed waste management guides
-- ---------------------------------------------------------------------------
CREATE TABLE waste_guides (
    guide_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id  INT UNSIGNED NOT NULL,
    created_by   INT UNSIGNED NOT NULL,
    title        VARCHAR(255) NOT NULL,
    content      TEXT         NOT NULL,
    is_published TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (guide_id),
    KEY idx_guides_category (category_id),
    KEY idx_guides_published (is_published),
    CONSTRAINT fk_guides_category
        FOREIGN KEY (category_id) REFERENCES waste_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_guides_user
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 4 — OPTIONAL DEMONSTRATION DATA
-- ----------------------------------------------------------------------------
-- The records below are for testing only. Delete this section before
-- production deployment.
--
-- SECURITY: The password hashes below are placeholders and MUST be replaced.
-- See database/README.md for the first-admin setup procedure.
-- ============================================================================

-- Sample users (placeholders — replace password_hash before use) ----------
INSERT INTO users
    (user_id, role_id, barangay_id, first_name, last_name, email, password_hash,
     phone, address, is_email_verified, email_verified_at, is_active)
VALUES
(1, 1,  9, 'System',  'Administrator', 'admintrashwise@gmail.com',
 '$2y$10$REPLACE_ME_WITH_REAL_HASH_0000000000000000000000000000000',
 '09170000001', 'Minglanilla Municipal Hall', 1, NOW(), 1),
(2, 2,  1, 'David',    'unabia',     'stafftrashwise@gmail.com',
 '$2y$10$REPLACE_ME_WITH_REAL_HASH_0000000000000000000000000000000',
 '09170000002', 'Cadulawan Barangay Hall', 1, NOW(), 1),
(3, 3, 12, 'jaylord',   'Slaspuna',        'resident@trashwise.local',
 '$2y$10$REPLACE_ME_WITH_REAL_HASH_0000000000000000000000000000000',
 '09170000003', 'Poblacion Ward II, Minglanilla', 0, NULL, 1);

-- Sample disposal locations ----------------------------------------------
INSERT INTO disposal_locations
    (location_id, barangay_id, location_name, facility_type, address,
     latitude, longitude, accepted_categories, operating_hours, contact_number)
VALUES
(1,  1, 'Cadulawan Material Recovery Facility', 'MRF',
 'Cadulawan, Minglanilla, Cebu', 10.24470000, 123.79600000,
 'Biodegradable, Recyclable', 'Mon–Sat 6:00 AM – 5:00 PM', '09170000002'),
(2,  9, 'Minglanilla Central Collection Point', 'Collection Point',
 'Minglanilla Proper, Cebu', 10.24490000, 123.79560000,
 'Biodegradable, Recyclable, Non-Biodegradable', 'Daily 5:00 AM – 6:00 PM', NULL);

-- Sample waste guides ----------------------------------------------------
INSERT INTO waste_guides (guide_id, category_id, created_by, title, content, is_published)
VALUES
(1, 1, 1, 'How to Handle Biodegradable Waste',
 'Biodegradable waste includes food scraps, garden trimmings, and paper. '
 'Segregate it from other waste and use it for composting at home.', 1),
(2, 2, 1, 'Recyclable Waste: What to Do',
 'Recyclables include plastic bottles, glass containers, metal cans, and '
 'clean paper. Rinse them before storing.', 1);

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================