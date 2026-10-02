# 3AM RENTALS — FIXED MAILER REPORT

Date: 2026-10-02. Branch: feature/website-ui-refresh.
This report supersedes the earlier editable-mailer report, per the final requirements.
Status: **locally verified / live delivery confirmation pending**.

## Mailer Architecture

- Existing mailer reused: SmtpMailer, with certificate-verified STARTTLS/SSL, plain text/HTML alternatives and actual CC support. Inquiry configuration/workflow unchanged.
- Existing controller commits transaction → RentalNotification → fixed RentalMailEvents content → RentalSmtpTransport → existing SmtpMailer.
- SMTP source: existing config/mail.php and private server MAIL_* settings. No credentials, APP_KEY or API secrets added to source/views.
- Company routing: config/rentals-mail.php, loaded automatically by the existing configuration loader. No duplicated company addresses in controllers.
- Fixed content in code; escaped navy/yellow HTML wrapper. No internal notes or raw encrypted proof paths/content in email.
- One shared Pending/Approved/Rejected review status remains authoritative.

## Fixed Recipients

Owner: joedeldacudao27@gmail.com

CC:

- lilbeemail88@gmail.com
- leueilshem@gmail.com
- iannopura0206@gmail.com

Company messages use one actual SMTP To recipient and the fixed Cc list. Distinct CC addresses are present in both SMTP envelope and Cc header. No recipient CRUD or subscriptions remain.

These are the exact example CC addresses requested, **not verified company mailboxes**. Before claiming production company delivery works, replace the examples centrally with real authorized addresses through reviewed config deployment. SMTP rejection of an example recipient causes a safe company-message failure; the customer message and saved order/status remain independent.

## Customer Recipient

- Dynamic from order_header.customer_email, saved with the transaction.
- Editing an account email does not redirect a previously created order notification.
- No company CC or protected Admin review link on customer messages.
- Invalid customer destination is recorded safely without preventing independent Owner delivery.
- Confirmed with unique local fixtures and fake transport only.

## Status Emails

| Status | Customer subject | Owner subject | Trigger |
| --- | --- | --- | --- |
| Pending | Rental Request Received — {{order_number}} | New Rental Request — {{order_number}} | Successful checkout commits new Pending order |
| Approved | Rental Request Approved — {{order_number}} | Rental Request Approved — {{order_number}} | Existing Admin review commits Pending → Approved |
| Rejected | Rental Request Update — {{order_number}} | Rental Request Rejected — {{order_number}} | Existing Admin review commits Pending → Rejected |

Each workflow sends one customer message and one Owner message with the fixed CC. No separate rental/payment approval or rejection emails.

Pending explains receipt, attached proof if present, Pending review and a later review email. Empty proof paths never produce a false proof-received claim. Approved explains combined request/payment approval and pickup/delivery contact instructions. Rejected explains combined rejection, support and replacement-proof instructions. There is no dedicated customer-visible rejection reason in the schema; that variable remains empty and internal order_header.notes is never used.

All messages include order, items/quantities/dates, subtotal, deposit, total, payment method/status and support. Company content adds customer email/phone, proof-submitted Yes/No and a protected Admin order link.

The existing replacement-proof hook remains: one fixed customer acknowledgement and one Owner/CC message after a genuinely new proof is saved and committed. Initial checkout sends only the combined Pending receipt. A new proof revision can generate its next legitimate review notification.

Duplicate prevention for every event:

- Existing locked Admin review requires Pending. Repeated Approved/Rejected actions do not trigger email.
- Unique ledger key covers order/event group/proof revision/audience/primary destination; atomic first-attempt claim prevents concurrent repeats.
- Repeated submission/review/proof calls and refreshes do not resend registered events.
- Historical review_approved/review_rejected groups remain compatible with previous payment_approved/payment_rejected records; deploying this update does not resend already recorded combined reviews.
- Failed, pending and uncertain deliveries are not automatically retried. No website retry/test/settings actions remain; server-side ledger/logs retain safe failure categories.
- All notification errors are contained after commit and cannot undo the saved business transaction.

## Removed Editable Mailer Features

- Admin Email Notifications navigation and all eight mailer routes: removed.
- Template subject/body editor, preview/reset/test actions: removed.
- Owner/recipient/subscription CRUD and notification enable/disable controls: removed.
- Delivery history/retry Admin UI: removed with the section.
- Dedicated controller, settings model, four Admin views, heading partial and settings-only CSS: deleted.
- Runtime never reads old saved template overrides or recipient/subscription tables.
- Senior's separated Admin architecture and unrelated navigation preserved.

Old mailer GET/POST URLs return HTTP 404 with valid CSRF for guest/customer/Admin/Superadmin. Global CSRF enforcement remains active.

## Database and Live Compatibility

Only rental_notification_deliveries is required for durable duplicate protection and outcome recording. Current canonical/additive SQL creates no template/recipient/subscription tables.

database/rentals-mailer-additive.sql contains **one CREATE TABLE IF NOT EXISTS**, with unique dedup key, order/event and status/date indexes, and nullable order FK with ON DELETE SET NULL. No DROP/TRUNCATE, business-data UPDATE or destructive schema change. The normal schema checker now covers nine core tables plus this ledger.

Previously created four-table databases remain compatible: their extra nullable recipient_id column/FK is not written or queried. Old template/recipient/subscription tables may remain unused. No tables/records were dropped and no local or live business schema was changed in this pass.

If the ledger already exists and php bin/rentals-mailer.php --check passes, **no new migration is needed**. If it is missing on live, the authorized administrator must review/apply the one-table additive SQL. Missing ledger/SMTP errors are contained after commit. CREATE IF NOT EXISTS cannot repair incompatible existing definitions.

Live prerequisites: existing private MAIL_ENABLED, MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, optional MAIL_TIMEOUT; verified sender, OpenSSL/CA trust and outbound SMTP access. Preserve existing APP_URL/APP_BASE_PATH for /web-dev/3am-web/. No environment settings changed automatically; no Admin recipient setup needed anymore.

Delivery remains synchronous/best effort. Process interruption before registration cannot guarantee eventual delivery. Unknown SMTP acceptance needs provider confirmation before server-side recovery. No queue or second SMTP implementation added.

## Tests

All final checks below passed on local PHP 8.4.25/MariaDB. Code uses the project's PHP 8.2+ language/API baseline; actual PHP 8.2 runtime and live-provider delivery were not tested locally.

| Command/check | Final result |
| --- | --- |
| php tests/rentals-mailer.php | PASS: fixed Owner/CC, dynamic snapshot, combined statuses, post-commit guard, actual reviews, duplicates and historical delivery compatibility, failure/uncertainty/config safety, escaped data, replacement proof, invalid-customer isolation, no editable service/controller/model |
| php tests/rentals-smtp.php | PASS: production adapter actual To/Cc envelopes/headers, duplicate CC normalization, large text/HTML, accepted DATA despite failed QUIT, uncertain/definite rejection; loopback only |
| php tests/rentals-mailer-http.php <QA URL> | PASS: removed GET/POST routes 404 for all roles; clean Admin nav; Home/Information/Rentals/Equipment available; no order mutation |
| php tests/rentals-http-flow.php <QA URL> | PASS: dated cart, checkout, encrypted proofs, QR/status, Admin review/reports, resubmission |
| php tests/rentals-admin-qa.php <QA URL> | PASS: mounted auth/redirect/logout, product/category CRUD/uploads, malicious files, QR, blackouts, search/filter, CSRF, reports, production errors |
| php tests/rentals-urls.php | PASS: root/mounted URLs, absolute links, redirects/query/fragment boundaries |
| php bin/rentals-mailer.php --check | PASS: ledger columns, unique delivery key and order FK |
| php bin/rentals.php --check | PASS: core schema/relationships/ledger; original records preserved |
| PHP lint on every modified/new PHP file | PASS |
| node --check js/rentals-admin.js | PASS; no application JS changed |
| git diff --check; git status --short | PASS/reviewed; changes uncommitted |

QA URL: http://127.0.0.1:8018/web-dev/3am-web, isolated external QA upload storage, SMTP disabled. Initial HTTP regression attempts used mismatched CLI/server QA storage roots; aligning the harness configuration made checkout/upload suites pass. No application storage fix needed.

No real emails sent. Fixtures removed. Original counts preserved: users 3, categories 1, items 4, blackouts 2, carts 2, cart_items 0, payment_methods 3, orders 18, details 20; delivery ledger 0.

## Files Modified

New:

- config/rentals-mail.php

Modified:

- app/Services/RentalMailEvents.php
- app/Services/RentalMailTransport.php
- app/Services/RentalNotification.php
- app/Services/RentalSmtpTransport.php
- app/Views/rentals/emails/content.php
- app/Views/rentals/partials/admin-header.php
- bin/rentals-mailer.php
- bin/rentals.php
- css/rentals-admin.css
- database/rentals-mailer-additive.sql
- database/rentals.sql
- routes/web.php
- tests/rentals-mailer-http.php
- tests/rentals-mailer.php
- tests/rentals-smtp.php
- docs/rentals-mailer-2026-10-02.md

Deleted:

- app/Controllers/Rentals/RentalEmailController.php
- app/Models/RentalMailSettings.php
- app/Views/rentals/admin/email-history.php
- app/Views/rentals/admin/email-recipients.php
- app/Views/rentals/admin/email-template-edit.php
- app/Views/rentals/admin/email-templates.php
- app/Views/rentals/partials/email-heading.php

## Confirmations

- Mailer NOT editable from Admin.
- Owner and CC fixed centrally; customer destination dynamic from order.
- No SMTP credentials or APP secrets hardcoded.
- .env untouched.
- No live DB changes, live emails, commit, push or merge.
- Existing order/payment, image/proof storage and main-site architecture preserved.
