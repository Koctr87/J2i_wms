<?php
/**
 * Migration: Add sale_currency column to sale_items table
 */
require_once __DIR__ . '/config/config.php';

try {
    $db = getDB();

    // Check if column already exists
    $columns = $db->query("SHOW COLUMNS FROM sale_items LIKE 'sale_currency'")->fetchAll();

    if (empty($columns)) {
        $db->exec("ALTER TABLE sale_items ADD COLUMN sale_currency VARCHAR(3) NOT NULL DEFAULT 'CZK' AFTER unit_price");
        echo "✅ Column 'sale_currency' added to sale_items table successfully.<br>";
    } else {
        echo "ℹ️ Column 'sale_currency' already exists.<br>";
    }

    echo "<br><a href='pages/sales/new-sale.php'>← Go to Sales</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
