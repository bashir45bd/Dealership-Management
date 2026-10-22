# Shared-hosting deployment

1. Back up the database and application files. Keep `database/finaldatabase.sql` only for a new blank installation; never import it over an existing database because it drops tables.
2. Deploy the application outside a public writable directory where possible. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`; do not use the bundled root defaults in production.
3. Run `C:\xampp\php\php.exe database/migrate.php` (or the host PHP CLI equivalent) once. Verify `schema_migrations` contains all three migration files.
4. Change all seeded passwords, remove/disable `setup.php`, use HTTPS, and ensure only `assets/uploads/branding` is writable by PHP.
5. Back up with the authorised Backup screen or `mysqldump`; test restore into a separate database before relying on it. Subscription expiry must never be used to delete tenant data.
