# 3AM Rentals

The module uses the existing eight tables: `users`, `rental_categories`,
`rental_items`, `carts`, `cart_items`, `payment_methods`, `order_header`, and
`order_details`. Services are `rental_items.is_service = 1`. One checkout writes
one header and one detail row per cart line. Analytics has its own Admin page
after Dashboard. Sales Report includes only Approved rental subtotals, while
Payment Report tracks all payment statuses. Both query those tables directly.
In local/testing environments, orders containing `[TEST]` item names are kept
out of Admin business totals and reports; their rental workflow is still
available for QA.

## Customer workflow

On Equipment, the customer opens an item, selects dates and quantity, and the
calendar shows availability by date. The server checks when Add to Cart is
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
only Pending manual proofs. Rejected customers may resubmit a proof only if
their original dates still have stock.

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
readable content. The corporate Information page's visible but inactive Start
Renting button is intentionally outside this module.

## Local verification

Use PHP 8.1+ and run `tests/rentals.php`, `tests/rentals-completion.php`,
`tests/rentals-integration.php`, and `tests/rentals-http-flow.php
http://127.0.0.1:8000`. The HTTP script creates and removes only its own
uniquely named test records and proofs. It checks a two-item order, rejected
invalid uploads, encrypted proof, status token/QR markup, Admin and Superadmin
redirects, role authorization, approval, rejection and proof resubmission.
