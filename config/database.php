<?php
/**
 * J2i Warehouse Management System
 * Database Configuration
 */

// Database credentials - CHANGE THESE FOR YOUR ENVIRONMENT
// For MAMP: DB_PORT = 8889, DB_USER = root, DB_PASS = root
// For Docker: DB_HOST = db, DB_PORT = 3306

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '8889');  // MAMP default, change to 3306 for standard MySQL
define('DB_NAME', getenv('DB_NAME') ?: 'j2i_wms');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');  // MAMP default password
define('DB_CHARSET', 'utf8mb4');

// PDO connection options
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

/**
 * Get database connection
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        global $pdo_options;
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
        } catch (PDOException $e) {
            // Log error and show user-friendly message
            error_log("Database connection failed: " . $e->getMessage());
            die("Chyba připojení k databázi / Database connection error");
        }
    }

    return $pdo;
}
