# 3AM Rentals

The module uses nine tables: `users`, `rental_categories`,
`rental_items`, `carts`, `cart_items`, `payment_methods`, `order_header`, and
`order_details`, plus `rental_item_blackouts` for Admin-blocked dates. Services are `rental_items.is_service = 1`. One checkout writes
one header and one detail row per cart line. Analytics has its own Admin page
after Dashboard. Sales Report includes only Approved rental subtotals, while
Payment Report tracks all payment statuses. Both query those tables directly.
Orders with `[TEST]` item snapshots are included under the same status rules.
Approved-only rental sales/revenue uses `subtotal`; deposits remain separate.
Payment Report shows Approved collected totals and Pending amounts separately.

## Customer workflow

On Equipment, the customer opens an item, selects dates and quantity, and the
single custom calendar shows availability by date. Quantity changes keep the
visible month and revalidate availability; stale responses cannot replace the
latest month. A valid selected range is retained; an invalid range is cleared
with an explanation. The desktop modal keeps its image column fixed while the
details column scrolls. My Rentals, Cart and Account are grouped in the header;
Account opens an anchored popover using hover, click, keyboard or mobile click.
My Rentals contains only the signed-in user's orders, with expandable snapshot
details and separate rental subtotal/deposit totals. The server checks when Add to Cart is
pressed. Cart changes save automatically and check again. Final checkout locks
the relevant item rows and checks once more before writing the
header and details in a transaction. Pending and Approved orders reserve stock;
Rejected orders do not. The selected payment method must be active. A manual
method also needs configured account name and number; checkout shows those
details and the calculated amount. Gateway methods are configured in Admin but remain unavailable at checkout until a real
integration is approved. A legacy `test` method is accepted only in local/testing.

Manual checkout requires a JPG, PNG, WebP or PDF proof no larger than 5 MB. The
server checks MIME and size, ignores the supplied filename, generates a random
name and stores a relative path such as `micro/payment/<random>.jpg` on the order.
The physical directory is `BASE_PATH/micro/payment`, corresponding to the
deployed `/var/www/html/web/micro/payment`. This is inside the document root.
Apache denies direct access with `.htaccess`; the stored file is also AES-256-GCM
encrypted so direct access on the local PHP server reveals no readable proof.
Only the owner and an Admin can use the app's decrypted proof routes. The local
encryption key is kept in ignored `storage/rentals/payment-key.php`; production
requires a stable existing `APP_KEY`. Preserve that key across deployments and
back it up with the proofs. Do not change it while old proofs need to be read.

Every order gets an independent 64-character random `status_token`. A status URL
uses the token rather than an order ID. The success and status pages generate a
downloadable QR in the browser using the bundled MIT-licensed QR library. A
best-effort email is attempted after checkout commit and after Admin review;
mail failure is logged and never rolls back an order. Verify the production mail
transport before relying on delivery.

## One status source

`order_header.payment_status` is `TINYINT UNSIGNED`: 0 Pending, 1 Approved,
2 Rejected. `RentalPaymentStatus` provides shared constants and labels. The
generic `status`, `order_status`, and `payment_review_status` columns are not
part of the current schema. Review metadata remains in `payment_reviewed_at`,
`payment_reviewed_by`, and `paid_at`. Admin review uses POST + CSRF and accepts
only Pending payments. Approval requires a valid manual proof; rejection is
available even before a proof is uploaded. Both actions record the authenticated
reviewer and timestamp. Approval sets `paid_at`; rejection clears it.
Rejected customers may resubmit a proof only if
their original dates still have stock.

Admin Products offers **Manage availability** on equipment rows and inside Edit.
Its calendar shows Available, Customer Reserved and Admin Blocked without
customer identities. Select one date or two dates for a range, optionally add a
note, then Block dates. Remove manual block deactivates the corresponding
`rental_item_blackouts` record; Block again reactivates it. Reservations are
never removed or overridden by these controls. Both calendars read the same
reservation/blackout calculation, and customer availability requests are fresh
rather than cached between month changes. The Admin API requires Admin/Superadmin
authorization. No additional availability table or schema migration is needed.

## Migration and deployment

`database/rentals.sql` is the canonical fresh schema. Run `php bin/rentals.php
--check` to inspect a configured database without writing. `--migrate` is
restricted to local/testing and preflights legacy values before replacing the
old status columns; it aborts on unmappable values. The local database was
migrated after confirming that its existing five orders were all Pending.
Production is untouched: back it up, inspect its actual statuses, rehearse the
migration on staging, then review the exact DDL before applying it. DDL can
implicitly commit, so do not treat this as an automatic live migration.

The deployed web root must keep the root and `micro/payment/.htaccess` deny
rules enabled. Test a direct proof URL after deployment; it must not return
readable content. Public payment-method QR images stored beneath that denied
directory are served through `/rentals/payment-qr/{id}`; payment proofs remain
private. Product images use `micro/rentals/products` with executable files denied.
The corporate Information page's Start Renting button now links to Rentals.

### Existing deployed database missing review fields

No production migration was run. Back up and inspect the deployed schema first:

```sql
SHOW COLUMNS FROM order_header LIKE 'payment_reviewed_at';
SHOW COLUMNS FROM order_header LIKE 'payment_reviewed_by';
SHOW COLUMNS FROM order_header LIKE 'paid_at';
```

Run only the individual statement for a column confirmed missing, using the
approved deployment migration account. These nullable additions preserve rows:

```sql
ALTER TABLE order_header ADD COLUMN payment_reviewed_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE order_header ADD COLUMN payment_reviewed_by BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE order_header ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL;
```

The canonical schema and `bin/rentals.php --check` already require these fields.
Check also `payment_methods.qr_image_path` and `rental_item_blackouts` against
`database/rentals.sql` before release. If the QR field is missing, its exact
additive statement is:

```sql
ALTER TABLE payment_methods ADD COLUMN qr_image_path VARCHAR(255) DEFAULT NULL;
```

The complete `CREATE TABLE IF NOT EXISTS rental_item_blackouts` definition is
in the canonical schema; do not recreate any existing tables or change statuses
without reviewing actual deployed values.

## Local verification

Use PHP 8.1+ and run `tests/rentals.php`, `tests/rentals-completion.php`,
`tests/rentals-integration.php`, and `tests/rentals-http-flow.php
http://127.0.0.1:8000`. The HTTP script creates and removes only its own
uniquely named test records and proofs. It checks a two-item order, rejected
invalid uploads, encrypted proof, status token/QR markup, Admin and Superadmin
redirects, role authorization, approval, rejection and proof resubmission.

`tests/rentals-urls.php` checks root/mounted URL generation without DB writes.
For Admin QA, start the local-only `tests/rentals-dev-router.php` with PHP's
built-in server, then run `tests/rentals-admin-qa.php http://127.0.0.1:8012`.
Set `RENTALS_QA_MOUNT=/web-dev/3am-web` only in the test server process to
simulate deployment mounting. The QA script removes its own uniquely named
fixtures/uploads; it never migrates production or modifies `.env`.
