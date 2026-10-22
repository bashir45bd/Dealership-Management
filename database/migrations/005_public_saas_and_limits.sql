ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS yearly_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER price,
  ADD COLUMN IF NOT EXISTS trial_days INT UNSIGNED NOT NULL DEFAULT 14 AFTER billing_period,
  ADD COLUMN IF NOT EXISTS feature_flags JSON NULL AFTER user_limit,
  ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0 AFTER feature_flags;
ALTER TABLE subscription_payments
  MODIFY COLUMN status ENUM('pending','approved','rejected','refunded','cancelled') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS billing_period ENUM('monthly','yearly','custom') NOT NULL DEFAULT 'monthly' AFTER payment_method,
  ADD COLUMN IF NOT EXISTS gateway VARCHAR(50) NOT NULL DEFAULT 'manual' AFTER billing_period,
  ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL AFTER notes;
CREATE TABLE IF NOT EXISTS subscription_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  actor_user_id INT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  old_value JSON NULL,
  new_value JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_subscription_events_tenant_date (tenant_id,created_at),
  CONSTRAINT fk_subscription_event_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
UPDATE subscription_plans SET yearly_price = price * 10, trial_days = 14, display_order = id WHERE yearly_price = 0;
