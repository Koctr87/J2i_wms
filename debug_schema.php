<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

function dumpTable($db, $table)
{
    echo "--- Table: $table ---\n";
    $stmt = $db->query("DESCRIBE `$table` ");
    foreach ($stmt->fetchAll() as $row) {
        printf("%-20s %-20s %-10s %-10s %-10s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default']);
    }
    echo "\n";
}

header('Content-Type: text/plain');
dumpTable($db, 'devices');
dumpTable($db, 'purchases');
dumpTable($db, 'users');
