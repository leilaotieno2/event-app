<?php
/**
 * Database connection using PDO.
 *
 * SQL INJECTION DEFENSE (part 1):
 * We use PDO with prepared statements everywhere in this app.
 * PDO::ATTR_EMULATE_PREPARES is disabled so PHP sends real
 * parameterized queries to MySQL - user input is never concatenated
 * into SQL strings.
 *
 * Configuration comes from environment variables (see .env.example),
 * so credentials never need to be hard-coded or committed. DB_DRIVER
 * can be 'mysql' (production) or 'sqlite' (local/demo, no server needed).
 */
require_once __DIR__ . '/env.php';
load_env();

define('DB_DRIVER', env('DB_DRIVER', 'mysql'));
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'event_registration'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_SQLITE_PATH', env('DB_SQLITE_PATH', __DIR__ . '/../sql/event_registration.sqlite'));

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        ];

        try {
            if (DB_DRIVER === 'sqlite') {
                $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            }
        } catch (PDOException $e) {
            // Never leak DB credentials/details to the browser
            error_log('DB connection failed: ' . $e->getMessage());
            die('A system error occurred. Please try again later.');
        }
    }

    return $pdo;
}
