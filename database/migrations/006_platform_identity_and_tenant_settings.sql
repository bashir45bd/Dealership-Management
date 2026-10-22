ALTER TABLE users MODIFY COLUMN tenant_id INT UNSIGNED NULL DEFAULT NULL;
UPDATE users SET tenant_id = NULL WHERE is_platform_admin = 1;
CREATE TABLE IF NOT EXISTS tenant_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, setting_key VARCHAR(100) NOT NULL, setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_setting (tenant_id,setting_key),
  CONSTRAINT fk_tenant_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS platform_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_user_id INT UNSIGNED NULL, tenant_id INT UNSIGNED NULL, action VARCHAR(100) NOT NULL, module VARCHAR(50) NOT NULL,
  reference_id VARCHAR(100) NULL, old_value JSON NULL, new_value JSON NULL, ip_address VARCHAR(45) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_platform_audit_tenant_date (tenant_id,created_at), KEY idx_platform_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
