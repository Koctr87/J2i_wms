<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();
try {
    $stmt = $db->query("SHOW TABLES LIKE 'supplier_comments'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "Table exists\n";
        $stmt = $db->query("SELECT COUNT(*) FROM supplier_comments");
        echo "Count: " . $stmt->fetchColumn() . "\n";
    } else {
        echo "Table does NOT exist\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
