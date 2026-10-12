<?php
/**
 * config/database.php
 * ---------------------------------------------------------------------------
 * Centralized database layer for TrashWise.
 *
 * Responsibilities:
 *   - Read credentials from environment variables when available.
 *   - Provide a single shared PDO instance (db()).
 *   - Provide reusable query helpers used across all modules:
 *       db_query()        - run a prepared statement and return the statement
 *       db_one()          - fetch a single row
 *       db_all()          - fetch all rows
 *       db_insert()       - insert and return the new ID
 *       db_execute()      - run an UPDATE / DELETE / generic statement
 *       db_exists()       - check whether at least one row matches
 *       db_transaction()  - wrap a callable in a transaction
 *   - Never expose raw exception messages to visitors.
 *
 * Design notes:
 *   - All future modules (reports, collection, guides, notifications, maps,
 *     analytics, administration) must reuse db() and these helpers.
 *   - Do NOT create new PDO connections inside individual pages.
 *   - Credentials come from config/app.php environment constants, which read
 *     from getenv() when available (XAMPP-friendly, production-ready).
 * ---------------------------------------------------------------------------
 */

// -------------------------------------------------------------------------
// Environment loading — must run before db() is called.
// -------------------------------------------------------------------------
if (!defined('TRASHWISE_APP')) {
    define('TRASHWISE_APP', true);
}

// Load application constants (defines environment + BASE_URL + sessions).
require_once __DIR__ . '/app.php';

// -------------------------------------------------------------------------
// Database credentials — prefer environment variables, fall back to XAMPP
// defaults so the project works locally out of the box.
// -------------------------------------------------------------------------
defined('DB_HOST')    || define('DB_HOST',    getenv('TRASHWISE_DB_HOST')    ?: 'localhost');
defined('DB_NAME')    || define('DB_NAME',    getenv('TRASHWISE_DB_NAME')    ?: 'trashwise_db');
defined('DB_USER')    || define('DB_USER',    getenv('TRASHWISE_DB_USER')    ?: 'root');
defined('DB_PASS')    || define('DB_PASS',    getenv('TRASHWISE_DB_PASS')    ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------------------------------
// Shared PDO instance.
// -------------------------------------------------------------------------

/**
 * Return the single shared PDO connection.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log technical detail, return generic message.
        error_log('[TrashWise DB] ' . $e->getMessage());

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (APP_DEBUG) {
            exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }

        exit('A database error occurred. Please try again later.');
    }

    return $pdo;
}

// -------------------------------------------------------------------------
// Reusable query helpers
// -------------------------------------------------------------------------

/**
 * Prepare and execute a statement.
 *
 * @param string $sql
 * @param array  $params
 * @return PDOStatement
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row or null.
 *
 * @param string $sql
 * @param array  $params
 * @return array|null
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Fetch a single scalar value.
 *
 * @param string $sql
 * @param array  $params
 * @return mixed
 */
function db_scalar(string $sql, array $params = [])
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/**
 * Insert a row and return the new primary key.
 *
 * @param string $sql
 * @param array  $params
 * @return int
 */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

/**
 * Execute an UPDATE / DELETE / generic statement and return affected rows.
 *
 * @param string $sql
 * @param array  $params
 * @return int
 */
function db_execute(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Return true if at least one row matches.
 *
 * @param string $sql
 * @param array  $params
 * @return bool
 */
function db_exists(string $sql, array $params = []): bool
{
    return db_query($sql, $params)->fetchColumn() !== false;
}

// -------------------------------------------------------------------------
// Transaction helper
// -------------------------------------------------------------------------

/**
 * Wrap a callable in a database transaction.
 *
 * Example:
 *   $newId = db_transaction(function () use ($data) {
 *       $id = db_insert('INSERT INTO ...', [...]);
 *       db_execute('INSERT INTO ...', [...]);
 *       return $id;
 *   });
 *
 * @param callable $callback
 * @return mixed  Return value of $callback.
 * @throws Throwable  Re-thrown after rollback.
 */
function db_transaction(callable $callback)
{
    $pdo = db();
    $owner = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $owner = true;
    }

    try {
        $result = $callback();

        if ($owner && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return $result;
    } catch (Throwable $e) {
        if ($owner && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[TrashWise TX] ' . $e->getMessage());
        throw $e;
    }
}