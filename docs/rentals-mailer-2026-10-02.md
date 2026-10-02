# 3AM RENTALS — MAILER IMPLEMENTATION REPORT

Branch: `feature/website-ui-refresh`. Status: locally verified; live delivery confirmation pending deployment/configuration. No live database, secret, commit, push or merge operations were performed.

## Existing Mailer Audit

- Existing `SmtpMailer` already supports SMTP, certificate-verified STARTTLS/SSL, plain text and HTML alternatives. Inquiry already uses this client. Its existing Inquiry configuration and workflow remain unchanged.
- Rentals previously called native PHP `mail()` from `RentalNotification` for checkout, proof replacement and Admin proof review. Those calls now use the existing SMTP client through an injectable transport adapter; there is no second SMTP implementation.
- SMTP configuration is `config/mail.php`. Actual environment names are `MAIL_*`, not `SMTP_*`. Sender comes from `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`. No SMTP values are editable or disclosed through Admin.
- Local audit found no equivalent mail settings/template/recipient tables. The four additions below are therefore necessary.
- Original local business row counts: users 3, categories 1, items 4, blackouts 2, carts 2, cart_items 0, payment_methods 3, order_header 18, order_details 20. Test fixtures are removed; original records remain.

## Mail Events

The current senior-refactored schema has one `order_header.payment_status` (Pending 0, Approved 1, Rejected 2). Per the user's clarification, a review sends **one combined customer email**, never separate rental/payment approval or rejection emails. No independent rental status or second review workflow was introduced.

All customer destinations are read from the order's saved `customer_email`, not an editable template or the user's current account email. Internal destinations are active Admin-configured event subscribers; each address receives a separate email without CC.

| Event | Trigger after commit | Customer template | Internal template / subscriptions | Duplicate prevention |
| --- | --- | --- | --- | --- |
| Rental Submitted | Successful checkout creates a Pending order with saved proof and details | `rental_submitted/customer`: request and payment/proof pending review | `rental_submitted/admin`; proof-only subscribers can receive `payment_proof_received/admin` | One registration per order/audience/address; initial request/proof share a delivery group |
| Rental Approved | Actual Pending → Approved Admin review | Covered by the combined `payment_approved/customer` email | `rental_approved/admin` for subscribers | Same review group as Payment Approved; subscribing to both does not send twice |
| Rental Rejected | Actual Pending → Rejected Admin review | Covered by combined `payment_rejected/customer` | `rental_rejected/admin` for subscribers | Same review group as Payment Rejected |
| Proof Received | Valid replacement proof is stored and associated successfully | `payment_proof_received/customer`: received, awaiting verification | `payment_proof_received/admin` | Per order/proof revision/audience/address; initial checkout is one combined customer receipt |
| Payment Approved | Actual Pending → Approved review | `payment_approved/customer`: payment confirmed and rental approved | `payment_approved/admin`, with rental subscriber fallback | Successful existing deliveries cannot be sent or retried again |
| Payment Rejected | Actual Pending → Rejected review | `payment_rejected/customer`: review rejected and replacement-proof instructions | `payment_rejected/admin`, with rental subscriber fallback | Only actual transitions generate mail; a genuinely new proof permits a new review notification |

The six central definitions and twelve editable audience templates are available. The standalone `rental_approved/customer` and `rental_rejected/customer` templates are clearly marked as reserved for a future separate rental review, and are not invoked by the current shared review action.

No dedicated customer-visible rejection reason exists in the current schema. `rejection_reason` is empty for real orders; internal `order_header.notes` is never loaded into email. Safe sample reason rendering is tested. Future reminders/cancellations can extend the event catalog without changing SMTP.

The unused `RentalAdminReservation` service has no current controller/route callers; this pass does not revive it or create another booking workflow.

## Admin Email Templates

Open Rentals Admin → **Email Notifications** (`/rentals/admin/email`). Separated controller, views and existing Admin layout are preserved; no `rentals/admin.php` god view was created.

- Edit display name, subject, plain-text body and enabled status.
- Professional defaults are supplied from the event catalog when there is no saved override; no runtime database seeding is required.
- Save, sample preview, explicit reset to default, and explicit test email are implemented. Preview/test do not save draft edits or create/change orders.
- Allowlisted customer/order/dates/items/totals/deposit/payment/company/support variables; phone, proof-submitted flag and secure Admin review URL are internal-only variables.
- Unknown variables and subject header injection are rejected visibly. Template code/HTML is never executed. All body/variable content is escaped in the branded navy/yellow HTML wrapper; plain-text alternative remains available.
- Sample preview shares the email content partial and works with existing CSP; no iframe exceptions or weakened security headers are needed.
- SMTP/sender status exposes only YES/NO. Credentials remain inaccessible.

## Owner/Admin Recipients

Name/email CRUD, active/disabled setting and normalized per-event subscriptions are implemented. Invalid/duplicate addresses and invalid subscriptions are rejected. Removing an internal recipient removes subscriptions while preserving historical deliveries; order/customer data is untouched.

No internal recipient addresses are prefilled from hardcoded controllers or Inquiry CC. An Admin must explicitly add the Owner/Operations/Accounting addresses that should receive Rentals events.

## Delivery Logging

- A unique SHA-256 delivery key covers order, event group, proof revision, audience and normalized destination.
- Statuses: pending, sent, failed, uncertain. History includes event, order, destination/audience, attempts, timestamps and safe failure category. Email bodies, credentials, internal notes and encrypted proof data are not logged or stored in delivery history.
- Separate recipient deliveries and atomic conditional claims prevent concurrent duplicate attempts.
- Only failed deliveries can be retried via Admin POST. A successful, pending, uncertain, obsolete, disabled or unsubscribed delivery cannot be retried. Changed recipient addresses do not redirect old deliveries to a new address.
- Retries do not change order/payment status. Obsolete review/proof notifications are rejected rather than sending contradictory updates.
- Ordinary logs contain safe event/order/audience/category metadata; unexpected notification errors include exception class and source location, never exception messages, credentials or body contents.
- Mail is sent only after commit. A mailer error or missing mailer schema is contained by the service, so successful checkout/proof updates/review remain saved.
- Delivery is synchronous and best effort; no scheduler or background queue was added. A process interrupted before registration cannot guarantee eventual delivery. Pending/uncertain attempts require provider confirmation, rather than an unsafe automatic resend.

The shared SMTP client now completes partial socket writes, treats acknowledged DATA as accepted even if QUIT fails, and distinguishes unknown DATA acceptance from a definite rejection. Unknown acceptance is not blindly retryable. Certificate verification/authentication/encryption remain intact.

## SMTP / Security

- Secret source: existing environment/server configuration. SMTP credentials and `.env` were not changed.
- Admin/Superadmin authorization runs on every mailer read/write/test/retry action. Customers receive HTTP 403; guests use the normal sign-in redirect.
- All mutations, previews, resets, test sends and retries use existing CSRF enforcement; HTTP tests verify 419 without a valid token.
- Test email uses explicitly validated destination or current Admin email, sample data and a `[TEST]` subject through the normal transport pipeline.
- Automated tests inject a fake transport, use a loopback fake SMTP server, or force `MAIL_ENABLED=false` in the local-only QA router. **No real email was sent during QA.**
- Proofs remain encrypted; email contains a protected Admin order link, not raw proof paths or attachments. Storage/image delivery and proof encryption were not redesigned.
- Relative routes and `absolute_url()` preserve root/subdirectory deployments. Absolute email links use the existing `APP_URL`/`APP_BASE_PATH` configuration.

## Database

Existing `order_header`, `order_details`, users and payment methods are reused for transactional snapshots. No order/customer fields, status columns or existing business data were migrated.

Additive SQL: `database/rentals-mailer-additive.sql`. The canonical `database/rentals.sql` includes the same definitions.

| New table | Columns / key purpose | Indexes and constraints |
| --- | --- | --- |
| `rental_email_templates` | id, event_key, audience, display_name, subject, body, is_active, created_at, updated_at | PK id; unique (event_key, audience) |
| `rental_notification_recipients` | id, name, email, is_active, created_at, updated_at | PK id; unique email |
| `rental_notification_recipient_events` | recipient_id, event_key | Composite PK; event lookup index; recipient FK ON DELETE CASCADE |
| `rental_notification_deliveries` | id, dedup_key, event_key, event_version, order_id, audience, recipient_id, recipient_email, status, attempts, failure_category, attempted_at, sent_at, created_at | PK id; unique dedup_key; status/date, order/event and recipient indexes; order/recipient FKs ON DELETE SET NULL |

Tables use InnoDB / utf8mb4 and unsigned bigint IDs matching the canonical schema. SQL is CREATE TABLE IF NOT EXISTS only; no DROP, TRUNCATE, rewrites or record deletion. MySQL DDL commits implicitly. Existing incompatible tables need review rather than assuming IF NOT EXISTS repairs them.

`php bin/rentals-mailer.php --sql` prints the SQL without executing it. `--check` verifies required columns, auto-increment, composite unique keys and FKs read-only. `--migrate` is explicitly restricted to local/testing and adds only the four mailer tables. The normal Rentals schema check now covers all thirteen canonical tables.

**Local migration run: YES. Live migration run: NO.** The four local mailer tables are available with no seeded owner addresses or test data. Rollback implication: rolling back the application can safely leave the new tables in place. Do not automatically drop history/configuration tables or modify existing orders to roll back the feature.

## Tests

All commands below passed using PHP 8.4.25 and the configured local MariaDB database. HTTP suites ran against `http://127.0.0.1:8018/web-dev/3am-web` with an isolated external QA storage root and delivery disabled.

- `php tests/rentals-mailer.php`: combined customer events, correct recipients/order snapshot, initial proof/request grouping, proof replacement/versioning, editable/disabled templates, escaped HTML/sample reason, invalid recipients/variables/headers, SMTP failure preserving Approved, retries, double review, uncertain acceptance, sample tests, recipient changes/removal, Admin/Superadmin guards, post-commit enforcement.
- `php tests/rentals-smtp.php`: existing SMTP pipeline using loopback only, large multipart text/HTML message, accepted DATA despite failed QUIT, uncertain acceptance and definite rejection.
- `php tests/rentals-mailer-http.php <QA URL>`: mounted event routes with underscores, settings/status, Save/Preview/Test, no preview business/template writes, visible errors, recipient CRUD/subscriptions, history, guest/customer access, CSRF on every write.
- `php tests/rentals-http-flow.php <QA URL>`: dated multi-item cart, checkout, encrypted proofs, QR/status, Admin approval/rejection and proof resubmission.
- `php tests/rentals-admin-qa.php <QA URL>`: existing Admin authorization, CRUD/uploads, malicious files, managed QR/image routes, blackouts, filters, CSRF, reports, production safe errors, mounted redirects/logout and unchanged `/information` CTA.
- `php tests/rentals.php`: existing isolated cart/catalog behavior.
- `php tests/rentals-urls.php`: root/mounted/absolute URL behavior.
- `php tests/rentals-integration.php`: existing core schema/status/review/QR relationships.
- `php tests/rentals-completion.php`: cart/date/proof/totals/status/stock/Admin reports.
- `php tests/rentals-live-regression.php`: inquiry-only items, date-range stock accounting, password validation.
- `php tests/rentals-payment-report.php`: reporting and exact additive-schema diagnosis fixtures.
- `php tests/rentals-proof-view-qa.php`: encrypted image/PDF response and proof/empty-state markup.
- `php bin/rentals-mailer.php --check` and `php bin/rentals.php --check`: passed.
- PHP lint on every modified/new PHP file: passed. `node --check js/rentals-admin.js`: passed; no application JS was changed.
- `git diff --check`: passed. `git status` reviewed; changes remain uncommitted.
- Browser QA at desktop (1304px), tablet (768px), mobile (390px): templates/editor/preview/recipients checked; no horizontal document overflow. Preview preserves CSP and the 3AM navy/yellow design language.

## Files Modified

Mailer-pass files below; earlier uncommitted diagnostic-cleanup work is retained separately and was not recreated or reverted.

```
app/Controllers/Rentals/RentalEmailController.php                  NEW
app/Controllers/Rentals/RentalCheckoutController.php
app/Controllers/Rentals/RentalAccountController.php
app/Controllers/Rentals/RentalAdminController.php
app/Core/Database.php
app/Models/RentalMailSettings.php                                 NEW
app/Services/RentalNotification.php
app/Services/RentalCheckout.php
app/Services/RentalMailEvents.php                                 NEW
app/Services/RentalMailTransport.php                              NEW
app/Services/RentalSmtpTransport.php                              NEW
app/Services/SmtpDeliveryUncertain.php                            NEW
app/Services/SmtpMailer.php
app/Views/rentals/admin/email-templates.php                        NEW
app/Views/rentals/admin/email-template-edit.php                    NEW
app/Views/rentals/admin/email-recipients.php                       NEW
app/Views/rentals/admin/email-history.php                          NEW
app/Views/rentals/emails/notification.php                          NEW
app/Views/rentals/emails/content.php                               NEW
app/Views/rentals/partials/email-heading.php                       NEW
app/Views/rentals/partials/admin-header.php
bootstrap.php
routes/web.php
css/rentals-admin.css
database/rentals-mailer-additive.sql                               NEW
database/rentals.sql
bin/rentals-mailer.php                                            NEW
bin/rentals.php
tests/rentals-mailer.php                                          NEW
tests/rentals-mailer-http.php                                     NEW
tests/rentals-smtp.php                                            NEW
tests/rentals-dev-router.php
tests/rentals-http-flow.php
tests/rentals-admin-qa.php
docs/rentals-mailer-2026-10-02.md                                  NEW
```

Main-site page controllers/views, main CSS, Inquiry templates/configuration, customer/payment/proof schema, production `.env` and upload storage configuration were not changed by the mailer pass.

## Manual Configuration Required

1. **Migration authorization:** review `database/rentals-mailer-additive.sql` against the live canonical core tables, back up as normal, and have the authorized server administrator apply it. No automatic live migration was performed. Run the two read-only schema checks afterwards. Until then, mail settings show a clean unavailable state and notification failures do not undo saved transactions.
2. **SMTP/server requirements:** configure the existing `MAIL_ENABLED`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (`tls`/587 or `ssl`/465), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, optional `MAIL_TIMEOUT` and existing support address `MAIL_INQUIRY_TO` privately. Sender must be authorized by the provider/domain. PHP needs OpenSSL, a valid CA trust store, and outbound access to the configured SMTP port. Preserve the correct existing `APP_URL`/`APP_BASE_PATH` so email review/status links use the live host and `/web-dev/3am-web/` mount. No secrets are supplied in this report.
3. **Recipient setup:** Admin → Email Notifications → Recipients, add actual internal addresses and choose subscriptions. Use Send test email explicitly, then retest one successful checkout, one approval, one rejection and proof replacement against the provider/inbox before declaring live delivery verified.

Confirmed: no `.env` change, no credentials hardcoded, no live DB change, no commit, no push, no merge. The local implementation is ready for review; live delivery cannot be claimed verified until the authorized migration/configuration and server test are completed.
