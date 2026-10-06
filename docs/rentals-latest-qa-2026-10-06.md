# 3AM RENTALS — LATEST QA FIX REPORT

2026-10-06 · `feature/website-ui-refresh` · local verification, live deployment
not performed. Scope: latest master QA attachment and `Fixes(2).docx`, preserving
the senior's current separated architecture and existing uncommitted category fix.

## Local Seed Regression

- Root cause: `RentalCatalog::load()` still required the intentionally deleted
  `database/seeds/rentals.php` when preview mode met an empty/failed database.
  Requiring it inside the error fallback caused another fatal exception.
- Correction after comparison with the original document: restored the senior's
  existing optional preview behavior and configuration. Both optional seed reads
  now check readability first, so the missing file cannot cause another fatal error.
  No seed file was restored and no demo inventory was invented.
- Existing DB inventory takes precedence. The local category shared by four active
  equipment items and one active service was inactive before this QA pass; it was
  reactivated locally at the user's request to restore the catalogue. Item rows,
  images, prices, quantities, orders and reservations were not changed.
- `/rentals` and `/rentals/items` return HTTP 200 locally, including mounted deployment.

## QR / Status Security

- Guest: valid token route redirects to Rentals login; login return still requires ownership.
- Owner: authenticated owner can access the minimal status/confirmation payload.
- Other customer: HTTP 403, without order/QR/private fields.
- Admin/Superadmin: customer token routes redirect to the dedicated authorized
  `/rentals/admin/orders/<id>` page. That page still shows payment/order review status.
- The prior Admin token rendering violated the latest role-separation requirement;
  this was corrected without weakening Admin authorization.
- Numeric tokens and unknown tokens return 404. Another customer's numeric proof
  ID returns 404; customers cannot access Admin orders/proofs (403).
- Tokens remain 32 cryptographically random bytes encoded as 64 hex characters
  (256 bits). A token locates an order; possession alone never grants access.
- QR payload contains only the protected status URL. Status views omit customer
  contact details, payment references, notes and proof/internal storage paths.
- Token pages now have scoped `no-referrer` and `noindex, nofollow` metadata;
  no third-party QR service or tracking was added. Notification failure logs use
  event/order ID/result categories, not tokens or proof contents.
- Web-server access logs may inherently record requested token URLs. Redacting
  those paths is a hosting/log-retention concern; no server log configuration
  or broad middleware rewrite was performed.
- Existing CSRF, protected image/PDF proof routes and proof encryption were preserved.

## Status / QR UI and Account Dropdown

- Existing centered status CSS already met the latest layout requirement; it was
  verified rather than replaced. Status card, QR, badge and download-button centers
  matched at 320, 390, 768 and 1440 px. Confirmation QR and Return button matched too.
- Replaced the baseline-sensitive caret glyph with a decorative inline SVG in the
  existing header control. Account text/caret vertical offset: 0 px across four widths.
  Sign In offset: at most 0.5 px. Enter opens; Escape closes; `aria-expanded` updates.
- No extra dropdown implementation, negative margins, palette or main-site redesign.

## Multiple Product Images

- Read-only local schema: one nullable `rental_items.image_path` varchar(500);
  no gallery JSON field or media/image relation is present.
- A schema addition is required. The removed `rental_item_images` table was not recreated.
- Proposal: nullable `additional_image_paths JSON`, retaining `image_path` as primary.
  Exact read-only precheck/additive ALTER: `docs/rentals-multiple-images-proposal.sql`.
- Admin multiple-upload/individual removal and customer carousel/swipe/keyboard
  remain **not implemented**. The original document requests this feature; the
  approval prerequisite came from the generated prompt, not the original document.
- Detailed backward compatibility, managed storage, upload validation and cleanup
  plan: `docs/rentals-multiple-images-proposal.md`. No gallery migration was executed.
- Existing single-image display, managed image serving, Admin authorization,
  invalid-upload handling and fallback remain intact and passed relevant QA.

## Manual Block Removal

- Added native Block dates / Remove block radio modes to the existing Admin
  availability form; reuses existing `availability_action=available` backend logic.
- Remove mode hides/disables the maintenance note and updates the submit label/guidance.
- Existing transaction locks and blackout-only range subtraction were retained.
- Full removal, trim start/end, middle split, single day, month boundary and overlapping
  rows passed deterministic tests. `order_details` snapshots remained unchanged.
- Browser submission removing Oct 12–13 from Oct 10–15 left active Oct 10–11 and
  Oct 14–15 segments. Customer calendar showed 12–13 available with the other four
  dates still blocked. Reservations on other days remained visible and unchanged.

## Calendar Date Parity

- Earlier date-string/local-noon handling is preserved; this pass found no new
  off-by-one regression and did not replace the shared availability model.
- Inclusive Oct 10–12 covers exactly three dates, never Oct 13.
- Rendering/range-minimum tests passed in Asia/Manila, UTC, America/Los_Angeles and
  Europe/London, including month/year boundaries.
- Final user clarification is authoritative: **allow remaining units; disable fully
  booked dates**. Partial reservations are selectable, with remaining-capacity labels.

## Quantity

- Physical capacity 5, reserved 2: selected-date capacity 3. The maximum is the minimum
  remaining capacity across every selected day, including overlapping own-cart lines.
- Cart editing excludes only its own line; a supplied line ID cannot exclude another
  user's cart. No new stock/reservation table or second availability implementation.
- Modal/cart set min 1, date-specific max, clamp typed values above max and disable
  zero-capacity quantity controls. API data refreshes the limit on date changes.
- Rental-date and quantity inputs remain aligned despite the new capacity hint
  (0 px top offset in the final desktop check); the bottom message no longer
  repeats that hint. The final mobile modal still has no horizontal overflow.
- Browser testing found clamped cart typing could suppress native `change`; blur now
  persists the correction once. Normal increment and typed-overstock autosave both passed.
- Checkout link is grey/inactive while a cart edit is unsaved, preventing checkout
  against the previous saved dates/quantity. Valid date recovery restores the flow.
- API returns unavailable days for overstock quantities; malformed/fractional/out-of-range
  quantity is rejected. Add to Cart/Cart update reject manipulated excess quantity.
- Existing checkout transaction locks cart, lines and equipment in sorted order and
  revalidates daily aggregate capacity before saving. Stale stock tests created no order.
- Stock quantity stays directly below Availability, as previously requested.

## Analytics Revenue Graph

- Old source already used real `order_header.subtotal` for approved status 1, scoped
  to orders with persisted details. No random/sample chart source was found.
- Defects were presentation/aggregation: missing zero-date buckets, compressed time
  spacing, square SVG inside a wide area, artificial minimum bar height and a floor
  on the displayed highest amount.
- Source/report definitions remain unchanged: approved rental subtotal, deposits
  excluded, grouped by order submission date. Pending/rejected revenue is excluded.
  There is no additional refund/cancelled revenue state in the current workflow.
- Added zero buckets and explicit range/aggregation/highest-date captions. SVG fills
  its container; zero transaction counts have zero-height bars; an empty approved
  period shows an intentional empty state. Sub-peso peaks retain their real amount.
- Up to 366 days: daily; longer custom ranges up to 3650 days: monthly; longer: yearly.
  This bounds bucket count without changing approved totals or report queries.
- Deterministic fixtures: day 1 approved 100 + 50, day 5 approved 0.50, intervening
  zero days; pending 9000/rejected 8000 and next-day boundary record excluded.
  Chart and Payment/Sales Report approved totals all equal 150.50; highest day 150.
- Local DB session timezone SYSTEM/Asia-Singapore and PHP Asia/Manila are both UTC+8.
  Live timezone alignment was not inspected or changed.

## Regression and Limits

- Mailer mock and loopback SMTP tests pass combined Pending/Approved/Rejected,
  state/event deduplication, actual CC headers/envelopes and post-commit failure safety.
  Removed editable mailer URLs/writes remain 404 for all tested roles.
- **Existing senior-code difference:** `config/rentals-mail.php` now reads environment
  Owner/CC values, and `RentalNotification` sends to the saved customer with configured
  CC only. It currently does not send a separate Owner notification. Therefore the
  historical fixed Owner/three-CC routing requirement is not verified/met by this
  checked-out implementation. Those senior changes were not reversed in this QA pass;
  no mailer/config/SMTP changes or real emails were made.
- Checkout HTTP flow, duplicate protection, submit spinner/loading/aria behavior,
  QR ownership, Admin CRUD/RBAC/CSRF/uploads, availability and reports passed locally.
- Main `/` and `/information` return 200 and were briefly checked in the browser;
  no main-site view/CSS/route was edited. Intentionally configured main Rentals link retained.
- Desktop 1440, tablet 768, mobile 390 and narrow mobile 320 checked for status,
  confirmation, header, equipment modal, cart, availability and analytics. No settled
  document horizontal overflow in those checks. Existing carousel overflow is contained.
- Multi-image carousel remains unimplemented and its tests are not marked passed.
- All visual fixtures were disposable local rows, cleaned afterward. Temporary QA
  upload storage, browser tab/viewport override and isolated server were cleaned/stopped.
- **Locally verified / live confirmation pending.** No claim of a live deployment/test.

## Database

- Live changes: **NO**. Persistent local schema changes: **NO**.
- The proposed gallery column has not been added; multi-image persistence is missing.
- Migration executed: **NO**. Existing stock, blackout and order tables reused.
- `bin/rentals.php --check` still fails because its parser requires the intentionally
  removed `database/rentals.sql`. This pre-existing checker/source mismatch is recorded
  rather than recreating the senior's deleted schema speculatively. It is not proof
  of a runtime DB failure. Read-only integration and Payment Report schema checks pass.

## Tests

Commands ran from `C:\Users\PC-3\3am-web`; PHP = `C:\php84\php.exe`.
HTTP base = `http://127.0.0.1:8018/web-dev/3am-web`.
The QA server/HTTP clients used the same isolated `RENTALS_STORAGE_ROOT`; server
`MAIL_ENABLED=false`, without editing `.env`. HTTP tests are loopback-only.

| Command | Result |
| --- | --- |
| `C:\php84\php.exe tests\rentals.php` | PASS: guest cart, quantity/date bounds, images, honest unavailable catalogue despite stale preview env flag |
| `C:\php84\php.exe tests\rentals-latest-qa.php` | PASS: stock/API/cart/checkout, seven unblock cases/reservation preservation, date parity and deterministic revenue/report comparisons |
| `node tests/rentals-qa-ui.mjs` | PASS: type controls, four timezones, partial/full stock, quantity clamp/range minimum and clamped cart save once |
| `node tests/rentals-submit-ui.mjs` | PASS: spinner/disabled/aria-busy, duplicate prevention, native POST and recovery |
| `C:\php84\php.exe tests\rentals-qr-ownership.php <HTTP base>` | PASS: guest/owner/other/Admin/Superadmin, numeric tampering, minimal opaque QR, no-store/logout, scoped referrer/index metadata |
| `C:\php84\php.exe tests\rentals-http-flow.php <HTTP base>` | PASS: real local request/proof/checkout flow; one initial harness storage-root mismatch was corrected before this successful run |
| `C:\php84\php.exe tests\rentals-admin-qa.php <HTTP base>` | PASS: separated Admin CRUD, images/uploads, dates, CSRF/RBAC, reports/analytics and proof review |
| `C:\php84\php.exe tests\rentals-ui-categories.php` | PASS: duplicate-name legacy status edits; new duplicate rejection; hidden empty public categories |
| `C:\php84\php.exe tests\rentals-live-regression.php` | PASS locally: inquiry items, multiple date ranges, actual overbooking and password validation |
| `C:\php84\php.exe tests\rentals-urls.php` | PASS: root/mount/redirect/query/fragment behavior |
| `C:\php84\php.exe tests\rentals-mailer.php` | PASS with mock: dynamic customer/environment CC, combined events, deduplication, SMTP failure safety |
| `C:\php84\php.exe tests\rentals-smtp.php` | PASS loopback: actual To/CC delivery and success/rejection/uncertain cases |
| `C:\php84\php.exe tests\rentals-mailer-http.php <HTTP base>` | PASS: removed settings pages/writes and main/Rentals HTTP 200, no order changes |
| `C:\php84\php.exe tests\rentals-integration.php` | PASS: core/blackout/review/QR schema, integer status, token/detail relationships (read-only) |
| `C:\php84\php.exe tests\rentals-payment-report.php` | PASS: isolated temporary-table missing-column cases, filters/totals/additive proposals; no persistent migration |
| `C:\php84\php.exe tests\rentals-proof-view-qa.php` | PASS: encrypted image/PDF response and Admin proof/empty markup |
| `C:\php84\php.exe bin\rentals.php --payment-report-check` | PASS: compatible schema and actual SELECT |
| `C:\php84\php.exe bin\rentals.php --check` | FAIL: missing canonical SQL source as explained above; no migration |
| `C:\php84\php.exe -l <each modified PHP file and tests/rentals-latest-qa.php>` | PASS: all 17 PHP files |
| `node --check js/rentals.js`; `node --check js/rentals-admin.js` | PASS |
| `git diff --check` | PASS |
| `git status --short`; `git branch --show-current` | Changes uncommitted; expected branch retained |

## Files Modified / Added

Exact project-relative paths (all under `C:\Users\PC-3\3am-web`):

```text
app/Controllers/Rentals/RentalAdminController.php
app/Controllers/Rentals/RentalCartController.php
app/Controllers/Rentals/RentalCheckoutController.php
app/Models/RentalCatalog.php
app/Services/RentalAdminInsights.php
app/Views/rentals/admin/items-edit.php
app/Views/rentals/cart.php
app/Views/rentals/confirmation.php
app/Views/rentals/items.php
app/Views/rentals/partials/admin-analytics.php
app/Views/rentals/partials/header.php
app/Views/rentals/status.php
css/rentals-admin.css
css/rentals.css
js/rentals-admin.js
js/rentals.js
tests/rentals-qa-ui.mjs
tests/rentals-qr-ownership.php
tests/rentals-ui-categories.php
tests/rentals.php
tests/rentals-latest-qa.php (new)
docs/rentals-multiple-images-proposal.md (new)
docs/rentals-multiple-images-proposal.sql (new; proposal only)
docs/rentals-latest-qa-2026-10-06.md (new)
```

The Admin category-controller fix and its category tests were already present
uncommitted at the start and preserved. No unrelated senior changes were reverted.

## Confirmation

`.env` untouched; no live DB mutation; no secrets exposed; no authorization or
encryption bypass; no commit, push, merge, pull, rebase or branch switch.
The implemented subset is ready for local review. The whole original task is not
complete: gallery implementation is missing, the full schema-source
checker remains unavailable, and the existing senior mail routing differs from
the historical fixed-owner requirement.

## Original-document correction and existing-work audit

- The generated prompt added a DB-only implementation preference and gallery
  approval condition. Neither is treated as an original-document requirement.
- Reverted the preview/configuration removal; the remaining catalogue diff consists
  of two missing-file guards. The active-category filter already existed and stays.
- Local data correction: only category ID 5 (`test-rentals-category`) changed from
  inactive to active. Its previous row was backed up outside the repository.
  Existing IDs 4, 5, 102, 107 and 346 were verified unchanged after the update.
- Browser verification: Equipment displays four existing cards with four loaded
  images; Services displays the existing service. Neither shows the empty message.
- Requested fixes retained rather than repeated: QR/customer/Admin separation,
  account caret alignment, manual unblock range selection, stock/quantity limits,
  partially reserved dates and deterministic revenue graph.
- Existing unfinished areas explicitly remain unfinished: multiple-image uploads
  and per-product carousel, plus the deleted-source full schema checker.
- No changes to senior cleanup code, architecture, live data, schema, main-site UI,
  mail configuration, `.env`, or Git history in this correction.
- Recheck results: PHP lint for catalogue/config/test; missing-seed tests for both
  successful-empty and failed queries; category status/visibility tests; stock,
  manual unblock and revenue tests; Payment Report tests; four-timezone JS tests;
  JS syntax; HTTP QR ownership tests; read-only core schema checks: all PASS.
  `git diff --check` PASS. Home, Equipment, Services, Categories, main `/` and
  `/information`: HTTP 200. Equipment/Services no longer show the empty message.
- Browser evidence from the existing restored inventory:
  `latest-qa/equipment-restored-original-scope.png` and
  `latest-qa/services-restored-original-scope.png` under the task visualization root.

## Admin follow-ups: past dates, metric sizing and service form

- Admin availability API now includes `past` and `available` using the existing
  application timezone. Past days are gray and say `Past / unavailable`;
  historical reservation/stock counts remain intact for Admin management.
  Partial reservations show the remaining available units; full reservations
  and manual blocks have separate labels. Historical manual blocks can still
  be selected/removed. Customer calendar code/behavior was not changed.
- Dashboard and analytics metric values fit their actual card width. Font size
  recalculates after card resizing, web-font loading and text changes, restoring
  the normal size for shorter values. Values are never rounded, abbreviated,
  ellipsized or changed to fit. Browser checked 1,220,000.00, 12.50 and
  9,999,999,999,999.99 at desktop/narrow mobile with full text inside the card.
- Add/Edit Service presentation now uses service name, event/production examples,
  coverage/crew/equipment scope, event/project types, service reference code,
  pricing basis and starting price. Stock, availability and deposit fields remain
  hidden/disabled for services. Type switching updates the presentation without
  overwriting entered values. Existing fields and service inquiry flow are reused;
  no new table/column, catalog record or service package was invented.
- PHP lint, JS syntax, Admin/customer UI tests, real local Admin HTTP CRUD/service
  create/edit, availability/unblock/reservations and read-only schema checks PASS.
  Layout checked at 1440, 768, 390 and 320 px. Final narrow calendar has no label
  overflow; service form has no horizontal overflow. `git diff --check` PASS.
- Follow-up application files: `RentalAdminController.php`, `items-create.php`,
  `items-edit.php`, `rentals-admin.js` and `rentals-admin.css`. Added regression
  assertions in the existing `rentals-latest-qa.php` and `rentals-qa-ui.mjs`.
- Layout screenshots use explicitly synthetic fixtures rendered through actual
  Admin templates on a temporary loopback-only router outside the repository.
  Dashboard/Service examples in screenshots were not saved as business data.
  Actual endpoint and save behavior were tested separately with unique disposable
  fixtures; existing category 5 and all five inventory rows remain intact.
- No persistent DB/schema changes, `.env` changes, real emails, live deployment,
  commit, push or merge in these follow-ups.
