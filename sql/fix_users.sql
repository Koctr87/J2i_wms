-- J2i WMS - Quick Fix Script
-- Run this in phpMyAdmin if you already imported the old schema

-- Drop and recreate users table with correct structure
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `accessories`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL DEFAULT '',
    `role` ENUM('director', 'admin', 'manager', 'seller', 'logist') NOT NULL DEFAULT 'seller',
    `language` ENUM('ru', 'cs', 'uk', 'en') NOT NULL DEFAULT 'cs',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert admin user with correct password hash for 'admin123'
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `language`) VALUES
('admin@j2i.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'J2i', 'director', 'ru');

-- Recreate devices table with correct foreign key
CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `memory_id` INT UNSIGNED DEFAULT NULL,
    `color_id` INT UNSIGNED DEFAULT NULL,
    `purchase_date` DATE NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `quantity_available` INT NOT NULL DEFAULT 1,
    `invoice_in` VARCHAR(50) DEFAULT NULL,
    `invoice_out` VARCHAR(50) DEFAULT NULL,
    `purchase_price` DECIMAL(10,2) NOT NULL,
    `purchase_currency` ENUM('EUR', 'USD', 'CZK') NOT NULL DEFAULT 'EUR',
    `delivery_cost` DECIMAL(10,2) DEFAULT 0,
    `wholesale_price` DECIMAL(10,2) DEFAULT NULL,
    `retail_price` DECIMAL(10,2) DEFAULT NULL,
    `imei` VARCHAR(20) DEFAULT NULL,
    `vat_mode` ENUM('reverse', 'marginal', 'no') NOT NULL DEFAULT 'marginal',
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('in_stock', 'reserved', 'sold', 'returned') NOT NULL DEFAULT 'in_stock',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`memory_id`) REFERENCES `memory_options`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`color_id`) REFERENCES `color_options`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_imei` (`imei`),
    INDEX `idx_purchase_date` (`purchase_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate accessories
CREATE TABLE IF NOT EXISTS `accessories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `purchase_date` DATE NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `quantity_available` INT NOT NULL DEFAULT 1,
    `purchase_price` DECIMAL(10,2) NOT NULL,
    `purchase_currency` ENUM('EUR', 'USD', 'CZK') NOT NULL DEFAULT 'CZK',
    `selling_price` DECIMAL(10,2) DEFAULT NULL,
    `repair_comment` TEXT DEFAULT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('in_stock', 'sold', 'used') NOT NULL DEFAULT 'in_stock',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`type_id`) REFERENCES `accessory_types`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate sales
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `sale_date` DATE NOT NULL,
    `invoice_number` VARCHAR(50) DEFAULT NULL,
    `currency_rate_eur` DECIMAL(10,4) DEFAULT NULL,
    `currency_rate_usd` DECIMAL(10,4) DEFAULT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `vat_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_sale_date` (`sale_date`),
    INDEX `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `accessory_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `vat_mode` ENUM('reverse', 'marginal', 'no') NOT NULL,
    `vat_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`accessory_id`) REFERENCES `accessories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate activity_log
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Database fixed! Login: admin@j2i.cz / admin123' AS Result;
