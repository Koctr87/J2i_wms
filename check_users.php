<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

echo "=== Users in database ===\n";
$users = $db->query("SELECT id, username, full_name, role FROM users ORDER BY id")->fetchAll();
if (empty($users)) {
    echo "NO USERS FOUND!\n";
} else {
    foreach ($users as $u) {
        echo "ID: {$u['id']} | Username: {$u['username']} | Name: {$u['full_name']} | Role: {$u['role']}\n";
    }
}

echo "\n=== Current Session ===\n";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
