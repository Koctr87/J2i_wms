<?php
/**
 * J2i WMS - Debug Login Script
 * Run this in browser to diagnose login issues
 * DELETE THIS FILE AFTER USE!
 */
require_once __DIR__ . '/config/config.php';

echo "<h1>J2i WMS - Login Debug</h1>";
echo "<style>body { font-family: sans-serif; padding: 2rem; } pre { background: #f5f5f5; padding: 1rem; } .ok { color: green; } .err { color: red; }</style>";

// 1. Check database connection
echo "<h2>1. Database Connection</h2>";
try {
    $db = getDB();
    echo "<p class='ok'>✅ Connected to database</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// 2. Check users table structure
echo "<h2>2. Users Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>" . implode(", ", $columns) . "</pre>";

    // Check required columns
    $required = ['email', 'password_hash', 'first_name', 'last_name', 'role', 'is_active'];
    $missing = array_diff($required, $columns);

    if (empty($missing)) {
        echo "<p class='ok'>✅ All required columns exist</p>";
    } else {
        echo "<p class='err'>❌ Missing columns: " . implode(", ", $missing) . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Table error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Check existing users
echo "<h2>3. Existing Users</h2>";
try {
    $stmt = $db->query("SELECT id, email, first_name, last_name, role, is_active, LEFT(password_hash, 30) as hash_preview FROM users");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<p class='err'>❌ No users in database! Need to insert admin user.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Active</th><th>Hash Preview</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td>{$u['email']}</td>";
            echo "<td>{$u['first_name']} {$u['last_name']}</td>";
            echo "<td>{$u['role']}</td>";
            echo "<td>" . ($u['is_active'] ? '✅' : '❌') . "</td>";
            echo "<td><code>{$u['hash_preview']}...</code></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Query error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Test password verification
echo "<h2>4. Password Test</h2>";
$testPassword = 'admin123';
$correctHash = password_hash($testPassword, PASSWORD_DEFAULT);
echo "<p>New hash for '<b>$testPassword</b>':</p>";
echo "<pre>$correctHash</pre>";

// 5. One-click fix
echo "<h2>5. Quick Fix - Create Admin User</h2>";
if (isset($_GET['fix'])) {
    try {
        // Delete existing admin
        $db->exec("DELETE FROM users WHERE email = 'admin@j2i.cz'");

        // Create correct hash
        $hash = password_hash('admin123', PASSWORD_DEFAULT);

        // Insert new admin
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, language, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute(['admin@j2i.cz', $hash, 'Admin', 'J2i', 'director', 'ru']);

        echo "<p class='ok'>✅ Admin user created successfully!</p>";
        echo "<p><b>Email:</b> admin@j2i.cz</p>";
        echo "<p><b>Password:</b> admin123</p>";
        echo "<p><a href='pages/users/login.php'>👉 Go to Login Page</a></p>";
    } catch (Exception $e) {
        echo "<p class='err'>❌ Fix error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p><a href='?fix=1' style='padding: 1rem 2rem; background: #E74C3C; color: white; text-decoration: none; border-radius: 8px; display: inline-block;'>🔧 Click here to create/fix admin user</a></p>";
}

echo "<hr><p><small>Delete this file (debug.php) after fixing the issue!</small></p>";
