# Rentals local bug audit — 29 September 2026

## Fixed

- Inquiry-only/unknown-status equipment could pass availability checks. Direct cart and availability checks now require available equipment.
- Checkout and rejected-payment resubmission summed disjoint date ranges, incorrectly rejecting valid requests. They now compare the requested quantity against remaining capacity on each day.
- Registration could throw a PHP error for a password containing a null character. Invalid and overlong passwords now receive validation messages before hashing.
- Uploads exceeding PHP's POST limit lost their CSRF token and appeared as session errors. Rentals product, payment-method, checkout and proof-upload routes now reject these requests with HTTP 413 before writing anything.
- Payment-proof forms advertised a fixed 5 MB limit even when PHP allowed less. They now show the effective server limit. Missing upload dependencies and inaccessible proof storage receive safe customer messages and server-side diagnostics.
- Session initialization failures occurred outside the application's error handler. They now use the normal error response and security headers.

## Verification

Passed: Rentals basic, URL, integration, completion, payment-report, new live-regression, customer HTTP flow and Admin QA suites. HTTP workflows used `/web-dev/3am-web` with upload_max_filesize=2M and post_max_size=3M to exercise subfolder routing and oversized POST handling.

Regression checks confirm valid split date ranges pass, true overbooking fails, invalid passwords are rejected, failed proof uploads preserve the cart, and production session failures do not expose internal paths even with APP_DEBUG enabled. Admin QA covers product/category operations, images, payment QR uploads, blackouts, authorization, CSRF and reports. Existing completion-test random passwords were converted to hexadecimal to prevent intermittent null-character hashing failures.

PHP lint passed for all modified PHP files. `git diff --check` passed. Local schema/relationship checks passed; test fixtures were rolled back or removed. No schema migration was made. Existing local data counts remained: 3 users, 1 category, 4 items, 1 blackout, 2 carts, 0 cart lines, 3 payment methods, 15 orders and 17 order lines.

## Deployment boundary

These fixes were tested locally, not deployed. Production logs, schema, PHP extensions and filesystem permissions have not been verified in this pass; this is not a guarantee that all live errors are resolved. The earlier payment-report diagnostic remains available for inspecting production schema without changing it.

No .env, main-site design, intentionally disabled Rentals button, Git branch or production database changes. No commit or push.
