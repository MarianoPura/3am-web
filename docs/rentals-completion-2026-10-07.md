# Rentals completion and QA — 7 October 2026

## Delivered locally

1. Services retain a separate enquiry → Admin approval → quotation → encrypted customer payment proof → payment review → Completed/Cancelled flow. They do not create equipment orders, reserve equipment stock, or change equipment payment states. Completion requires the event end date and an approved payment, or an explicit zero-amount quotation. Cancellation retains payment records; refunds remain a coordinated manual action.
2. Customer Services history/detail and Admin queues now expose quotation, payment method/reference, review messages, protected image/PDF proofs and lifecycle states. Customer ownership, Admin/Superadmin, CSRF and no-cache checks apply. Repeated submissions/reviews preserve the first applicable decision and do not save duplicate proof files or delivery events. Rejected proofs can be replaced safely.
3. Login/registration throttling is atomic across sessions, with account and IP buckets. Forgot Password uses the existing SMTP transport, private 30-minute single-use hashed tokens, generic recovery responses, password validation and stale-session invalidation. Recovery links are never CC'd. There is no mail-settings UI.
4. Products accept a primary image plus five additional managed images. Admin can upload/remove thumbnails, retaining existing images after validation failures. File cleanup happens after persistence and checks shared references. Equipment detail supports arrows, keyboard and horizontal drag/swipe; single-image controls hide. Existing managed delivery routes, MIME/size validation and missing-image handling remain in use.
5. Products, Orders, Customers, Sales/Payment Reports, customer equipment history and customer/Admin Services lists are paginated in groups of 25. Filter parameters persist, invalid pages clamp safely, and report totals still cover all matching records.
6. Analytics appears in Dashboard, with Services status counts. The old Analytics route redirects and preserves filters. Both time charts have readable date axes, value scales, exact date/value selectors, descriptive titles and responsive labels. Day/month/year buckets preserve totals. Revenue still uses approved equipment-order subtotals, excludes deposits, fills zero dates and retains its highest-day summary. Dates are formatted server-side without browser UTC conversion. The caption uses the actual database connection offset; it identifies Asia/Manila only when that offset matches the application's configured zone. No global/session database timezone is changed and no hourly data is invented for daily totals.
7. Read-only schema tooling now uses the current code contract, independent of the deleted legacy database SQL. Column presence, optional fields, relevant sizes/types, unique keys and foreign keys are checked. The old destructive legacy `--migrate` path is disabled; schema updates are explicit reviewed SQL.

## Deployment prerequisite — live has NOT been changed

The additive schema was applied to the local loopback database only. Before deploying this branch, the server administrator should review/back up the intended database and apply only missing clauses from `docs/rentals-completion-additive.sql`:

- One nullable `rental_items.additional_image_paths` JSON column; primary image data is preserved.
- New `rental_password_resets` table, with a unique hashed token and user FK.
- Twelve quotation/payment/completion fields and related keys on the existing `rental_service_requests` table.
- One nullable `service_request_id` and related index/FK in the existing notification delivery ledger. Services use this reference, not the legacy editable-recipient relationship or an equipment order ID.

The existing base Services table must be present (`docs/rentals-service-requests-additive.sql`). If the notification ledger itself is absent, its minimal creation SQL is in `docs/rentals-notification-ledger.sql`; do not recreate or drop a working ledger. Existing legacy recipient tables/relationships are preserved.

Read-only inventory before applying any ALTER:

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('rental_items', 'rental_service_requests',
                    'rental_password_resets', 'rental_notification_deliveries')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

Do not blindly rerun the grouped ALTER statements. Select only clauses whose columns/indexes/constraints are missing. `bin/rentals-completion.php --apply-local` is guarded for explicit local/testing loopback use; it is not a live deployment command. Request handlers never execute DDL.

After review/application, run:

```text
php bin/rentals.php --check
php bin/rentals-mailer.php --check
php bin/rentals-completion.php --check
```

Keep the already working live SMTP settings, sender/recipients, APP_KEY and external RentalStorage root unchanged. PHP Fileinfo/OpenSSL and existing proof/image upload permissions are still required. The PHP process also needs write access to the private `storage/cache/rentals-auth` directory (created automatically); use appropriate ownership, not chmod 777. Keep runtime uploads and private cache outside destructive release cleanup. Account/IP limiter records contain only hashes/timestamps; expired records may be removed by the server's normal private-cache housekeeping.

Existing enquiry review and primary-image behavior remain available when optional new feature columns are not yet deployed. Quotation/payment/gallery additions require their reviewed schema update; the schema checks intentionally report missing prerequisites.

## Verification results

All final executed suites passed. Local HTTP QA used a mail-disabled PHP router and disposable fixtures; automated mail tests used fake/loopback transports, never the live mailbox.

| Check | Result |
|---|---|
| `tests/rentals-project-completion.php` | PASS — recovery/token/session/throttle, Services transitions, pagination/ownership, dedup/SMTP failure, unchanged equipment orders |
| `tests/rentals-completion-http.php` | PASS at root and `/web-dev/3am-web/` — actual multipart galleries, managed URLs, encrypted image/PDF proofs and replacement, CSRF/ownership, repeated POST/review, pagination/public pages |
| `tests/rentals-services.php`, `tests/rentals-services-http.php` | PASS — request/history/review and mounted HTTP flow |
| `tests/rentals-mailer.php` | PASS — existing dynamic customer/configured CC, Pending/Approved/Rejected, dedup and SMTP safety |
| `tests/rentals-admin-qa.php` | PASS — CRUD/uploads, protected QR delivery, dates/blackouts/remaining stock, filtering/reports and production error responses |
| `tests/rentals-completion.php`, `tests/rentals-latest-qa.php` | PASS — equipment checkout/status/stock; date boundaries, report/chart totals, sub-peso values, monthly aggregation |
| `tests/rentals-live-regression.php`, `tests/rentals-payment-report.php` | PASS — persisted flow and exact missing-column diagnostic tests |
| `tests/rentals-qr-ownership.php` | PASS — owner/Admin/Superadmin access, other customer 403, protected proof, mounted URLs |
| `tests/rentals.php` | PASS — guest cart, quantity/date validation, unavailable catalogue and portable images |
| `tests/rentals-analytics-charts.php` | PASS — readable date buckets, month/year boundaries, exact values, timezone basis, one-day/zero scales, schema column checks |
| `tests/rentals-completion-ui.mjs` | PASS — gallery keyboard/swipe/cancel/single/missing states; exact chart selector updates |
| Existing `rentals-qa-ui`, `rentals-services-ui`, `rentals-submit-ui` JS suites | PASS — stock/calendar labels, type controls, submit locking/spinner/history recovery |
| PHP lint | PASS — 117 files across Rentals, models/services, CLI/tests, routes and entry point |
| JS syntax | PASS — `js/rentals.js` and `js/rentals-admin.js` |
| Three schema/ledger CLI checks | PASS locally |
| `git diff --check` | PASS |

Browser QA covered desktop (1440px), tablet (768px) and mobile (390px): Admin/customer Services detail, quotation/payment method switching, gallery buttons/keyboard, recovery, Dashboard metrics/charts and gallery editor. No document horizontal overflow was observed on these views. Public `/`, `/information`, Equipment and Services return HTTP 200. Main-site templates/styles and the intentionally disabled main-site Rentals button were not changed.

Some existing regression tests were updated to reflect intentional new behavior: Analytics redirects to Dashboard; a reviewed service still has separate quotation/close controls; disposable direct-session switches clear stale password stamps. Assertions for ownership, duplicate protection and data totals remain enforced. Temporary visual/upload fixtures were cleaned after browser QA.

## Files changed in this completion pass

- Controllers: Rentals Account, Admin and Service Request; new Password controller.
- Services/models: Account, AdminInsights, Notification, ServiceRequests, Catalog; new Schema, AuthThrottle, PasswordReset, Gallery and Pagination helpers.
- Views: account/recovery; customer order/Services history and Services detail; Admin Dashboard, items/editor, customers/orders/Services; pagination, gallery, Services payment summary, analytics/time-chart and report/header partials.
- Integration: Rentals routes, scoped Rentals CSS/JS, and the existing entry-point oversized-upload guard extended to Services proof submissions.
- CLI/docs/tests: updated Rentals/schema/mail CLI; new explicit local completion CLI, additive SQL/ledger contract, this report and focused backend/HTTP/UI/chart tests; compatible regression assertions and historical documentation status notes.

No `.env`, SMTP credential, APP_KEY, fixed recipient configuration, main-site redesign, live database operation, commit, push or merge was performed. This is locally verified; live deployment and real-mail delivery of the new events remain pending the senior's reviewed rollout.
