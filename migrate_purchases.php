<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

try {
    echo "Starting Purchases Migration...\n";

    // 1. Create purchases table
    echo "Creating 'purchases' table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `purchases` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `supplier_id` INT UNSIGNED NOT NULL,
        `invoice_number` VARCHAR(50) DEFAULT NULL,
        `purchase_date` DATE NOT NULL,
        `vat_mode` ENUM('reverse', 'marginal', 'no') NOT NULL DEFAULT 'marginal',
        `condition` ENUM('new', 'used') NOT NULL DEFAULT 'used',
        `currency` ENUM('EUR', 'USD', 'CZK') NOT NULL DEFAULT 'EUR',
        `notes` TEXT DEFAULT NULL,
        `attachment_url` VARCHAR(255) DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Add missing columns to devices table
    echo "Updating 'devices' table...\n";

    // Check if columns exist before adding
    $columns = $db->query("SHOW COLUMNS FROM devices")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('supplier_id', $columns)) {
        $db->exec("ALTER TABLE devices ADD COLUMN `supplier_id` INT UNSIGNED DEFAULT NULL AFTER `color_id` ");
        $db->exec("ALTER TABLE devices ADD FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL");
    }

    if (!in_array('purchase_id', $columns)) {
        $db->exec("ALTER TABLE devices ADD COLUMN `purchase_id` INT UNSIGNED DEFAULT NULL AFTER `supplier_id` ");
        $db->exec("ALTER TABLE devices ADD FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE SET NULL");
    }

    if (!in_array('grading', $columns)) {
        $db->exec("ALTER TABLE devices ADD COLUMN `grading` VARCHAR(10) DEFAULT 'A' AFTER `condition` ");
    }

    if (!in_array('delivery_currency', $columns)) {
        $db->exec("ALTER TABLE devices ADD COLUMN `delivery_currency` ENUM('EUR', 'USD', 'CZK') DEFAULT 'EUR' AFTER `delivery_cost` ");
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
