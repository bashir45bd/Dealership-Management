-- Migration 009: Multi-tenant generalization and scoped indexing

-- 1. Create many-to-many scheme scoping tables if not exist
CREATE TABLE IF NOT EXISTS commission_scheme_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scheme_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_scheme_product (scheme_id, product_id),
  KEY idx_csp_product (product_id),
  CONSTRAINT fk_csp_scheme FOREIGN KEY (scheme_id) REFERENCES commission_schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commission_scheme_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scheme_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_scheme_category (scheme_id, category_id),
  KEY idx_csc_category (category_id),
  CONSTRAINT fk_csc_scheme FOREIGN KEY (scheme_id) REFERENCES commission_schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Drop global unique constraints and replace with tenant-scoped composite unique constraints
-- Note: MySQL syntax allows dropping index and adding key safely

ALTER TABLE companies DROP INDEX company_code, ADD UNIQUE KEY uq_comp_tenant_code (tenant_id, company_code);
ALTER TABLE products DROP INDEX product_code, ADD UNIQUE KEY uq_prod_tenant_code (tenant_id, product_code);
ALTER TABLE retailers DROP INDEX retailer_code, ADD UNIQUE KEY uq_ret_tenant_code (tenant_id, retailer_code);
ALTER TABLE sales DROP INDEX invoice_no, ADD UNIQUE KEY uq_sale_tenant_inv (tenant_id, invoice_no);
ALTER TABLE cement_receives DROP INDEX receive_code, ADD UNIQUE KEY uq_rec_tenant_code (tenant_id, receive_code);
ALTER TABLE collections DROP INDEX collection_code, ADD UNIQUE KEY uq_col_tenant_code (tenant_id, collection_code);
ALTER TABLE company_payments DROP INDEX payment_code, ADD UNIQUE KEY uq_pay_tenant_code (tenant_id, payment_code);
ALTER TABLE advances DROP INDEX advance_code, ADD UNIQUE KEY uq_adv_tenant_code (tenant_id, advance_code);
ALTER TABLE expenses DROP INDEX expense_code, ADD UNIQUE KEY uq_exp_tenant_code (tenant_id, expense_code);

-- 3. Seed standard configurable categories for all current tenants
INSERT IGNORE INTO product_categories (tenant_id, name, code, status)
SELECT t.id, c.name, c.code, 'active'
FROM tenants t
CROSS JOIN (
  SELECT 'Cement' AS name, 'CEMENT' AS code UNION ALL
  SELECT 'Rod', 'ROD' UNION ALL
  SELECT 'Tin', 'TIN' UNION ALL
  SELECT 'Tiles', 'TILES' UNION ALL
  SELECT 'Sanitary', 'SANITARY' UNION ALL
  SELECT 'Paint', 'PAINT' UNION ALL
  SELECT 'Hardware', 'HARDWARE' UNION ALL
  SELECT 'Electrical', 'ELECTRICAL' UNION ALL
  SELECT 'Plumbing', 'PLUMBING' UNION ALL
  SELECT 'Construction Materials', 'CONSTRUCTION' UNION ALL
  SELECT 'Other', 'OTHER'
) c;

-- 4. Seed standard units if missing
INSERT IGNORE INTO units (name, code, allows_fraction, status) VALUES
  ('Bag', 'BAG', 1, 'active'),
  ('KG', 'KG', 1, 'active'),
  ('Ton', 'TON', 1, 'active'),
  ('Piece', 'PIECE', 0, 'active'),
  ('Box', 'BOX', 1, 'active'),
  ('CFT', 'CFT', 1, 'active'),
  ('Liter', 'LITER', 1, 'active'),
  ('Packet', 'PACKET', 1, 'active'),
  ('Carton', 'CARTON', 1, 'active'),
  ('Tin', 'TIN', 1, 'active'),
  ('Custom', 'CUSTOM', 1, 'active');
