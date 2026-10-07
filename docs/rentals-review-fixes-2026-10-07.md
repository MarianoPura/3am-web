# Rentals review fixes — 7 October 2026

Status: locally verified / live confirmation pending. No live access or changes were performed.

## Changes

1. Cart now resolves its customer through the existing `RentalAccount::current()` password/reset guard. Checkout GET and POST validate that account before reading the cart; `createOrder()` also verifies that its user ID belongs to the validated session. Invalidated sessions cannot read/change the old database cart or create an order. Guest carts and fresh authenticated sessions retain their existing behavior.
2. `RentalDateRange` provides strict date parsing and a 400-day inclusive cap before any per-day availability query/array is built. The cap covers all dates selectable within the existing current-month plus 12-month picker. Cart validation, Checkout validation (including the locked recheck), and the shared availability model use it. Same-day, leap-day and historical monthly Admin queries remain supported. Older over-limit cart lines must be shortened; existing orders/data are not deleted.
3. The Services payment form submits the existing `quote_version`. The payment service compares it with the locked request before saving a proof. Missing, invalid or outdated versions require reviewing the latest quotation; the message tells customers who already paid a different amount to contact the team before paying again. Already-pending/approved proof replays still return without creating files or notifications. No new database field is required.
4. The centralized `config/rentals-mail.php` effective CC list now includes the existing configured Owner exactly once, along with configured CC addresses. Addresses remain environment-driven; no address, SMTP credential or sender override was introduced. Case-insensitive duplicates/invalid addresses are filtered. Existing delivery removes the customer from CC when that mailbox is also the Owner/CC. Pending/Approved/Rejected and Services notifications keep the same transport, event keys, templates and ledger deduplication. Historical events are not resent.

## Files changed in this follow-up only

- `app/Controllers/Rentals/RentalCartController.php`
- `app/Controllers/Rentals/RentalCheckoutController.php`
- `app/Controllers/Rentals/RentalServiceRequestController.php`
- `app/Models/RentalCatalog.php`
- `app/Services/RentalCart.php`
- `app/Services/RentalCheckout.php`
- `app/Services/RentalDateRange.php` (new)
- `app/Services/RentalServiceRequests.php`
- `app/Views/rentals/service-request-view.php`
- `config/rentals-mail.php`
- `tests/rentals-review-regression.php` (new)
- `tests/rentals-project-completion.php`
- `tests/rentals-mailer.php`
- `tests/rentals-completion-http.php`
- This report.

Other working-tree changes predate this follow-up and were preserved, including the equipment-image UI work.

## Validation

- PHP lint: 182 application/config/route/CLI/test files passed; the subsequently extended HTTP test was linted again.
- `tests/rentals-review-regression.php`: passed. Boundary/invalid/extreme dates, rejection before opening SQL, stale and legacy-reset sessions, protected Cart/Checkout, normal login/checkout, quotation revisions and replay protection. Fixture changes roll back.
- `tests/rentals-project-completion.php`, `tests/rentals-completion.php`, `tests/rentals-services.php`, `tests/rentals-live-regression.php`: passed.
- `tests/rentals-mailer.php`: passed with a fake transport and synthetic Owner/CC addresses; combined status notifications, recipient overlap, deduplication, legacy-event compatibility and SMTP failure safety.
- `tests/rentals-completion-http.php`: passed at both `http://127.0.0.1:8017` and `http://127.0.0.1:8018/web-dev/3am-web`. Actual multipart image/PDF proof handling, stale/missing/current quote versions, two independent stale sessions, login recovery, bounded dates, ownership/CSRF, existing product/gallery behavior and main/public pages. HTTP mail was disabled.
- `tests/rentals-mailer-http.php`: passed; no editable mailer UI or write routes for guest/customer/Admin/Superadmin.
- Both Rentals JS syntax checks and submit/completion/calendar/Services UI suites passed.
- All three schema/ledger CLI checks passed locally. `git diff --check` passed.

No real email was sent. Local integration tests used disposable fixture rows/files and cleaned them up; no schema migration or existing business-data edit was performed.

## Deployment boundary

This follow-up requires no additional schema migration. The existing completion prerequisites in `docs/rentals-completion-2026-10-07.md` still need to be confirmed on live using the read-only schema/ledger CLI checks. Do not blindly rerun the earlier additive SQL or change the working live environment.

Deploy the reviewed branch as a complete release, including currently untracked application helpers/views. In particular, include `RentalDateRange.php` with its callers, and deploy the Services controller/service/view together. Older open Services payment forms safely request a reload instead of accepting an unversioned payment. Preserve live `.env`, SMTP/sender/recipient values, APP_KEY, external micro uploads and private runtime storage. Use the existing release process to refresh PHP opcode caches if required by the server configuration.

After deployment, retest fresh login/dated equipment checkout, expired-session access, quotation revision between page load and proof upload, image/PDF proof replacement, and one real authorized mail event. Local mounted HTTP tests simulate the live URL prefix but do not verify live permissions, SMTP delivery or deployed schema.

No commit, push, merge, live database change, `.env` edit, SMTP credential edit or APP_KEY edit was performed.
