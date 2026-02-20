<?php
/**
 * Migration: Add delivery columns to sales table
 */
require_once __DIR__ . '/config/config.php';

try {
    $db = getDB();

    // Check if columns already exist
    $columns = $db->query("SHOW COLUMNS FROM sales LIKE 'sale_delivery_cost'")->fetchAll();

    if (empty($columns)) {
        $db->exec("ALTER TABLE sales 
                   ADD COLUMN sale_delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER currency_rate_usd,
                   ADD COLUMN sale_delivery_currency VARCHAR(3) NOT NULL DEFAULT 'CZK' AFTER sale_delivery_cost");
        echo "✅ Columns 'sale_delivery_cost' and 'sale_delivery_currency' added to sales table successfully.<br>";
    } else {
        echo "ℹ️ Columns already exist.<br>";
    }

    echo "<br><a href='pages/sales/new-sale.php'>← Go to Sales</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
