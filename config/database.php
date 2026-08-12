<?php
/**
 * Database connection using PDO.
 *
 * SQL INJECTION DEFENSE (part 1):
 * We use PDO with prepared statements everywhere in this app.
 * PDO::ATTR_EMULATE_PREPARES is disabled so PHP sends real
 * parameterized queries to MySQL - user input is never concatenated
 * into SQL strings.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'event_registration');
define('DB_USER', 'root');       // change for your environment
define('DB_PASS', '');           // change for your environment

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never leak DB credentials/details to the browser
            error_log('DB connection failed: ' . $e->getMessage());
            die('A system error occurred. Please try again later.');
        }
    }

    return $pdo;
}
