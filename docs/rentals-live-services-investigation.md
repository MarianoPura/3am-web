# Live Services investigation — 2026-10-08

## Scope and environment evidence

Working branch: `feature/website-ui-refresh`. The working tree was clean at the
start of this follow-up; the prior category-classification repair was already in
the checked-out code. No commit, push, branch switch or deployment was performed.

Unauthenticated, read-only HTTP observations against
`https://3ammediatech.com/web-dev/3am-web/`:

| Request | Result |
|---|---|
| `rentals/items` | HTTP 200; Equipment **7**, Services **0**; Equipment categories Camera, Tables, Video Production |
| `rentals/items?type=services` | HTTP 200; no listed services; existing “Service information is being updated” message |
| `rentals/admin/service-requests` | HTTP 403 as a guest; authenticated Admin response could not be verified |
| `js/rentals-admin.js` | HTTP 200; matched the local pre-follow-up JavaScript after newline normalization |

The public Equipment count is currently 7, rather than the supplied earlier 6.
The supplied production category counts also predict 7 visible Equipment items:
Camera 4 + Tables 2 + Video Production 1. The inactive Microphones category is
excluded. The only supplied Service category, Film Production, has no active
items, which predicts zero Services and no applicable Service sidebar categories.
These SQL findings were supplied by the owner, not independently queried here.

The matching public JavaScript establishes that the previous category-driven form
handler reached the live asset. It does **not** establish the deployed PHP revision,
OPcache state, database schema, grants, runtime configuration or server PHP version.

## Problem A: Service Requests HTTP 503

Confirmed source in the checked-out application:
`RentalServiceRequestController::adminList()` checks `RentalServiceRequests::ready()`.
If false, `unavailable(true, $admin)` renders the reported message with HTTP 503.
`ready()` runs:

```sql
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'rental_service_requests';
```

This checks **table visibility**, not request rows or catalogue Service counts.
A missing table, wrong configured database, or runtime-account visibility/grant
problem can cause that explicit 503 path. Missing workflow columns are a separate
condition checked by `workflowReady()`. Actual SELECT failures are exceptions,
not healthy empty lists. The list and readiness queries contain no removed item
classification-column dependency.

The exact production dependency failure remains **unverified**: no authenticated
Admin browser/session, production database connection, server logs or deployed PHP
source were available. The guest HTTP 403 is expected authorization behavior and
does not reproduce the reported Admin HTTP 503. No readiness guard was removed,
and no failure was replaced with fabricated empty rows.

Run `docs/rentals-live-services-diagnostics.sql` privately with the application's
runtime DB account. Run the existing read-only commands on the deployed checkout:

```text
php bin/rentals.php --check
php bin/rentals-completion.php --check
```

Use the same database configuration as the web application and compare the deployed
PHP files and public asset with the reviewed release. Check PHP-FPM/Apache error logs
privately, including the underlying PDOException when a query fails. Do not publish
credentials, `.env`, authentication cookies or raw customer records.

If the table is genuinely absent, review the existing proposed schema in
`docs/rentals-service-requests-additive.sql`. If quotation/payment dependencies are
missing, review only the missing `rental_service_requests` and notification clauses
in `docs/rentals-completion-additive.sql`, against `RentalSchema::COLUMNS` and
`workflowReady()`. Do not run the entire additive script blindly: it also contains
unrelated gallery/password-reset statements. No production DDL is established as
necessary until diagnostics confirm the difference. No migration was executed.

## Problem B: Zero Services and Video Production

The current local catalogue already joins `rental_categories` and projects
`c.is_service AS is_service`. Both category and item must be active. Equipment
categories require active Equipment records; Service sidebar categories are derived
from returned Service records. Existing search, More/Less, cards and CSS are preserved.

The supplied live records explain zero Services without a SQL failure: item 7 belongs
to category 6, whose classification is Equipment. Category 7 has no active items.
The old empty message made this healthy state sound like a backend outage; it now
says “No services are currently listed.” A database-unavailable catalogue says
“Services are temporarily unavailable.” The unavailable flag remains intact.

The public Video Production card showed description “video prod”, listed rate
PHP 12,300 and unit “Oct. 31, Manila Resort”, using Equipment detail/cart behavior.
Those fields suggest data requiring business review, but do not establish whether
the offering is physical equipment, an event package or a crew/production service.
Existing booking/cart/blackout dependencies could not be queried on production.

**Required owner decision:** Is item 7 a quotation-based service? If yes, which
Service category is appropriate, and how should any existing Equipment bookings
and carts be preserved? Category 6 was not changed, and no Service item was created.

`docs/rentals-video-production-proposal.sql` contains an optional, guarded item-only
reassignment to an explicitly approved existing Service category. Its default target
is NULL, so it changes nothing. It refuses items with orders, carts, blackouts or
service requests. This is a proposal requiring business review and separate production
write authorization, not a required migration or an executed repair.

## Problem C: Add Product type selection

The previous Add Product form explicitly disabled its type `<select>`. Its JavaScript
set the displayed type from category selection and did not filter categories by type.
That prevented the requested “choose Equipment/Service, then choose category” flow.
The matching deployed asset supports this handler diagnosis; authenticated production
form markup and browser console errors could not be inspected.

The new product form enables the selector as `catalogue_type`, a transient category
filter only. JavaScript filters/disables incompatible category options, clears stale
category selections when type changes, and updates existing equipment/service fields,
labels and placeholders. Matching drafts survive initialization. An empty type's
category list leaves the required placeholder, rather than inventing a category.

The backend still derives saved type exclusively from `rental_categories.is_service`.
If the new form supplies a mismatching/invalid filter value, it rejects the combination.
New products cannot select inactive categories. Existing products can remain in their
current inactive category while being edited. Edit category choices stay within the
existing type; cross-type product moves and populated category conversions remain
blocked. Existing legacy posted `is_service` values cannot override category data.

## Changed files in this follow-up

| File | Purpose |
|---|---|
| `app/Controllers/Rentals/RentalAdminController.php` | Active category choices, same-type edit choices, inactive/mismatching category validation, filter draft preservation |
| `app/Views/rentals/admin/items-create.php` | Enabled type selector and category-derived initial type |
| `js/rentals-admin.js` | Matching-category filtering, stale selection clearing, existing field behavior |
| `app/Views/rentals/items.php` | Pass database availability into the Service partial |
| `app/Views/rentals/partials/service-catalogue.php` | Distinguish healthy empty inventory from unavailable database |
| `tests/rentals-category-classification.php` | Regression for enabled selector, mismatches, inactive categories, same-type edits and empty Admin request filter |
| `tests/rentals-qa-ui.mjs` | Update existing DOM fixture for the real category select |
| `tests/rentals-product-types-ui.mjs` | New dropdown, category, fields, draft, locked-edit and no-category behavior tests |
| `tests/rentals-services-availability.php` | No-DB fixtures: empty/missing/failed requests, authorization, empty/failed catalogue |
| `docs/rentals-live-services-diagnostics.sql` | Read-only production compatibility and classification diagnostics |
| `docs/rentals-video-production-proposal.sql` | Unexecuted, guarded proposal pending business decision |
| `docs/rentals-live-services-investigation.md` | This report |

The project-wide reference audit found no remaining application, seed or fixture SQL
querying/writing the removed item column. Existing array keys named `is_service` are
category-derived aliases used by views/cart validation. References to the removed
column in the earlier repair report and regression assertions are explanatory or
assert its absence. Existing migration SQL does not reintroduce it.

## Exact local verification

Runtime: PHP 8.4.25 (`C:/php84/php.exe`). The default PHP command is PHP 8.0.30,
below the application's PHP >=8.2 requirement; no runtime configuration was changed.
JavaScript: installed VS Code Electron's Node 24.21.0, in Node mode without opening
the editor. All tests below exited 0.

| Test | Result |
|---|---|
| `tests/rentals.php` | PASS: guest cart, bounds/dates/images, honest catalogue failure and healthy empty catalogue |
| `tests/rentals-integration.php` | PASS: tables, tokens, payment fields and order relationships |
| `tests/rentals-catalogue-categories.php` | PASS: six-category limit, More/Less, escaping, empty/short lists |
| `tests/rentals-analytics-charts.php` | PASS: chart periods/timezones/zero values and schema checks |
| `tests/rentals-urls.php` | PASS: root/mounted URLs and request path boundaries |
| `tests/rentals-services-availability.php` | PASS: empty lists/catalogues 200, missing table 503, query failures retain PDO cause, guest authorization |
| `tests/rentals-category-classification.php` | PASS: Add/Edit, category filtering/validation, active rules, public catalogue, Admin empty filter/dashboard, service eligibility, cart rejection, checkout totals/proof guard, service submission/review/quote; fixture rows rolled back |
| `tests/rentals-review-regression.php` | PASS: dates, stale sessions, Cart/Checkout, quotation versions, payment replay; fixture rows rolled back |
| `tests/rentals-completion.php` | PASS: dated cart, mandatory proof, transaction totals, reservation status behavior, Admin/report queries; fixture rows rolled back |
| `tests/rentals-payment-report.php` | PASS: isolated connection-local temporary tables, missing-column diagnosis, report totals/filters; temporary fixtures removed |
| `tests/rentals-completion-ui.mjs` | PASS: gallery/chart behavior and public category/search intersection/deep links |
| `tests/rentals-product-types-ui.mjs` | PASS: type selector, category filtering, stale category clearing, fields/labels, drafts, locked edits, absent Service categories |
| `tests/rentals-qa-ui.mjs` | PASS: Admin calendars, product fields, date/quantity behavior and cart correction |
| `tests/rentals-services-ui.mjs` | PASS: service submit validation/loading/replay/history behavior |
| `tests/rentals-submit-ui.mjs` | PASS: checkout submit loading/replay/validation/navigation behavior |
| `bin/rentals.php --check` | PASS: required columns, keys and relationships |
| `bin/rentals-completion.php --check` | PASS: complete Rentals schema contract |
| PHP syntax checks | PASS: every changed/new PHP file |
| `git diff --check` | PASS: no whitespace errors |

The payment-report test intentionally reproduced SQLSTATE 42S22 / 1054 for missing
`payment_reviewed_at`, `payment_reviewed_by` and `paid_at` on its temporary fixtures;
these were expected assertions, not failures of the actual local schema.

The JavaScript tests use DOM fixtures, not an interactive browser. The browser tool
could not start because its Windows sandbox helper failed. PHP cURL initially lacked
a trusted issuer bundle; Windows cURL's certificate store successfully fetched the
public pages with TLS verification intact. Neither SSL verification nor security
checks were disabled. No authenticated production console, full HTTP proof-upload
checkout or production service approval was tested.

## Deployment requirements and remaining risks

No executed test failed. Production's authenticated 503 dependency remains unresolved
pending runtime-account/schema/log inspection. Zero Services is consistent with the
supplied classification, and cannot be solved through code by inventing inventory.

Review and approve the local changes; confirm the deployed database's existing Service
dependencies; deploy PHP/views/JavaScript together only with explicit authorization.
Use the normal approved PHP/OPcache reload procedure if needed, verify asset cache
refresh, then repeat authenticated Admin empty/populated Service Requests, Add Product
both types, public catalogue/search, and existing rental/service workflow checks.
Confirm the web PHP runtime meets the project requirement. No new public diagnostics
endpoint or automatic migration was added.

All executed database tests used rollback or connection-local temporary fixtures.
They can advance local AUTO_INCREMENT counters without retaining fixture records.
No production database writes, `.env` edits, existing-record resets/deletions, styling,
storage, authentication changes, commits, pushes or deployments were performed.
