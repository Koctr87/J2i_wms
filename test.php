<?php
/**
 * Simple test page to check PHP is working
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Test Page</h1>";
echo "<p>PHP is working! Version: " . phpversion() . "</p>";

echo "<h2>Testing includes...</h2>";

// Test 1: Database config
echo "<h3>1. Database Config</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "<p style='color:green'>✅ database.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ database.php error: " . $e->getMessage() . "</p>";
}

// Test 2: Functions
echo "<h3>2. Functions</h3>";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<p style='color:green'>✅ functions.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ functions.php error: " . $e->getMessage() . "</p>";
}

// Test 3: Auth
echo "<h3>3. Auth</h3>";
try {
    require_once __DIR__ . '/includes/auth.php';
    echo "<p style='color:green'>✅ auth.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ auth.php error: " . $e->getMessage() . "</p>";
}

// Test 4: Database connection
echo "<h3>4. Database Connection</h3>";
try {
    $db = getDB();
    echo "<p style='color:green'>✅ Database connected</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 5: Extensions
echo "<h3>5. Extensions</h3>";
echo "mbstring: " . (extension_loaded('mbstring') ? "<span class='ok'>✅ Loaded</span>" : "<span class='err'>❌ Not loaded</span>") . "<br>";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? "<span class='ok'>✅ Loaded</span>" : "<span class='err'>❌ Not loaded</span>") . "<br>";

// Test 6: Auth Logic
echo "<h3>6. Auth Logic</h3>";
try {
    // Simulate login
    $_SESSION['user_id'] = 2; // Admin ID
    $_SESSION['user_role'] = 'director';
    $_SESSION['language'] = 'ru';

    echo "Current Language: " . getCurrentLanguage() . "<br>";
    $user = getCurrentUser();
    echo "Current User: " . ($user ? $user['email'] : 'None') . "<br>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ Logic error: " . $e->getMessage() . "</p>";
}

// Test 7: Header Include
echo "<h3>7. Header Include</h3>";
try {
    // We need to buffer output because header sends HTML
    ob_start();
    require_once __DIR__ . '/includes/header.php';
    ob_end_clean();
    echo "<p class='ok'>✅ header.php included successfully</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ header.php error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr><p><a href='pages/users/login.php'>Go to Login</a></p>";
echo "<style>.ok {color:green} .err {color:red; font-weight:bold}</style>";
