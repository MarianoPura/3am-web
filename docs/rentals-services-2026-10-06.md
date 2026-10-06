# Services request/history pass — 6 October 2026

## Scope and confirmed cause

The user authorized completing necessary Services functions while preserving the
working live Gmail configuration. The existing flow was enquiry/quotation first.
Services linked to `/start?type=Rentals&rental=...`, but the main InquiryController
neither consumed the selected service ID nor matched the capitalized `Rentals`
preset. Those enquiries were not `order_header` records and had no Rentals
customer history or Admin status queue. No historical service/order mapping can
be reconstructed reliably from that ignored query parameter; none was invented.

Local `MAIL_INQUIRY_TO` is explicitly set to `3ammediatech@gmail.com`. The local
contact and mail reply configuration derive from this value. `.env` is ignored
by Git; merging development updates the code, not local/live environment values.
The user's reported live `puramariano0206@gmail.com` value was not changed or
independently inspected in the live environment.

## Delivered behavior

- Selected active service → Rentals login if needed → scoped service request form.
  The service selection survives login/registration. Customer name/email come
  from the authenticated account, not posted recipient fields.
- Event start/end, phone, location and crew/coverage/equipment requirements are
  validated. Past/invalid/reversed dates and excessive lengths are rejected with
  visible field errors; valid entered values remain on the form.
- Customer **Service requests** history includes Pending, Approved and Rejected
  filters, date/location/scope snapshots and the team's customer-visible message.
  Queries filter by authenticated `user_id`; no other customer's records are shown.
- Admin/Superadmin **Service Requests** queue defaults to Pending, with search,
  filters, request details and Approve/Reject. Final decisions cannot be silently
  overwritten by repeated/conflicting clicks. Global CSRF middleware protects POSTs.
- Quotations use their own `rental_service_requests` records. They do not create
  financial/equipment orders, reserve equipment stock, affect revenue or claim
  payment approval. Approval means proceed with coordination/quotation; final
  scope/payment remain arranged with the team.
- The submit button disables, turns gray and displays a decorative spinner plus
  `SUBMITTING...`/`aria-busy`. Native POST failures render a fresh enabled form;
  browser history restoration also restores the button. A session-issued key,
  database unique key and customer-row lock prevent repeated/concurrent POSTs
  from creating duplicate requests.
- Request receipt reuses **the existing InquiryStore confirmation implementation**
  with the selected service/reference/dates/location included in enquiry details.
  A per-request atomic claim permits at most one confirmation capture/send attempt
  after commit. SMTP/storage notification failure does not undo the saved request.
  Existing inquiry email content/configuration/recipients/CC/sender are unchanged.
  Admin review updates the Services history; it does not send new payment-status
  emails or claim a payment was approved. No new mail templates/settings/Admin UI.
- History retains saved snapshots when a service/category later becomes inactive.
  Inactive/unavailable services cannot accept new requests. Samples do not submit.

## Deployment dependency

One new table is necessary to persist ownership, request data and review state
without overloading existing payment-order fields. Exact portable additive SQL:
`docs/rentals-service-requests-additive.sql` (20 columns; unique reference/submission
keys; customer/status/item/reviewer indexes; foreign keys to existing BIGINT
UNSIGNED users/rental_items IDs). The SQL creates only this table; it never alters
existing tables, inserts business records or changes mail configuration.

The table was explicitly created **only in the guarded local loopback database**
for implementation/testing. No DDL runs on application requests or in tests.
The live administrator must review/apply this SQL before deploying these routes.
If the table is absent, the new request/history/review endpoints return a clean
503 state; the existing Services catalogue and other modules continue to operate.
Do not copy the local `.env` to live or replace live SMTP/Gmail settings.

No live database action, deployment, secret edit, commit, push or merge occurred.
The senior's existing controllers, mailer and schema architecture were retained;
the new controller/service is scoped to the missing service enquiry lifecycle.

## Validation

- PHP lint: new controller/service, routes, account controller, new/modified
  Rentals views and service tests: PASS.
- `php tests/rentals-services.php`: PASS (isolated fixtures, isolated enquiry
  backup; validation/auth/history/review/status/escaping/idempotency; simulated
  loopback SMTP connection refusal; no real emails or existing-data edits).
- `php tests/rentals-services-http.php http://127.0.0.1:8017`: PASS (actual routes,
  CSRF/form errors/login return/replay/customer/Admin/Superadmin/history). HTTP
  replay uses an already captured fixture so it does not write into the user's
  existing enquiry backup; initial save/capture is tested separately above.
- Same Services HTTP suite at `/web-dev/3am-web/` on a second loopback QA server:
  PASS, including mount-aware CTA/login/POST/history/Admin review URLs.
- Existing `php tests/rentals-qr-ownership.php` on that mounted QA server: PASS;
  the additional service login return does not bypass existing order ownership.
- `node tests/rentals-services-ui.mjs`: PASS (invalid form, immediate disable,
  loading text/spinner/aria-busy, repeated-click blocking, back/forward recovery).
- Existing `php tests/rentals-mailer.php`: PASS (dynamic saved customer + current
  environment CC, combined Pending/Approved/Rejected, duplicates, post-commit mail,
  safe failure handling, no editable mailer). All transport mocked; no real mail.
- Existing `php tests/rentals-mailer-http.php http://127.0.0.1:8017`: PASS (removed
  editable email pages/actions remain 404; Admin menu remains free of mail settings).
- Services schema check: all 20 expected columns, primary/unique/filter indexes
  and three ownership/item/reviewer foreign keys present; tested on local only.
- Existing `node tests/rentals-qa-ui.mjs`: PASS; stock/calendar behavior retained.
- Browser checked real templates with disposable local test users/services and
  Pending/Approved/Rejected requests at 1440, 768, 390 and 320 px. No horizontal
  document overflow after correcting narrow heading/card wrapping. Admin queue
  retains a contained horizontal table scroll on mobile.
- Screenshot evidence under the task visualization root `latest-qa/`:
  `services-request-desktop.png`, `services-request-mobile.png`,
  `services-history-desktop.png`, `services-history-mobile.png`,
  `services-admin-queue.png`, `services-admin-review.png`.
- Home `/`, `/information`, `/rentals/items`, `/rentals/services`: HTTP 200.
  No main-site view/CSS/controller edits.
- Mail/config/env file hashes are checked unchanged before/after. Temporary
  Services fixtures are removed; existing user enquiries remain untouched.
- `git diff --check`: PASS. Working tree inspected; no staging/commit/push/merge.

## Files in this pass

New: `app/Controllers/Rentals/RentalServiceRequestController.php`,
`app/Services/RentalServiceRequests.php`, customer `service-request.php` and
`service-requests.php`, Admin equivalents, `partials/service-request-details.php`,
the additive SQL/report and `tests/rentals-services.php`,
`tests/rentals-services-http.php`, `tests/rentals-services-ui.mjs`.

Updated: `routes/web.php` (new Services routes only),
`RentalAccountController.php` (allowlisted login return), Rentals `services.php`,
`account.php`, public/Admin header links, `js/rentals.js` (scoped submission UI),
`css/rentals.css` and `css/rentals-admin.css` (new Services layout only).

Earlier uncommitted QA changes were preserved, not redone or committed.
