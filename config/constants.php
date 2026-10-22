<?php
/**
 * Maruf Traders - Global Constants Definition
 */

defined('APP_INIT') or define('APP_INIT', true);

// Roles Slugs
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_SALES_STAFF', 'sales_staff');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_VIEWER', 'viewer');

// Transaction Code Prefixes
define('PREFIX_RETAILER', 'RET');
define('PREFIX_COMPANY', 'COMP');
define('PREFIX_PRODUCT', 'PRD');
define('PREFIX_RECEIVE', 'REC');
define('PREFIX_SALE', 'SALE');
define('PREFIX_COLLECTION', 'COL');
define('PREFIX_ADVANCE', 'ADV');
define('PREFIX_EXPENSE', 'EXP');
define('PREFIX_COMPANY_PAYMENT', 'CPAY');
define('PREFIX_STOCK_ADJUSTMENT', 'ADJ');
define('PREFIX_TARGET', 'TGT');

// Transaction Statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_PAID', 'paid');
define('STATUS_PARTIAL', 'partial');
define('STATUS_DUE', 'due');

// Payment Methods
define('PAYMENT_CASH', 'Cash');
define('PAYMENT_BKASH', 'bKash');
define('PAYMENT_NAGAD', 'Nagad');
define('PAYMENT_BANK', 'Bank');
define('PAYMENT_ROCKET', 'Rocket');

// Stock Transaction Types
define('STOCK_TYPE_OPENING', 'OPENING');
define('STOCK_TYPE_RECEIVE', 'RECEIVE');
define('STOCK_TYPE_SALE', 'SALE');
define('STOCK_TYPE_ADJUSTMENT_ADD', 'ADJUSTMENT_ADD');
define('STOCK_TYPE_ADJUSTMENT_SUB', 'ADJUSTMENT_SUB');
define('STOCK_TYPE_SALE_CANCEL', 'SALE_CANCEL');
define('STOCK_TYPE_RECEIVE_CANCEL', 'RECEIVE_CANCEL');

// Retailer Ledger Types
define('R_LEDGER_OPENING', 'OPENING');
define('R_LEDGER_SALE', 'SALE');
define('R_LEDGER_COLLECTION', 'COLLECTION');
define('R_LEDGER_ADVANCE', 'ADVANCE');
define('R_LEDGER_ADVANCE_USED', 'ADVANCE_USED');
define('R_LEDGER_SALE_CANCEL', 'SALE_CANCEL');
define('R_LEDGER_ADJUSTMENT', 'ADJUSTMENT');

// Company Ledger Types
define('C_LEDGER_OPENING', 'OPENING');
define('C_LEDGER_RECEIVE', 'RECEIVE');
define('C_LEDGER_PAYMENT', 'PAYMENT');
define('C_LEDGER_RECEIVE_CANCEL', 'RECEIVE_CANCEL');
define('C_LEDGER_PAYMENT_CANCEL', 'PAYMENT_CANCEL');
