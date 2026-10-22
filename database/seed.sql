-- Maruf Traders Seed Data
-- Initial Master Data for Roles, Permissions, Users, Settings, Companies, Products, Retailers & Rules

-- 1. ROLES
INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'Full system control, permissions, and business management'),
(2, 'Admin', 'admin', 'Business operations, monthly closing, and user management'),
(3, 'Manager', 'manager', 'Operational management, stock, and sales oversight'),
(4, 'Sales Staff', 'sales_staff', 'Retailer management, new sales, and collections'),
(5, 'Accountant', 'accountant', 'Collections, advances, expenses, company payments, and financial reports'),
(6, 'Viewer', 'viewer', 'Read-only access to dashboard and reporting');

-- 2. PERMISSIONS
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES
(1, 'View Dashboard', 'dashboard.view', 'dashboard', 'Can view management dashboard'),
(2, 'Manage Retailers', 'retailers.manage', 'retailers', 'Can create, edit, and view retailers'),
(3, 'Delete Retailers', 'retailers.delete', 'retailers', 'Can delete/deactivate retailers'),
(4, 'View Sales', 'sales.view', 'sales', 'Can view sales list and invoices'),
(5, 'Create Sales', 'sales.create', 'sales', 'Can create new sales invoice'),
(6, 'Cancel Sales', 'sales.cancel', 'sales', 'Can void/cancel sales invoice'),
(7, 'Manage Collections', 'collections.manage', 'collections', 'Can record and view collections'),
(8, 'Manage Advances', 'advances.manage', 'advances', 'Can record and view advances'),
(9, 'Manage Expenses', 'expenses.manage', 'expenses', 'Can record and view expenses'),
(10, 'Manage Companies', 'companies.manage', 'companies', 'Can manage suppliers and view statements'),
(11, 'Manage Products', 'products.manage', 'products', 'Can manage cement products and prices'),
(12, 'Manage Cement Receives', 'receives.manage', 'receives', 'Can record cement stock receive'),
(13, 'Manage Stock Adjustments', 'stock.adjust', 'stock', 'Can record stock adjustments'),
(14, 'Manage Company Payments', 'company_payments.manage', 'company_payments', 'Can record company payments'),
(15, 'Manage Targets & Commission', 'targets.manage', 'targets', 'Can set monthly targets and commission rules'),
(16, 'View Financial Reports', 'reports.financial', 'reports', 'Can view profit, loss, and ledger statements'),
(17, 'Manage Monthly Closing', 'closing.manage', 'monthly_closing', 'Can close or reopen accounting months'),
(18, 'Manage Users', 'users.manage', 'users', 'Can manage system users and roles'),
(19, 'Manage Settings', 'settings.manage', 'settings', 'Can update business and app settings'),
(20, 'View Audit Logs', 'audit.view', 'audit', 'Can view audit trails and activity logs');

-- 3. ROLE PERMISSIONS
-- Super Admin (All Permissions)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Admin (All except system delete/critical settings)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE id NOT IN (19);

-- Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE id IN (1, 2, 4, 5, 7, 8, 9, 10, 11, 12, 13, 15, 16);

-- Sales Staff
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE id IN (1, 2, 4, 5, 7, 8);

-- Accountant
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` WHERE id IN (1, 2, 4, 7, 8, 9, 10, 14, 16);

-- Viewer
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE id IN (1, 4, 16);

-- 4. DEFAULT USERS
-- Password for all: password123
INSERT INTO `users` (`id`, `role_id`, `name`, `username`, `email`, `password`, `phone`, `status`) VALUES
(1, 1, 'Bashir Ahmed', 'admin', 'bashir@maruftraders.com', '$2y$10$S7NGl1QAvi1yMh2FfjLYQ.AR2kWP9rsXf4D/38GvujAgNQ8Rsr0C.', '01711000000', 'active'),
(2, 4, 'Md. Maruf Hasan', 'sales', 'maruf@maruftraders.com', '$2y$10$S7NGl1QAvi1yMh2FfjLYQ.AR2kWP9rsXf4D/38GvujAgNQ8Rsr0C.', '01811000000', 'active'),
(3, 5, 'Kawsar Mia', 'accountant', 'accounts@maruftraders.com', '$2y$10$S7NGl1QAvi1yMh2FfjLYQ.AR2kWP9rsXf4D/38GvujAgNQ8Rsr0C.', '01911000000', 'active');

-- 5. SETTINGS
INSERT INTO `settings` (`setting_key`, `setting_value`, `group_name`, `description`) VALUES
('business_name', 'Maruf Traders', 'general', 'Official Business Name'),
('business_title', 'Cement Dealership Management & Accounting Software', 'general', 'Software Subtitle'),
('business_address', 'সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ', 'general', 'Business Address'),
('business_mobile', '01712-345678, 01812-345678', 'general', 'Contact Mobile Numbers'),
('business_email', 'contact@maruftraders.com', 'general', 'Business Email Address'),
('currency_symbol', '৳', 'general', 'Currency Symbol'),
('currency_code', 'BDT', 'general', 'Currency Code'),
('default_payment_method', 'Cash', 'finance', 'Default payment method for transactions'),
('payment_methods', 'Cash,bKash,Nagad,Bank Transfer,Rocket', 'finance', 'Comma separated available payment methods'),
('default_credit_limit', '100000.00', 'finance', 'Default retailer credit limit (BDT)'),
('allow_negative_stock', 'no', 'inventory', 'Allow sales when stock is zero or insufficient (yes/no)'),
('low_stock_threshold', '100', 'inventory', 'Global default low stock alert threshold in bags'),
('default_cement_unit', 'Bag', 'inventory', 'Standard unit for cement products'),
('allow_company_overpayment', 'no', 'finance', 'Allow payment exceeding current company payable balance (yes/no)'),
('commission_calculation_mode', 'proportional', 'commission', 'Default mode: proportional, fixed_per_bag, percentage_sales, fixed_amount'),
('session_timeout_minutes', '120', 'security', 'Auto logout after inactive minutes');

-- 6. EXPENSE CATEGORIES
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`) VALUES
(1, 'Transport & Freight', 'Carriage, truck fare, transport costs', 'active'),
(2, 'Shop & Office Expense', 'Tea, snacks, stationary, office supplies', 'active'),
(3, 'Loading & Unloading', 'Labor wages for loading and unloading cement bags', 'active'),
(4, 'Employee Salary', 'Staff monthly wages, daily allowances', 'active'),
(5, 'Mobile & Internet', 'Shop phone recharge, broadband internet bill', 'active'),
(6, 'Shop Rent', 'Monthly shop godown/showroom rent', 'active'),
(7, 'Electricity & Utility', 'Monthly electricity and municipal bills', 'active'),
(8, 'Maintenance & Repairs', 'Shop fixture repairs, shutter, electric work', 'active'),
(9, 'Bank Charges & Commission', 'Bank ledger fees, transaction commissions', 'active'),
(10, 'Miscellaneous / Other', 'General unexpected minor expenses', 'active');



INSERT INTO permissions (name, slug, module, description) VALUES ('Manage Database Backup', 'backup.manage', 'backup', 'Download a database backup');
-- Tarpor role_permissions e Super Admin (ba jei role dorkar) er sathe link korun:
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'super_admin' AND p.slug = 'backup.manage';

INSERT INTO permissions (name, slug, module, description) VALUES
('Manage Period Commissions', 'commission_periods.manage', 'commission_periods', 'Create and receive period commissions'),
('Manage Commission Engine', 'commission.manage', 'commission', 'Manage configurable commission schemes');
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.slug IN ('super_admin', 'admin', 'accountant') AND p.slug IN ('commission_periods.manage', 'commission.manage');

