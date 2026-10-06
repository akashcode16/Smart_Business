-- ========================================================
-- ONLINE SMART BUSINESS MANAGEMENT SYSTEM
-- Database: smart_business
-- ========================================================

CREATE DATABASE IF NOT EXISTS `smart_business` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_business`;

-- --------------------------------------------------------
-- Table: roles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure default roles exist without overwriting
INSERT IGNORE INTO `roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Stockiest'),
(3, 'Distributor'),
(4, 'Retailer');

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `role_id` INT(11) NOT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `google_id` VARCHAR(100) DEFAULT NULL,
    `google_email` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_google_id` (`google_id`),
    UNIQUE KEY `unique_google_email` (`google_email`),
    KEY `fk_users_role` (`role_id`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` VARCHAR(50) NOT NULL UNIQUE,
    `product_name` VARCHAR(150) NOT NULL,
    `category_id` INT(11) NOT NULL,
    `brand` VARCHAR(100) DEFAULT NULL,
    `unit` VARCHAR(30) NOT NULL DEFAULT 'Piece',
    `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `minimum_stock_level` INT(11) NOT NULL DEFAULT 5,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_products_category` (`category_id`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: suppliers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `supplier_id` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_name` VARCHAR(100) NOT NULL,
    `company_name` VARCHAR(150) DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: customers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` VARCHAR(50) NOT NULL UNIQUE,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: stock
-- Tracks current stock per party (Stockiest, Distributor, Retailer)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_product` (`user_id`, `product_id`),
    KEY `fk_stock_user` (`user_id`),
    KEY `fk_stock_product` (`product_id`),
    CONSTRAINT `fk_stock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: purchases
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_id` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `purchase_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Paid',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_purchases_supplier` (`supplier_id`),
    KEY `fk_purchases_user` (`user_id`),
    CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_purchases_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: purchase_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `purchase_price` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_purchase_items_purchase` (`purchase_id`),
    KEY `fk_purchase_items_product` (`product_id`),
    CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: stock_transfers
-- (Stockiest -> Distributor -> Retailer)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_transfers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transfer_id` VARCHAR(50) NOT NULL UNIQUE,
    `from_user_id` INT(11) NOT NULL,
    `to_user_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `transfer_date` DATE NOT NULL,
    `status` ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Completed',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_transfers_from_user` (`from_user_id`),
    KEY `fk_transfers_to_user` (`to_user_id`),
    KEY `fk_transfers_product` (`product_id`),
    CONSTRAINT `fk_transfers_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_transfers_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_transfers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: sales
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT(11) DEFAULT NULL,
    `user_id` INT(11) NOT NULL,
    `sale_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('Cash', 'Card', 'UPI', 'Bank Transfer', 'Credit') DEFAULT 'Cash',
    `payment_status` ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Paid',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_sales_customer` (`customer_id`),
    KEY `fk_sales_user` (`user_id`),
    CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: sale_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `selling_price` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_sale_items_sale` (`sale_id`),
    KEY `fk_sale_items_product` (`product_id`),
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `payment_id` VARCHAR(50) NOT NULL UNIQUE,
    `transaction_type` ENUM('Purchase', 'Sale') NOT NULL,
    `reference_id` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` ENUM('Cash', 'Card', 'UPI', 'Bank Transfer', 'Credit') NOT NULL DEFAULT 'Cash',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payments_reference` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
