-- Replace copied legacy identity defaults with the actual registered tenant identity.
UPDATE tenant_settings ts
JOIN tenants t ON t.id=ts.tenant_id
LEFT JOIN settings s ON BINARY s.setting_key=BINARY ts.setting_key
SET ts.setting_value=t.name
WHERE ts.setting_key='business_name' AND (ts.setting_value IS NULL OR ts.setting_value='' OR BINARY ts.setting_value=BINARY s.setting_value);

UPDATE tenant_settings ts
JOIN tenants t ON t.id=ts.tenant_id
LEFT JOIN settings s ON BINARY s.setting_key=BINARY ts.setting_key
SET ts.setting_value=COALESCE(NULLIF(t.owner_phone,''),ts.setting_value)
WHERE ts.setting_key='business_mobile' AND (ts.setting_value IS NULL OR ts.setting_value='' OR BINARY ts.setting_value=BINARY s.setting_value);

UPDATE tenant_settings ts
JOIN tenants t ON t.id=ts.tenant_id
LEFT JOIN settings s ON BINARY s.setting_key=BINARY ts.setting_key
SET ts.setting_value=COALESCE(NULLIF(t.owner_email,''),ts.setting_value)
WHERE ts.setting_key='business_email' AND (ts.setting_value IS NULL OR ts.setting_value='' OR BINARY ts.setting_value=BINARY s.setting_value);
