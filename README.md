# Maruf Traders — Complete Cement Dealership Management & Accounting Software

## SaaS upgrade

The project now includes additive multi-tenant/product/commission migrations in `database/migrations`. Existing installations must be backed up and upgraded with `php database/migrate.php`; do not re-import the destructive fresh-install schema. See [deployment notes](docs/deployment.md) and [architecture](docs/architecture.md).

A complete, professional, database-driven, production-ready Web-Based Cement Dealership Management and Financial Accounting Software built for **Maruf Traders** (সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ, Bangladesh).

---

## 🚀 Key Features & Modules

1. **Authentication & RBAC**:
   - Secure login with bcrypt `password_hash()` and `password_verify()`.
   - Role-based permissions: Super Admin, Admin, Manager, Sales Staff, Accountant, Viewer.
   - Session timeout protection and CSRF defense.

2. **Retailer Management & Ledger Engine**:
   - Automatic code generation (`RET-001`, `RET-002`).
   - Transaction-driven balance calculations (Opening Balance, Sales, Collections, Advances, Adjustments).
   - Real-time Credit Limit monitoring and Over-Limit visual alerts.
   - Complete printable Retailer Ledger & Customer Account Statement.

3. **Company / Supplier Management & Statement**:
   - Cement manufacturer supplier directory (`COMP-001`).
   - Detailed Company Ledger showing Cement Receives (Purchases), Payments, and running Payable balances.

4. **Cement Product Management**:
   - Brand directory (`PRD-001` - Shah Cement, Bashundhara, Seven Rings, Holcim, etc.).
   - Standard 50kg bag specifications, default purchase price, and default sale price.
   - Low stock warning limit configuration.

5. **Cement Receive / Purchase Management**:
   - Receive recording (`REC-00001`) with landed cost calculations:
     - `Purchase Value = Quantity × Rate`
     - `Total Shipment Cost = Purchase Value + Transport + Loading + Other`
     - `Landed Cost Basis / Bag = Total Cost / Quantity`
   - Real-time inventory addition, company payable update, and atomic stock & company ledger entries.
   - Safe transaction Void/Cancel with inventory rollback.

6. **Sales Management & POS Invoice**:
   - Multi-item sales invoice creator (`SALE-00001`).
   - Stock deduction, COGS computation, gross profit calculation per item and invoice.
   - Customer due calculation, advance balance deduction support.
   - Atomic rollback if any failure occurs.
   - Full printable Sales Invoice & Delivery Challan with business letterhead.
   - Void/Cancel sale with full stock, ledger, and target reversals.

7. **Collections & Advance Deposits**:
   - Market collection receipts (`COL-00001`) reducing customer dues.
   - Customer advance security deposits (`ADV-00001`) offset against sales.

8. **Operating Expenses**:
   - Categorized expenses (Rent, Electricity, Labor wages, Transport, Mobile/Internet, Entertainment).
   - Integrated into Net Profit computation.

9. **Company Payment Module**:
   - Manufacturer payments (`CPAY-00001`) via Bank transfer, Cheques, or Cash.
   - Overpayment restrictions based on business settings.

10. **Monthly Targets & Proportional Commission Engine**:
    - Monthly volume sales target tracking by company/brand (`TGT-00001`).
    - **Proportional Achievement Commission Calculation**:
      - `Achievement % = (Actual Sales Bags / Target Bags) × 100`
      - `Adjusted Rate = Full Rate × (Achievement % / 100)`
      - `Commission Income = Actual Eligible Bags × Adjusted Rate`

11. **Profit & Financial Calculations**:
    - `Gross Profit = Sales Revenue - COGS`
    - `Net Profit = Gross Profit - Operating Expenses + Commission Income`

12. **Executive Dark Dashboard & Live Analytics**:
    - 8 KPI cards with custom styling and gradients.
    - Chart.js live trend line charts for Sales vs Collections.
    - Radial target achievement and estimated commission tracker.
    - Brand-wise visual stock progress bars and animated low stock warning banner.

13. **Monthly Closing Engine**:
    - Enforced on PHP backend to lock financial months from accidental modifications.
    - Admin reopening capabilities with full audit logs.

14. **Comprehensive Reporting**:
    - Daily Business & Cash Flow Report.
    - Monthly Financial Closing Summary.
    - Yearly Comparative Performance Report.
    - Profit & Loss Statement (Printable).
    - Inventory Valuation Report.
    - Supplier Summary & Retailer Due Reports.

---

## 🛠️ Technology Stack

- **Backend**: Core PHP 8+ with PDO Prepared Statements and strict MySQL transactions.
- **Database**: MySQL / MariaDB (UTF8MB4, foreign keys, indexes).
- **Frontend**: HTML5, CSS3, Bootstrap 5, Vanilla JavaScript (ES6+), Fetch API / AJAX.
- **Charts & Icons**: Chart.js, Font Awesome 6.
- **Alerts**: SweetAlert2, Bootstrap Toast.

---

## 📦 Setup & Installation Instructions

### Option 1: One-Click Browser Setup
1. Copy the `maruf-traders` directory into your web root (e.g. `C:\xampp\htdocs\maruf-traders`).
2. Start Apache and MySQL in XAMPP.
3. Open your browser and navigate to: `http://localhost/maruf-traders/setup.php`.
4. Enter your MySQL host and credentials (default: `localhost`, `root`, no password) and click **Run Setup & Seed Database**.

### Option 2: Manual MySQL Import
1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
2. Create a new database named `maruf_traders` with `utf8mb4_unicode_ci` collation.
3. Import `database/schema.sql`.
4. Import `database/seed.sql`.
5. Open `config/database.php` and verify your credentials.
6. Access the software at: `http://localhost/maruf-traders/login.php`.

---

## 🔑 Default User Credentials

| Username | Password | Role | Permissions |
| :--- | :--- | :--- | :--- |
| `admin` | `password123` | Super Admin | Full System Control & Settings |
| `sales` | `password123` | Sales Staff | Retailers, Sales, Collections |
| `accountant` | `password123` | Accountant | Collections, Advances, Expenses, Payments, Reports |

---

## 📍 Business Location
**Maruf Traders**
সুন্দরপুর বাজার, সাটিয়াজুরি, চুনারুঘাট, হবিগঞ্জ, বাংলাদেশ
Currency: BDT (৳)
