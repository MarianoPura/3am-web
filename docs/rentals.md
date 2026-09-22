# Rentals database

The catalogue reads the existing MySQL/MariaDB connection through `App\Core\Database`. It never creates tables during web requests. An unavailable database produces an honest contact/inquiry state, not a hard-coded live catalogue.

From the project root, with PHP's `pdo_mysql` enabled and the existing database credentials configured in the process environment:

```powershell
php -d variables_order=EGPCS bin/rentals.php --migrate
php -d variables_order=EGPCS bin/rentals.php --seed
php -d variables_order=EGPCS bin/rentals.php --check
```

The existing database must already exist. Run migration with an account permitted to CREATE tables and foreign keys; the runtime account only needs SELECT for Rentals. Supply deployment credentials through the environment/secret manager, never source code. No `.env` edit is required by these scripts. Alternatively import `database/rentals.sql` into the configured database using your database administration tool, then run the optional seed command.

`--seed` uses `database/seeds/rentals.php`, adds missing slugs only and preserves existing records. It is optional and contains **representative samples, not confirmed stock**. Seed items retain `is_sample = 1`; replace their names, descriptions, inclusions and approved media references before setting this flag to 0. No prices or stock counts are stored. `media_reference` is a key from `config/assets.php`.

Use `is_active = 0` to hide an item or category; `display_order` controls ordering. Inclusions are normalized in `rental_inclusions`. Empty catalogues remain empty (samples are never silently reinserted). `--check` checks connectivity and the rental table without printing credentials.

Cards link to `/start?type=rentals&rental=<slug>`. The server resolves active items, visibly carries the selection and appends the verified name/slug to the captured inquiry details. Unknown or withdrawn items show an error asking the visitor to choose another rental or continue with a general inquiry. Old inquiry URLs remain compatible.

## Verification

Against an isolated database named with an `_qa` suffix, migrate and seed using the commands above, then run `php -d variables_order=EGPCS tests/rentals.php`. It verifies database reads, inclusions, inactive categories/items, validation and captured rental selections. Test inquiries use temporary storage and send no email. Database test changes are rolled back.

The configured application database was unreachable during implementation. Schema, repeatable seed, database-backed rendering and inquiry capture were verified using a separate temporary MariaDB instance. The application connection still requires setup; the temporary QA database is not the live catalogue.
