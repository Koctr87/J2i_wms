<?php
/**
 * J2i Warehouse Management System
 * Application Configuration
 */

// Debug mode - set to false in production
define('DEBUG_MODE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'J2i Warehouse');
define('APP_VERSION', '1.0.0');

// Detect base URL automatically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8888';
define('APP_URL', $protocol . '://' . $host);

// Default settings
define('DEFAULT_LANGUAGE', 'cs');
define('VAT_RATE', 21); // Czech VAT rate 21%

// Supported languages
define('SUPPORTED_LANGUAGES', ['ru', 'cs', 'uk', 'en']);

// User roles
define('ROLES', [
    'director' => ['full_access' => true, 'manage_users' => true, 'view_finances' => true],
    'admin' => ['full_access' => true, 'manage_users' => false, 'view_finances' => true],
    'manager' => ['full_access' => true, 'manage_users' => false, 'view_finances' => true],
    'seller' => ['full_access' => true, 'manage_users' => false, 'view_finances' => true],
    'logist' => ['full_access' => true, 'manage_users' => false, 'view_finances' => true],
]);

// ČNB Currency API
define('CNB_API_URL', 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt');

// Timezone
date_default_timezone_set('Europe/Prague');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Load language
// Handle language switching via URL parameter
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['language'] = $_GET['lang'];
}

$current_lang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
if (!in_array($current_lang, SUPPORTED_LANGUAGES)) {
    $current_lang = DEFAULT_LANGUAGE;
}
$lang_file = __DIR__ . '/languages/' . $current_lang . '.php';
if (file_exists($lang_file)) {
    $lang = require $lang_file;
} else {
    $lang = require __DIR__ . '/languages/en.php';
}
