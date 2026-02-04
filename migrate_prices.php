<?php
require_once __DIR__ . '/config/config.php';
$db = getDB();

try {
    echo "Starting migration...\n";

    // 1. Add condition to devices
    echo "Adding 'condition' to devices table...\n";
    $db->exec("ALTER TABLE devices ADD COLUMN `condition` ENUM('new', 'used') NOT NULL DEFAULT 'used' AFTER `color_id` ");

    // 2. Create product_prices table
    echo "Creating product_prices table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `product_prices` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT UNSIGNED NOT NULL,
        `memory_id` INT UNSIGNED NOT NULL,
        `condition` ENUM('new', 'used') NOT NULL,
        `vat_mode` ENUM('reverse', 'marginal') NOT NULL,
        `wholesale_price` DECIMAL(10,2) DEFAULT 0,
        `retail_price` DECIMAL(10,2) DEFAULT 0,
        `currency` ENUM('EUR', 'USD', 'CZK') NOT NULL DEFAULT 'CZK',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`memory_id`) REFERENCES `memory_options`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_price` (`product_id`, `memory_id`, `condition`, `vat_mode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
