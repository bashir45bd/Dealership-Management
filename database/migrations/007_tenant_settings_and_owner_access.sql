-- Copy existing defaults into each workspace once, then all business settings
-- are read and written through tenant_settings instead of shared global state.
INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value)
SELECT t.id, s.setting_key, s.setting_value
FROM tenants t CROSS JOIN settings s;

-- A tenant owner is represented by the Admin role; grant it only the
-- tenant-scoped Business Settings capability.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.slug = 'admin' AND p.slug = 'settings.manage';
