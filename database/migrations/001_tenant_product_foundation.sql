-- Additive migration: legacy rows belong to tenant 1 / Cement. Requires MySQL 8.0.29+ for IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('trial','active','grace_period','expired','suspended','cancelled') NOT NULL DEFAULT 'trial',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Dhaka',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tenants (id, name, slug, status) VALUES (1, 'Maruf Traders', 'maruf-traders', 'active');

CREATE TABLE IF NOT EXISTS product_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(30) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_category_tenant_code (tenant_id, code),
  UNIQUE KEY uq_category_tenant_name (tenant_id, name),
  KEY idx_category_tenant_status (tenant_id, status),
  CONSTRAINT fk_category_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  name VARCHAR(50) NOT NULL,
  code VARCHAR(20) NOT NULL,
  allows_fraction TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_unit_tenant_code (tenant_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO product_categories (id, tenant_id, name, code) VALUES (1, 1, 'Cement', 'CEMENT');
INSERT IGNORE INTO units (id, tenant_id, name, code, allows_fraction) VALUES
 (1, NULL, 'Bag', 'BAG', 1), (2, NULL, 'KG', 'KG', 1), (3, NULL, 'Ton', 'TON', 1),
 (4, NULL, 'Piece', 'PIECE', 1), (5, NULL, 'Box', 'BOX', 1), (6, NULL, 'CFT', 'CFT', 1),
 (7, NULL, 'Liter', 'LITER', 1), (8, NULL, 'Packet', 'PACKET', 1), (9, NULL, 'Carton', 'CARTON', 1), (10, NULL, 'Tin', 'TIN', 1);

ALTER TABLE users ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_users_tenant (tenant_id);
ALTER TABLE companies ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD COLUMN IF NOT EXISTS company_type VARCHAR(50) NOT NULL DEFAULT 'brand', ADD KEY IF NOT EXISTS idx_companies_tenant (tenant_id);
ALTER TABLE products ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED NULL AFTER company_id, ADD COLUMN IF NOT EXISTS description TEXT NULL, MODIFY COLUMN current_stock DECIMAL(15,3) NOT NULL DEFAULT 0, MODIFY COLUMN opening_stock DECIMAL(15,3) NOT NULL DEFAULT 0, MODIFY COLUMN low_stock_limit DECIMAL(15,3) NOT NULL DEFAULT 100, ADD KEY IF NOT EXISTS idx_products_tenant_company_category (tenant_id, company_id, category_id);
ALTER TABLE retailers ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD COLUMN IF NOT EXISTS customer_type VARCHAR(50) NOT NULL DEFAULT 'Retailer', ADD KEY IF NOT EXISTS idx_retailers_tenant (tenant_id);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_sales_tenant_date (tenant_id, sale_date);
ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, MODIFY COLUMN quantity DECIMAL(15,3) NOT NULL, ADD KEY IF NOT EXISTS idx_sale_items_tenant_product (tenant_id, product_id);
ALTER TABLE cement_receives ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, MODIFY COLUMN quantity DECIMAL(15,3) NOT NULL, ADD KEY IF NOT EXISTS idx_receives_tenant_date (tenant_id, receive_date);
ALTER TABLE collections ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_collections_tenant_date (tenant_id, collection_date);
ALTER TABLE advances ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_advances_tenant_date (tenant_id, advance_date);
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_expenses_tenant_date (tenant_id, expense_date);
ALTER TABLE company_payments ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY IF NOT EXISTS idx_company_payments_tenant_date (tenant_id, payment_date);
ALTER TABLE stock_ledger ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, MODIFY COLUMN stock_in DECIMAL(15,3) NOT NULL DEFAULT 0, MODIFY COLUMN stock_out DECIMAL(15,3) NOT NULL DEFAULT 0, MODIFY COLUMN running_balance DECIMAL(15,3) NOT NULL, ADD KEY IF NOT EXISTS idx_stock_ledger_tenant_date (tenant_id, transaction_date);
ALTER TABLE stock_adjustments ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE retailer_ledger ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE company_ledger ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE monthly_targets ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE monthly_closings ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NULL AFTER id, ADD KEY IF NOT EXISTS idx_audit_tenant_date (tenant_id, created_at);

UPDATE products SET category_id = 1 WHERE category_id IS NULL;
