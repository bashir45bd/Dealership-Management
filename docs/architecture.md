# Dealership SaaS architecture

## Phase 2 decisions

The existing ledger-first transaction flow remains the accounting source of truth.  The upgrade is additive: legacy cement data is placed in the default tenant and the `Cement` category; no financial transaction is deleted or rewritten.

* One MySQL database is shared by all tenants. `tenant_id` is server-derived from the authenticated user/session and is required on tenant-owned records.
* `companies` are brands/suppliers, `product_categories` are configurable, `products` remain company-owned, and `retailers` are presented as customers/parties for compatibility.
* Transaction headers retain their present tables. New work must write `tenant_id`; old rows migrate to the default tenant.
* Commission rules are data, not executable expressions. A scheme has scope, structured conditions, slabs, calculations, adjustments, settlements, and an append-only ledger.
* Sale/purchase/cancel work continues inside one PDO transaction. Commission calculation records are created/reversed within that same transaction or recalculated by a locked period workflow.
* Subscription status gates new writes but never reads, exports, or historical records.

## Migration order

1. `001_tenant_product_foundation.sql` creates tenant/category/unit data and makes operational records tenant-owned.
2. `002_commission_engine.sql` adds the normalized commission and reconciliation ledger.
3. `003_subscription_and_permissions.sql` adds SaaS plans/payments and missing permissions.

Run migrations with `php database/migrate.php` after a database backup. The runner records each completed migration and does not run it twice.
