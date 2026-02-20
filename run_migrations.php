<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

// Add missing columns if they don't exist
$migrations = [
    "ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS item_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS item_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK'",
    "ALTER TABLE sales ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL"
];

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
        echo "Success: $sql\n";
    } catch (Exception $e) {
        // Fallback for older MySQL versions that don't support IF NOT EXISTS in ALTER TABLE
        echo "Trying manual check for: $sql\n";
        if (strpos($sql, "item_delivery_cost") !== false) {
            try {
                $db->query("SELECT item_delivery_cost FROM sale_items LIMIT 1");
            } catch (Exception $e) {
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0");
            }
        }
        if (strpos($sql, "item_delivery_currency") !== false) {
            try {
                $db->query("SELECT item_delivery_currency FROM sale_items LIMIT 1");
            } catch (Exception $e) {
                $db->exec("ALTER TABLE sale_items ADD COLUMN item_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK'");
            }
        }
        if (strpos($sql, "attachment_path") !== false) {
            try {
                $db->query("SELECT attachment_path FROM sales LIMIT 1");
            } catch (Exception $e) {
                $db->exec("ALTER TABLE sales ADD COLUMN attachment_path VARCHAR(255) NULL");
            }
        }
    }
}
