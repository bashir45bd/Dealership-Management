CREATE TABLE IF NOT EXISTS subscription_plans (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL, company_limit INT NULL, product_limit INT NULL, customer_limit INT NULL, user_limit INT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tenant_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, plan_id INT UNSIGNED NOT NULL, status ENUM('trial','active','grace_period','expired','suspended','cancelled') NOT NULL, starts_at DATE NOT NULL, ends_at DATE NULL, grace_ends_at DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_subscription_tenant_status (tenant_id,status), CONSTRAINT fk_subscription_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id), CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS subscription_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, subscription_id BIGINT UNSIGNED NULL, amount DECIMAL(18,2) NOT NULL, payment_method ENUM('bKash','Nagad','Bank','Cash','Other') NOT NULL, transaction_reference VARCHAR(100) NULL, attachment_path VARCHAR(255) NULL, payment_date DATE NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_subscription_payment_tenant_status (tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO subscription_plans (id,code,name,company_limit,product_limit,customer_limit,user_limit) VALUES (1,'BASIC','Basic',3,3,50,1),(2,'STANDARD','Standard',5,10,150,3),(3,'PREMIUM','Premium',NULL,NULL,NULL,NULL);
INSERT IGNORE INTO permissions (name,slug,module,description) VALUES
 ('Manage Commission Engine','commission.manage','commission','Create schemes, previews, adjustments and settlements'),
 ('Manage Period Commissions','commission_periods.manage','commission_periods','Create and receive period commissions'),
 ('Manage Subscriptions','subscriptions.manage','subscriptions','Manage tenant plans and payments');
