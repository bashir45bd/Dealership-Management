-- Platform administration is deliberately separate from tenant operations.
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER tenant_id;
UPDATE users SET is_platform_admin = 1 WHERE username = 'admin';

ALTER TABLE tenants ADD COLUMN IF NOT EXISTS owner_name VARCHAR(100) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS owner_phone VARCHAR(30) NULL AFTER owner_name,
  ADD COLUMN IF NOT EXISTS owner_email VARCHAR(100) NULL AFTER owner_phone,
  ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER timezone;

ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER name,
  ADD COLUMN IF NOT EXISTS billing_period ENUM('monthly','yearly','custom') NOT NULL DEFAULT 'monthly' AFTER price;

INSERT IGNORE INTO permissions (name, slug, module, description) VALUES
 ('Platform Administration', 'platform.manage', 'platform', 'Manage tenants, plans, subscriptions and payment approvals');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.slug = 'super_admin' AND p.slug = 'platform.manage';
