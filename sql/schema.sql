-- J2i Warehouse Management System - Database Schema
-- MySQL 5.7+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Activity Log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users & Authentication
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
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

-- Default admin user (password: admin123)
-- Hash generated with: password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `language`) VALUES
('admin@j2i.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'J2i', 'director', 'ru');

-- --------------------------------------------------------
-- Brands
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `logo_url` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brands` (`name`) VALUES
('Apple'), ('Samsung'), ('Xiaomi'), ('Huawei'), ('Google'),
('Lenovo'), ('Dell'), ('HP'), ('Acer'), ('MSI'), ('ASUS'),
('Dyson'), ('Microsoft'), ('Sony'), ('Nintendo');

-- --------------------------------------------------------
-- Categories
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name_ru` VARCHAR(50) NOT NULL,
    `name_cs` VARCHAR(50) NOT NULL,
    `name_uk` VARCHAR(50) NOT NULL,
    `name_en` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'box',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name_ru`, `name_cs`, `name_uk`, `name_en`, `icon`) VALUES
('Телефоны', 'Telefony', 'Телефони', 'Phones', 'smartphone'),
('Планшеты', 'Tablety', 'Планшети', 'Tablets', 'tablet'),
('Ноутбуки', 'Notebooky', 'Ноутбуки', 'Laptops', 'laptop'),
('Часы', 'Hodinky', 'Годинники', 'Watches', 'watch'),
('Аксессуары', 'Příslušenství', 'Аксесуари', 'Accessories', 'cable'),
('Игровые консоли', 'Herní konzole', 'Ігрові консолі', 'Gaming Consoles', 'gamepad'),
('Бытовая техника', 'Domácí spotřebiče', 'Побутова техніка', 'Home Appliances', 'home');

-- --------------------------------------------------------
-- Products (Models)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `brand_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_product` (`brand_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Memory Options
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `memory_options` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `size` VARCHAR(20) NOT NULL UNIQUE,
    `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `memory_options` (`size`, `sort_order`) VALUES
('N/A', 0), ('32GB', 1), ('64GB', 2), ('128GB', 3), ('256GB', 4),
('512GB', 5), ('1TB', 6), ('2TB', 7);

-- --------------------------------------------------------
-- Color Options
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `color_options` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name_ru` VARCHAR(50) NOT NULL,
    `name_cs` VARCHAR(50) NOT NULL,
    `name_uk` VARCHAR(50) NOT NULL,
    `name_en` VARCHAR(50) NOT NULL,
    `hex_code` VARCHAR(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `color_options` (`name_ru`, `name_cs`, `name_uk`, `name_en`, `hex_code`) VALUES
('Чёрный', 'Černá', 'Чорний', 'Black', '#000000'),
('Белый', 'Bílá', 'Білий', 'White', '#FFFFFF'),
('Серый', 'Šedá', 'Сірий', 'Gray', '#808080'),
('Серебристый', 'Stříbrná', 'Сріблястий', 'Silver', '#C0C0C0'),
('Золотой', 'Zlatá', 'Золотий', 'Gold', '#FFD700'),
('Синий', 'Modrá', 'Синій', 'Blue', '#0000FF'),
('Красный', 'Červená', 'Червоний', 'Red', '#FF0000'),
('Зелёный', 'Zelená', 'Зелений', 'Green', '#008000'),
('Фиолетовый', 'Fialová', 'Фіолетовий', 'Purple', '#800080'),
('Розовый', 'Růžová', 'Рожевий', 'Pink', '#FFC0CB');

-- --------------------------------------------------------
-- Devices (Inventory Items)
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Accessory Types
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `accessory_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name_ru` VARCHAR(50) NOT NULL,
    `name_cs` VARCHAR(50) NOT NULL,
    `name_uk` VARCHAR(50) NOT NULL,
    `name_en` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `accessory_types` (`name_ru`, `name_cs`, `name_uk`, `name_en`) VALUES
('Кабель зарядки', 'Nabíjecí kabel', 'Кабель зарядки', 'Charging Cable'),
('Транспортировочная коробка', 'Přepravní krabice', 'Транспортувальна коробка', 'Shipping Box'),
('Упаковочная коробка', 'Balicí krabice', 'Пакувальна коробка', 'Packaging Box'),
('Этикетка', 'Štítek', 'Етикетка', 'Label'),
('Скрепка SIM', 'SIM jehla', 'Скрепка SIM', 'SIM Eject Tool'),
('Ремонт', 'Oprava', 'Ремонт', 'Repair');

-- --------------------------------------------------------
-- Accessories
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Clients (Counterparties)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(200) NOT NULL,
    `ico` VARCHAR(20) DEFAULT NULL,
    `dic` VARCHAR(20) DEFAULT NULL,
    `legal_address` TEXT DEFAULT NULL,
    `warehouse_address` TEXT DEFAULT NULL,
    `manager_name` VARCHAR(100) DEFAULT NULL,
    `manager_phone` VARCHAR(30) DEFAULT NULL,
    `manager_email` VARCHAR(100) DEFAULT NULL,
    `warehouse_contact_name` VARCHAR(100) DEFAULT NULL,
    `warehouse_contact_phone` VARCHAR(30) DEFAULT NULL,
    `warehouse_contact_email` VARCHAR(100) DEFAULT NULL,
    `same_contact` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_ico` (`ico`),
    INDEX `idx_company` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Sales
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `sale_date` DATE NOT NULL,
    `invoice_number` VARCHAR(50) DEFAULT NULL,
    `currency_rate_eur` DECIMAL(10,4) DEFAULT NULL,
    `currency_rate_usd` DECIMAL(10,4) DEFAULT NULL,
    `sale_delivery_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `sale_delivery_currency` VARCHAR(3) NOT NULL DEFAULT 'CZK',
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

-- --------------------------------------------------------
-- Sale Items
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `accessory_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `sale_currency` VARCHAR(3) NOT NULL DEFAULT 'CZK',
    `vat_mode` ENUM('reverse', 'marginal', 'no') NOT NULL,
    `vat_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`accessory_id`) REFERENCES `accessories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Currency Rates Cache
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `currency_rates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rate_date` DATE NOT NULL,
    `currency_code` VARCHAR(3) NOT NULL,
    `rate` DECIMAL(10,4) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_rate` (`rate_date`, `currency_code`),
    INDEX `idx_date` (`rate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Activity Log
-- --------------------------------------------------------

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

SET FOREIGN_KEY_CHECKS = 1;
