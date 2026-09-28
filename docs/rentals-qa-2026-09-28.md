# Rentals QA and fixes — 28 September 2026

Current branch: `feature/website-ui-refresh`. Main prompt was applied first,
then the Admin QA addendum. The merged working tree was preserved.

## Findings and fixes

- **Duplicate deployment prefix:** Rentals controllers passed `url(...)` to
  `Controller::redirect()`, which called `url()` again. The shared helper now
  recognizes an already-mounted path at a path boundary. `Request` also strips
  only an exact mount/path boundary. `config/app.php` normalizes the mount and
  avoids adding it twice when `APP_URL` already includes it. No deployment
  prefix is hardcoded in Rentals feature code.
- **Authentication:** Admin/Superadmin login goes directly to Admin. Customer
  return destinations are allowlisted, including My Rentals. Logout clears the
  full session, destroys its server record and expires its cookie. Guest Admin
  and Orders redirect to Account; empty Checkout redirects to Cart. Root and
  simulated subdirectory tests passed.
- **Products:** Current merged DB CRUD was functional on the checked local
  schema. The URL fix restores mounted Add/Edit redirects. Validation now
  finishes before storing a product upload, preventing files being orphaned by
  invalid form data. Add, edit, image retain/replace, category relationship,
  rate, deposit, quantity and deactivation were verified with real local DB
  records. No mock inventory was introduced or database rebuilt.
- **Calendar:** The previous refresh replaced the grid with loading text and
  used mutable month state after asynchronous requests. It now captures the
  requested month, keeps the existing grid disabled while loading, and ignores
  outdated responses. New item dialogs reset to the current month. Quantity
  changes keep the picker open and its month, clear dates and recheck stock.
  Range selection also ignores stale completion/errors. One custom picker is
  preserved, with server validation authoritative.
- **Rentals Home:** Hero dark, Categories light, Featured dark, Services light,
  How to Rent dark, Use Cases light, Support dark. Desktop hero min-height
  subtracts the header from the viewport; at 1440×900 the hero ends at y=900,
  so the next section does not peek. Small-screen content can grow naturally.
  Main-site styles and content were preserved except the requested link.
- **Start Renting:** Information's existing button now links to `url('rentals')`.
- **Orders:** Existing textual Pending/Approved/Rejected badges and filters were
  preserved. Pending review orders sort first in the default list. Search by
  order number/customer name and no-match state passed.
- **Reporting:** Existing merged code already included `[TEST]` snapshots and
  calculated Sales/Dashboard/Analytics revenue from Approved-only `subtotal`.
  This was preserved and tested, including a two-detail order counted once.
  The stale Dashboard exclusion note and documentation were removed. Payment
  Report now exposes rental amount, deposit, total, review date and reviewer;
  Approved collected money uses `total_amount`, Pending amount is separate.
  Rejected money contributes neither to sales nor collected totals. Dashboard
  retains its four important KPIs, including its separate Pending count.
- **Payment QR:** Direct QR URLs beneath `micro/payment` were denied by Apache
  along with proofs. A validated image-serving route now delivers active manual
  method QR images, and authorized Admin previews, while retaining the private
  proof directory protections.
- **Production errors:** Production cannot enable visible traces using
  `APP_DEBUG=true`. Query internals stay in server logs; form-visible DB errors
  are generic. The central 500 response retains CSP and other security headers.

## Database and deployment

No schema changes or production SQL were executed in this pass. Canonical schema
and checker already include payment review fields, QR path and blackout table.
The local checker passed, with original records preserved after test cleanup:

| Table | Records |
|---|---:|
| users | 3 |
| rental_categories | 1 |
| rental_items | 2 |
| rental_item_blackouts | 0 |
| carts | 2 |
| cart_items | 1 |
| payment_methods | 3 |
| order_header | 12 |
| order_details | 14 |

Exact conditional-on-inspection additive SQL for missing deployed review fields
and QR path is documented in [rentals.md](rentals.md#existing-deployed-database-missing-review-fields).
`CREATE TABLE IF NOT EXISTS` does not add columns to an existing table. Do not
run a fresh-schema script as a substitute for reviewing deployed migrations.

## Verification

- PASS: `C:\php84\php.exe bin/rentals.php --check`.
- PASS: PHP lint on all 23 modified/new PHP files; `node --check js/rentals.js`.
- PASS: `tests/rentals.php`, `rentals-integration.php`, `rentals-completion.php`.
- PASS: `tests/rentals-urls.php`: root, `/web`, `/web-dev/3am-web`, already-mounted
  URLs, absolute links, query/fragment preservation and Request path boundaries.
- PASS: `tests/rentals-http-flow.php` on root and simulated deployed mount:
  multi-item cart, registration/login, proof validation/encryption, status QR,
  Admin/Superadmin direct login, approval/rejection metadata, resubmission,
  customer My Rentals return destination.
- PASS: `tests/rentals-admin-qa.php` on root and simulated mount: DB CRUD,
  uploads, QR delivery, deactivation, blackouts/unblocking, search/status filters,
  all nine Admin write route types rejecting invalid CSRF, financial assertions,
  logout and production-mode 500 with intentionally failed DB read.
- PASS: browser checks at 1440×900, 768×1024 and 390×844. Rentals and Admin
  pages had no horizontal page overflow or broken visible images. Browser
  calendar selection, quantity/month retention, rapid navigation and search
  worked. No browser console errors observed in valid flows.
- PASS: root and Information rendered without Rentals CSS; Information button
  was clicked successfully into the mounted Rentals module.
- PASS: `git diff --check`.

Actual deployed Apache/database permissions, deployed schema, production mail
delivery and separate Chrome/Firefox/Edge/Safari installations still require
live/manual QA. Local browser QA used the available Chromium-based in-app
browser; simulated mounting is not a claim of production deployment validation.

## Admin QA matrix

All PASS results below refer to local and simulated mounted tests.

| Case | Result | Evidence |
|---|---|---|
| TC-ADM-AUTH-001 | PASS | Admin/Superadmin login reaches Admin with one prefix. |
| TC-ADM-AUTH-002 | PASS | Guest Admin redirects to Account; customer Admin access forbidden. |
| TC-ADM-ORD-002 | PASS | Order/name search, status filters and no-match state checked. |
| TC-ADM-ORD-004 | PASS | Approval records status 1, reviewer/time and paid_at; customer status Approved. |
| TC-ADM-ORD-005 | PASS | Rejection records status 2, reviewer/time and NULL paid_at; customer status Rejected. |
| TC-ADM-ITM-005 | PASS | Product/category/managed image inserted and visible publicly. |
| TC-ADM-ITM-006 | PASS | Edits persist; image retained/replaced and old managed image removed. |
| TC-ADM-ITM-007 | PASS | Inactive product hidden and Add to Cart rejected; historical orders preserved. |
| TC-ADM-CAT-003 | PASS | New category persisted and offered by product/public category UI. |
| TC-ADM-SLS-002 | PASS | Approved subtotal only; deposits/states excluded and header counted once. |
| TC-ADM-PAY-REP-001 | PASS | Report loads; separate amounts/statuses and review fields available. |
| TC-ADM-PAY-CFG-003 | PASS | Inactive method excluded at Checkout; historical orders readable. |
| TC-ADM-SEC-002 | PASS | Invalid CSRF yields 419 across all Admin write route types without writes. |
| TC-ADM-SEC-003 | PASS | Renamed scripts/oversized images rejected; valid uploads use random paths. |
| TC-ADM-SEC-004 | PASS | Production error is generic 500 with retained security headers. |

## Files changed

```text
app/Controllers/Rentals/RentalAccountController.php
app/Controllers/Rentals/RentalAdminController.php
app/Controllers/Rentals/RentalCheckoutController.php
app/Core/Database.php
app/Core/Request.php
app/Helpers/functions.php
app/Services/RentalAccount.php
app/Services/RentalAdminInsights.php
app/Views/pages/information.php
app/Views/rentals/admin.php
app/Views/rentals/checkout.php
app/Views/rentals/index.php
app/Views/rentals/partials/admin-dashboard.php
app/Views/rentals/partials/admin-payment-report.php
bootstrap.php
config/app.php
css/rentals.css
docs/rentals.md
docs/rentals-qa-2026-09-28.md (new)
index.php
js/rentals.js
routes/web.php
tests/rentals-http-flow.php
tests/rentals-integration.php
tests/rentals-admin-qa.php (new)
tests/rentals-dev-router.php (new)
tests/rentals-urls.php (new)
```

Git state: 23 modified tracked files and four new files, unstaged/uncommitted.
No branch change, commit, push, merge, reset, .env edit or production DB change.
Temporary uniquely identified test records/uploads/visual Admin were removed;
existing user data, stock and images were retained.
