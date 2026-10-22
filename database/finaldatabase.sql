-- Maruf Traders: Cement Dealership Management & Accounting Software Database Schema
-- Location: সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ, Bangladesh
-- Currency: BDT (৳)
-- FULL CONSOLIDATED SCHEMA - includes retailers soft-delete + company_period_commissions

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `company_period_commissions`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `monthly_closings`;
DROP TABLE IF EXISTS `commission_rules`;
DROP TABLE IF EXISTS `monthly_targets`;
DROP TABLE IF EXISTS `stock_adjustments`;
DROP TABLE IF EXISTS `stock_ledger`;
DROP TABLE IF EXISTS `company_payments`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `expense_categories`;
DROP TABLE IF EXISTS `advance_usage`;
DROP TABLE IF EXISTS `advances`;
DROP TABLE IF EXISTS `collections`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `cement_receive_items`;
DROP TABLE IF EXISTS `cement_receives`;
DROP TABLE IF EXISTS `company_ledger`;
DROP TABLE IF EXISTS `retailer_ledger`;
DROP TABLE IF EXISTS `retailers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `companies`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `user_activity_logs`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. ROLES TABLE
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PERMISSIONS TABLE
CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `module` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ROLE_PERMISSIONS TABLE
CREATE TABLE `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. USERS TABLE
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `last_login` DATETIME NULL,
    `last_ip` VARCHAR(45) NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USER ACTIVITY LOGS
CREATE TABLE `user_activity_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. SETTINGS TABLE
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `group_name` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. COMPANIES / SUPPLIERS TABLE
CREATE TABLE `companies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `mobile` VARCHAR(30) NOT NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `opening_payable` DECIMAL(15,2) DEFAULT 0.00,
    `current_payable` DECIMAL(15,2) DEFAULT 0.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company_code` (`company_code`),
    INDEX `idx_company_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CEMENT PRODUCTS TABLE
CREATE TABLE `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `product_code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `brand` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(20) DEFAULT 'Bag',
    `bag_size_kg` DECIMAL(8,2) DEFAULT 50.00,
    `default_purchase_price` DECIMAL(15,2) DEFAULT 0.00,
    `default_sale_price` DECIMAL(15,2) DEFAULT 0.00,
    `opening_stock` INT DEFAULT 0,
    `current_stock` INT DEFAULT 0,
    `low_stock_limit` INT DEFAULT 100,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_product_code` (`product_code`),
    INDEX `idx_product_company` (`company_id`),
    CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. RETAILERS TABLE (includes soft-delete columns: deleted_at, deleted_by)
CREATE TABLE `retailers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `retailer_code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `mobile` VARCHAR(30) NOT NULL,
    `address` TEXT NULL,
    `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
    `advance_balance` DECIMAL(15,2) DEFAULT 0.00,
    `current_due` DECIMAL(15,2) DEFAULT 0.00,
    `credit_limit` DECIMAL(15,2) DEFAULT 100000.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
    INDEX `idx_retailer_code` (`retailer_code`),
    INDEX `idx_retailer_mobile` (`mobile`),
    INDEX `idx_retailer_status` (`status`),
    INDEX `idx_retailer_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_retailers_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. RETAILER LEDGER TABLE
CREATE TABLE `retailer_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `retailer_id` INT UNSIGNED NOT NULL,
    `transaction_date` DATE NOT NULL,
    `transaction_type` ENUM('OPENING', 'SALE', 'COLLECTION', 'ADVANCE', 'ADVANCE_USED', 'SALE_CANCEL', 'ADJUSTMENT', 'REVERSAL') NOT NULL,
    `reference_id` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `debit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Increases Due (e.g. Sale)',
    `credit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Decreases Due (e.g. Collection/Payment)',
    `running_balance` DECIMAL(15,2) NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rl_retailer_date` (`retailer_id`, `transaction_date`),
    INDEX `idx_rl_ref` (`reference_id`),
    CONSTRAINT `fk_rl_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_rl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. COMPANY LEDGER TABLE
CREATE TABLE `company_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `transaction_date` DATE NOT NULL,
    `transaction_type` ENUM('OPENING', 'RECEIVE', 'PAYMENT', 'RECEIVE_CANCEL', 'PAYMENT_CANCEL', 'ADJUSTMENT') NOT NULL,
    `reference_id` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `debit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Purchase Cost Increases Payable',
    `credit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Payments Decrease Payable',
    `running_balance` DECIMAL(15,2) NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cl_company_date` (`company_id`, `transaction_date`),
    INDEX `idx_cl_ref` (`reference_id`),
    CONSTRAINT `fk_cl_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. CEMENT RECEIVES / PURCHASES TABLE
CREATE TABLE `cement_receives` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `receive_code` VARCHAR(50) NOT NULL UNIQUE,
    `company_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `receive_date` DATE NOT NULL,
    `quantity` INT NOT NULL,
    `purchase_rate` DECIMAL(15,2) NOT NULL,
    `purchase_value` DECIMAL(15,2) NOT NULL,
    `transport_cost` DECIMAL(15,2) DEFAULT 0.00,
    `loading_cost` DECIMAL(15,2) DEFAULT 0.00,
    `other_cost` DECIMAL(15,2) DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) NOT NULL,
    `unit_cost_basis` DECIMAL(15,2) NOT NULL COMMENT 'Total cost divided by qty',
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `payable_amount` DECIMAL(15,2) NOT NULL,
    `payment_status` ENUM('paid', 'partial', 'due') DEFAULT 'due',
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `challan_no` VARCHAR(100) NULL,
    `vehicle_no` VARCHAR(100) NULL,
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cr_date` (`receive_date`),
    INDEX `idx_cr_company` (`company_id`),
    INDEX `idx_cr_product` (`product_id`),
    CONSTRAINT `fk_cr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cr_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cr_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. SALES TABLE
CREATE TABLE `sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    `retailer_id` INT UNSIGNED NOT NULL,
    `sale_date` DATE NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `discount` DECIMAL(15,2) DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL,
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `due_amount` DECIMAL(15,2) NOT NULL,
    `advance_deducted` DECIMAL(15,2) DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `payment_status` ENUM('paid', 'partial', 'due') DEFAULT 'due',
    `delivery_address` TEXT NULL,
    `driver_info` VARCHAR(150) NULL,
    `total_cogs` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Cost of goods sold based on purchase price basis',
    `gross_profit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'total_amount - total_cogs',
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sales_invoice` (`invoice_no`),
    INDEX `idx_sales_date` (`sale_date`),
    INDEX `idx_sales_retailer` (`retailer_id`),
    CONSTRAINT `fk_sales_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. SALE ITEMS TABLE
CREATE TABLE `sale_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `company_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL,
    `unit_cost` DECIMAL(15,2) NOT NULL COMMENT 'Purchase cost basis at time of sale',
    `total_price` DECIMAL(15,2) NOT NULL,
    `item_cogs` DECIMAL(15,2) NOT NULL,
    `item_profit` DECIMAL(15,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_si_sale` (`sale_id`),
    INDEX `idx_si_product` (`product_id`),
    CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_si_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. COLLECTIONS TABLE
CREATE TABLE `collections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `collection_code` VARCHAR(50) NOT NULL UNIQUE,
    `retailer_id` INT UNSIGNED NOT NULL,
    `sale_id` INT UNSIGNED NULL,
    `collection_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `bank_account` VARCHAR(100) NULL,
    `transaction_ref` VARCHAR(100) NULL,
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_col_date` (`collection_date`),
    INDEX `idx_col_retailer` (`retailer_id`),
    CONSTRAINT `fk_col_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_col_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_col_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_col_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. ADVANCES TABLE
CREATE TABLE `advances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `advance_code` VARCHAR(50) NOT NULL UNIQUE,
    `retailer_id` INT UNSIGNED NOT NULL,
    `advance_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `used_amount` DECIMAL(15,2) DEFAULT 0.00,
    `remaining_amount` DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `transaction_ref` VARCHAR(100) NULL,
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_adv_date` (`advance_date`),
    INDEX `idx_adv_retailer` (`retailer_id`),
    CONSTRAINT `fk_adv_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_adv_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_adv_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. ADVANCE USAGE TABLE
CREATE TABLE `advance_usage` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `advance_id` INT UNSIGNED NOT NULL,
    `sale_id` INT UNSIGNED NOT NULL,
    `used_amount` DECIMAL(15,2) NOT NULL,
    `used_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_au_advance` FOREIGN KEY (`advance_id`) REFERENCES `advances` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_au_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. EXPENSE CATEGORIES TABLE
CREATE TABLE `expense_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. EXPENSES TABLE
CREATE TABLE `expenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `expense_code` VARCHAR(50) NOT NULL UNIQUE,
    `category_id` INT UNSIGNED NOT NULL,
    `expense_date` DATE NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `paid_to` VARCHAR(100) NULL,
    `voucher_no` VARCHAR(100) NULL,
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exp_date` (`expense_date`),
    INDEX `idx_exp_category` (`category_id`),
    CONSTRAINT `fk_exp_cat` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_exp_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. COMPANY PAYMENTS TABLE
CREATE TABLE `company_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_code` VARCHAR(50) NOT NULL UNIQUE,
    `company_id` INT UNSIGNED NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Bank',
    `reference_no` VARCHAR(100) NULL,
    `bank_name` VARCHAR(100) NULL,
    `status` ENUM('active', 'cancelled') DEFAULT 'active',
    `cancel_reason` TEXT NULL,
    `cancelled_by` INT UNSIGNED NULL,
    `cancelled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cp_date` (`payment_date`),
    INDEX `idx_cp_company` (`company_id`),
    CONSTRAINT `fk_cp_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cp_cancel_user` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. STOCK LEDGER TABLE
CREATE TABLE `stock_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `company_id` INT UNSIGNED NOT NULL,
    `transaction_date` DATE NOT NULL,
    `transaction_type` ENUM('OPENING', 'RECEIVE', 'SALE', 'ADJUSTMENT_ADD', 'ADJUSTMENT_SUB', 'SALE_CANCEL', 'RECEIVE_CANCEL') NOT NULL,
    `reference_id` VARCHAR(50) NOT NULL,
    `stock_in` INT DEFAULT 0,
    `stock_out` INT DEFAULT 0,
    `running_balance` INT NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sl_prod_date` (`product_id`, `transaction_date`),
    INDEX `idx_sl_company` (`company_id`),
    CONSTRAINT `fk_sl_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sl_comp` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. STOCK ADJUSTMENTS TABLE
CREATE TABLE `stock_adjustments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `adjustment_code` VARCHAR(50) NOT NULL UNIQUE,
    `product_id` INT UNSIGNED NOT NULL,
    `adjustment_date` DATE NOT NULL,
    `adjustment_type` ENUM('Damaged', 'Lost', 'Expired', 'Correction_Plus', 'Correction_Minus', 'Other') NOT NULL,
    `action` ENUM('increase', 'decrease') NOT NULL,
    `quantity` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sa_date` (`adjustment_date`),
    INDEX `idx_sa_prod` (`product_id`),
    CONSTRAINT `fk_sa_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. MONTHLY TARGETS TABLE
CREATE TABLE `monthly_targets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `target_code` VARCHAR(50) NOT NULL UNIQUE,
    `company_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `target_month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    `target_year` SMALLINT UNSIGNED NOT NULL,
    `target_quantity` INT NOT NULL DEFAULT 0,
    `actual_sales_quantity` INT NOT NULL DEFAULT 0,
    `achievement_percentage` DECIMAL(8,2) DEFAULT 0.00,
    `commission_mode` VARCHAR(50) DEFAULT 'proportional',
    `commission_rate` DECIMAL(10,2) DEFAULT 0.00,
    `fixed_commission_per_bag` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimated_commission` DECIMAL(15,2) DEFAULT 0.00,
    `status` ENUM('active', 'completed', 'expired') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_company_prod_month_year` (`company_id`, `product_id`, `target_month`, `target_year`),
    INDEX `idx_mt_month_year` (`target_month`, `target_year`),
    CONSTRAINT `fk_mt_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_mt_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. COMMISSION RULES TABLE
CREATE TABLE `commission_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NULL,
    `rule_name` VARCHAR(150) NOT NULL,
    `min_achievement_percent` DECIMAL(5,2) DEFAULT 0.00,
    `max_achievement_percent` DECIMAL(5,2) DEFAULT 999.99,
    `commission_type` ENUM('proportional', 'fixed_per_bag', 'percentage_sales', 'fixed_amount') DEFAULT 'proportional',
    `full_rate_per_bag` DECIMAL(10,2) DEFAULT 38.00,
    `percentage_rate` DECIMAL(5,2) DEFAULT 0.00,
    `fixed_amount` DECIMAL(15,2) DEFAULT 0.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_crules_comp` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_crules_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. MONTHLY CLOSINGS TABLE
CREATE TABLE `monthly_closings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `closing_month` TINYINT UNSIGNED NOT NULL,
    `closing_year` SMALLINT UNSIGNED NOT NULL,
    `status` ENUM('closed', 'reopened') DEFAULT 'closed',
    `closed_by` INT UNSIGNED NOT NULL,
    `closed_at` DATETIME NOT NULL,
    `reopened_by` INT UNSIGNED NULL,
    `reopened_at` DATETIME NULL,
    `closing_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_closing_period` (`closing_month`, `closing_year`),
    CONSTRAINT `fk_mc_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_mc_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. AUDIT LOGS TABLE
CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `module` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `reference_id` VARCHAR(100) NULL,
    `old_data` LONGTEXT NULL,
    `new_data` LONGTEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_al_module` (`module`),
    INDEX `idx_al_action` (`action`),
    INDEX `idx_al_date` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. COMPANY PERIOD COMMISSIONS TABLE (3-Month / Yearly, per-bag, per-company)
-- Separate from the monthly proportional target-commission engine (tables 23/24).
-- These sit as PENDING (auto-recalculated live from sale_items) and never affect
-- Net Profit until explicitly marked "received" from the UI - at which point the
-- frozen amount is picked up by the P&L report for the month it was received in.
CREATE TABLE `company_period_commissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `commission_type` ENUM('quarterly', 'yearly') NOT NULL,
    `period_label` VARCHAR(100) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `rate_per_bag` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_bags` INT NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'received') NOT NULL DEFAULT 'pending',
    `received_date` DATE NULL DEFAULT NULL,
    `received_by` INT UNSIGNED NULL DEFAULT NULL,
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cpc_company` (`company_id`),
    INDEX `idx_cpc_status` (`status`),
    INDEX `idx_cpc_dates` (`start_date`, `end_date`),
    INDEX `idx_cpc_received_date` (`received_date`),
    CONSTRAINT `fk_cpc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cpc_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cpc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `other_incomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `income_date` DATE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` ENUM('old_commission','rent','asset_sale','interest','other') NOT NULL DEFAULT 'other',
  `company_id` INT UNSIGNED NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `notes` TEXT NULL,
  `status` ENUM('active','deleted') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_income_date` (`income_date`),
  KEY `idx_status` (`status`),
  KEY `idx_company_id` (`company_id`),
  CONSTRAINT `fk_other_incomes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ============================================================
-- OPTIONAL: register the permission for the new Period Commissions module.
-- Super Admin already has full access regardless of this. Uncomment and
-- adjust role_id if you want to grant it to another role (e.g. Manager).
-- ============================================================
-- INSERT INTO `permissions` (`name`, `slug`, `module`, `description`)
-- VALUES ('Manage Period Commissions', 'commission_periods.manage', 'commission_periods', 'Create and receive 3-month/yearly per-bag company commissions');
--
-- INSERT INTO `role_permissions` (`role_id`, `permission_id`)
-- SELECT 1, id FROM `permissions` WHERE `slug` = 'commission_periods.manage';