<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();
echo "COLUMNS IN 'sales' table:\n";
foreach ($db->query("DESCRIBE sales") as $row) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
echo "\nCOLUMNS IN 'sale_items' table:\n";
foreach ($db->query("DESCRIBE sale_items") as $row) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
