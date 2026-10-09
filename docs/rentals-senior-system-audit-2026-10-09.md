# 3AM Rentals senior system audit — October 9, 2026

## A. Executive summary

The HOME PC application passes the coordinated local PHP, JavaScript, database and HTTP regressions. The public catalogue now filters, counts and limits records in SQL. The homepage is a bounded preview; cart and checkout lookups are independent of those limits. The responsive changes preserve the established desktop layout and visual identity.

This audit identified **14 grouped implementation gaps: 13 addressed and one partially implemented**. These are the findings listed in section F, not a claim that 38 new defects were repaired. The historical workbook contains 39 cases: **38 currently verified against the applicable current behavior, one partially implemented**. Ten of its eleven historical failures already have working fixes in the current application. Equipment rejection changes payment status correctly, but there is no dedicated Equipment rejection-reason field or workflow. That remains a business/schema decision; unrelated customer notes were not repurposed.

Scope is this Windows HOME PC only. No live server/database was contacted, no real customer export was imported, no persistent schema migration was executed during this audit, and no fixed notification recipients were changed. Test records were fictional, uniquely identified and rolled back or narrowly removed. MariaDB auto-increment gaps can remain after tests; existing business records were not reset. The original workbook and SQL export were not edited.

### Baseline and phased approach

Before editing, review covered the catalogue model/controllers/templates, the database abstraction, pagination helper, cart/checkout/date/availability logic, Equipment and Services classification, protected upload/proof paths, authorization, notification ledger, historical QA workbook and existing tests. Existing uncommitted HOME PC setup work was preserved.

1. **Baseline/plan:** identify unbounded retrieval and already implemented fixes; keep category-owned classification, same-day charging and protected proof architecture.
2. **Backend pagination:** SQL search/category/type predicates, explicit COUNT, bounded LIMIT/OFFSET, stable ordering, separate previews and keyed cart lookups.
3. **Responsive UI:** fix confirmed source defects and measure layouts/interaction in installed headless Chrome.
4. **Verified bugs:** narrow Customers/account/dashboard/metadata fixes; leave the unsupported rejection-reason schema decision pending.
5. **Regression:** isolated fixture populations, current historical-case reconciliation, local HTTP/upload/security tests and concurrent booking check.

The complete case-by-case reproduction, expected/actual results, files and evidence are in [the 39-case reconciliation](rentals-admin-qa-reconciliation-2026-10-09.md). Responsive evidence is in [the mobile audit](rentals-mobile-audit-2026-10-09.md); architecture/security/deployment details are in [the compatibility review](rentals-security-compatibility-2026-10-09.md).

## B. Requirement mapping

| Requirement | Interpretation and current result | Evidence/remaining limit |
| --- | --- | --- |
| Understand before changing; preserve senior architecture | Read existing PHP/SQL/JS/CSS and tests; kept Database bindings, category-owned type, payment-status source and distinct Services workflow. | Current schema check and classification/completion/review suites pass. |
| Verify existing senior fixes | Add Payment Method, Products page and Customer 403 were already implemented. Fixed duplicate Orders shortcut, missing SQL Customer role/search filter and incomplete account overview. | Admin HTTP QA, current browser and 60 focused assertions. |
| Genuine backend product pagination | Public Equipment/Services use matching SQL predicates, COUNT and LIMIT/OFFSET; no browser-only slicing. Admin Products retains its existing 25-row SQL pagination with smaller projection/count query. | 76 pagination assertions; 0/1/12/13/100/1,001 products. |
| Category views and other large listings | Public category cards and Admin categories now paginate. Existing Orders, Customers and service-request pages retain SQL pagination. | Added 27 populated category fixtures, exact counts and adjacent-page checks. Small payment configuration lists and dashboard summaries were not blindly paginated. |
| Search/filter/page input safety | Prepared bindings; safe integer normalization, 12-row default/48-row maximum, invalid-input fallback, final-page clamping and stable name/ID order. Search/category changes reset page. | Negative/zero/empty/array/decimal/exponent/huge inputs; literal wildcard and injection checks; filter-link tests. |
| Avoid N+1 and unnecessary fields | Public/admin list projections are explicit. Cart/checkout bulk-fetch only their keys instead of all inventory. Name-based legacy links use a bounded fallback. | Off-page/slugless/numeric-slug/inactive-category checkout/cart tests. Availability work still necessarily queries selected equipment reservations. |
| Review indexes and performance | Captured representative query timings, payload sizes and EXPLAIN. Existing category index helps selection; sorting still uses filesort. | Section C. No speculative index or schema DDL executed. |
| All requested mobile widths, branding, desktop preservation | Measured 320/375/390/430/768/1024/1440; fixed mobile date track, modal/control sizing, form/report wrapping and focusable table regions. | Headless layout and real-handler tests; physical phone keyboard remains untested. |
| Product selection usability/calendar/quantity | Kept visible selected date range, native modal, contained image, gallery controls and quantity limit; added reachable mobile close/Add action. | Calendar boundaries/stale response tests and headless modal/date/quantity/focus checks. No Shopee branding copied. |
| Equipment and Services separation | `rental_categories.is_service` remains authoritative. Services request/quote/payment processing stays outside Equipment checkout. | Classification, service HTTP, project completion and quote-version tests. |
| Reconcile every workbook case | Read exactly 39 cases; replaced historical credentials/routes/expectations with current safe reproduction and execution. | Section E and detailed companion report. Historical statuses are not reused as evidence. |
| Date/stock/deposit/checkout correctness | Preserved same-day as one inclusive charged day, deposit per unit and pending/approved reservation behavior. Reviewed ordered item locks and tested competing customers. | Date, stock, report and concurrency suites. The existing Admin/customer maximum booking horizon distinction is documented for policy confirmation. |
| Authorization/ownership/CSRF/SQL/XSS/uploads/errors | Kept server-side role/owner checks and prepared SQL; tested invalid uploads, proof authentication, CSRF rejection and production-style redaction locally. | HTTP, proof, QR ownership, review and focused suites. No security control weakened. |
| Notification and duplicate-submit safety | Existing delivery ledger, configured recipients and replay behavior preserved. Tests use disabled mail/in-memory transports or loopback SMTP. | Mailer, HTTP and SMTP suites; no real email sent. |
| Portability and deployment safety | Current Windows/XAMPP PHP/MariaDB checked; static namespace/path scan and root/mounted URL tests. Deployment settings/storage/keys remain senior-controlled. | Section H; no claims about unvisited production runtime. |
| Preserve Git and HOME PC files | No commit, push, merge, fetch, branch switch or deployment. Explicitly inventory portable edits separately from local setup/keys/uploads. | Section G; read-only current-branch GitHub tip check. |

## C. Backend pagination and performance

### Before and after

[RentalCatalog](../app/Models/RentalCatalog.php) previously selected every active Equipment and Service record using `i.*`, split the complete result in PHP, and then let JavaScript hide cards. Its `find()` also loaded that full catalogue for individual lookups. The homepage and information pages unnecessarily shared this retrieval path. Admin Products already used database pagination; it was not an unfixed frontend-only list.

Public Equipment/Services now share a model page query. It joins categories, applies `i.is_active=1`, `c.is_active=1` and the correct category type, then applies bound search/category predicates before COUNT and retrieval. Search covers name, description and SKU; `%`, `_` and `!` are escaped as literal search text. Rows sort by name then numeric ID, preventing duplicates at ties across adjacent pages. All categories for the selected catalogue remain available in the navigation, including categories whose products are on later pages. This navigation uses an aggregate name/ID/count list rather than loading all product details.

Default size is 12; controls offer 12/24/48 and the backend enforces a maximum of 48. Invalid sizes fall back to 12. Invalid page values fall back to one; valid excessive pages clamp to the final matching page. Zero results produce an honest empty/filter message. Database failure retains the existing safe unavailable catalogue behavior and never invents real stock. GET search and category links preserve the relevant state and reset page; pagination preserves filter state. JavaScript leaves server-selected cards and totals intact.

The homepage retrieves at most six populated Equipment categories and 12 records of each catalogue type; it renders its existing smaller featured selections. Category item counts are SQL aggregates, not counts of the preview. Static Support/How To Rent pages no longer query inventory. Public category cards use database pagination. Admin categories use 25-row SQL search/pagination; the old misleading 200-record message was removed.

Cart display and checkout summary use keyed batch lookups. Inactive equipment/categories remain visible as unavailable for cart removal, but active checkout lookup rejects them. Null/blank slugs, generated `item-N`, numeric database IDs and numeric-looking real slugs were tested. Checkout successfully retrieves equipment well beyond page one. Normal keyed batches use ID/slug predicates rather than casts of every row; bounded name fallback preserves legacy links.

### Measured local results

`tests/rentals-catalogue-pagination.php` passed **76 assertions** and rolled back all fixtures. Product populations were 0, 1, 12, 13, 100 and 1,001; 27 additional populated category fixtures exercised category pages. The representative measured retrieval on 1,000 active products returned:

| Metric | Prior unrestricted product retrieval | Limited representative list query |
| --- | ---: | ---: |
| Returned product rows | 1,000 | 12 |
| Encoded row payload | 442,740 bytes | 1,371 bytes |
| Single local query elapsed time | 6.224 ms | 1.191 ms |

These are warm, single-run local query measurements, **not end-to-end request latency or a production benchmark**. The limited comparison selects four illustrative list fields, whereas the previous comparison selects all item columns; both page limiting and projection contribute to the payload reduction. Actual public cards select their required display/booking fields. The important verified guarantee is bounded product retrieval after server filtering.

EXPLAIN selected the category PRIMARY key and `rental_items_category_index` for the populated fixture category, with `Using where; Using filesort`. The selected category still has roughly 1,001 rows to consider for sorting; LIMIT does not guarantee the server scans only 12 rows, and COUNT must inspect the complete matching set. A separate earlier single-category fixture population chose a full scan, demonstrating why an index recommendation needs representative data.

**Index review, not applied DDL:** compare actual production/staging plans for category/type/active filters and name/ID ordering. Candidates include an item index beginning `(category_id, is_active, name, id)` for category pages and `(is_active, name, id)` for broad active-list ordering, plus category type/active coverage where justified. `%term%` substring search is not made efficient by an ordinary name B-tree. Full-text/search-policy changes require a separate requirement and dataset benchmark. Review redundant indexes and write cost before choosing an index. No index migration is required for correctness of this change.

Primary implementation files: [model](../app/Models/RentalCatalog.php), [public controller](../app/Controllers/Rentals/RentalsController.php), [Admin controller](../app/Controllers/Rentals/RentalAdminController.php), [pagination helper](../app/Services/RentalPagination.php), [cart](../app/Services/RentalCart.php), [checkout](../app/Services/RentalCheckout.php), [public list](../app/Views/rentals/items.php), [category links](../app/Views/rentals/partials/catalogue-categories.php), [JavaScript](../js/rentals.js).

## D. Mobile and desktop UI

The full route/width/interaction matrix and CSS reasoning are in [the mobile audit](rentals-mobile-audit-2026-10-09.md). The final populated headless run passed **301/301 layouts, 26/26 interactions and 4/4 protected proof reads**, covering 43 route scenarios at all seven widths. A focused rerun passed **86/86 layouts, 7/7 interactions and 4/4 proof reads** at 390px and 1440px. Product range highlights/calendar visibility, quantity clamping, contained images, close/Escape focus, mobile navigation and actual keyboard table scrolling were exercised. Two initially reported desktop focus failures were asynchronous test-timing errors: the harness now waits for the native dialog close event. No application Escape behavior was changed for them.

Product selection keeps the established look and uses familiar mobile shopping patterns: clear item details, usable image controls, visible dates, explicit stock/quantity feedback and a reachable Add to Cart action. The modal expands within the dynamic mobile viewport and keeps its close action reachable during scrolling. Desktop modal/image/calendar dimension rules above 780px remain unchanged; measured desktop modal was 880×644 and image area 418×314 with contain-fit.

Seven calendar columns on a 320px screen necessarily have narrower horizontal targets; mobile day rows are 44px high. Physical touch accuracy, iOS/Android browser differences and on-screen keyboard behavior require a real device. Browser checks use local/fallback assets and block external requests. They do not prove production font/network behavior.

The matrix includes populated cart and checkout, customer Equipment receipt/status, Services request/quotation/payment forms and proof displays, Admin Equipment/Services proof review, Equipment editing/gallery/availability calendar, and category/payment-method editors. Temporary fictional accounts and records made these pages reachable without using real customer data. Four protected proof requests returned HTTP200, image/png and the expected 100-byte decrypted fixture image. Browser writes were blocked except local login; HTTP suites independently tested submissions.

The expanded run reproduced an additional real Categories heading overflow at 320px (305px document client width, 324px scroll width). Mobile hero children now permit shrinking and the long heading can wrap; the final seven-width run has no detected page overflow. Three final viewport screenshots were visually inspected: mobile/desktop Equipment dialogs and the mobile Admin Products page. Images retain their aspect ratio, controls and text wrap within the available width, and the desktop two-column dialog remains intact. The 390px dialog measured 359×846 with a 316×237 image area; the 1440px dialog retained its 880×644 and 418×314 measurements. Screenshot capture was corrected to preserve the viewport after an earlier capture setting temporarily resized it; no application layout fix was made for that harness artifact.

## E. Historical QA reconciliation

Read-only workbook inventory: one sheet, exactly 39 case IDs; historical totals 26 Passed, 11 Failed, one Untested and one blank. Current classification is **38 verified, one partially implemented**. This includes rechecking the 26 historical passes and both incomplete cases. Mandatory legacy product slug/type expectations were reconciled with the current optional/generated-slug and category-owned-type architecture, not restored as obsolete validation.

All 39 IDs, previous/current status, safe steps, expected/actual results, root cause, changed files and test evidence are in [the detailed reconciliation](rentals-admin-qa-reconciliation-2026-10-09.md). ORD-005 remains partial because Equipment payment rejection has no dedicated reason field. Payment status rejection, stock release and customer status visibility pass. Services has a separate supported customer/payment message mechanism; it does not justify silently adding a column to Equipment orders.

### Current result for every historical case

The detailed reconciliation linked above contains reproduction steps, expected/actual results, root causes, files and evidence for each row. The compact index below keeps all 39 current results visible in this report.

| Original case | Historical status | Current status |
| --- | --- | --- |
| TC-ADM-AUTH-001 — Admin login | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-002 — Guest Admin access | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-003 — Customer denied Admin | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-004 — Sign out | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-005 — Back to Rentals | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-001 — Dashboard KPIs | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-002 — Recent/upcoming panels | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-003 — Dashboard shortcuts | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-001 — Analytics presets | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-002 — Custom date validation | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-003 — Analytics panels/KPIs | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-001 — Orders columns | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-002 — Order search | FAILED | VERIFIED FIXED |
| TC-ADM-ORD-003 — Payment-status filter | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-004 — Proof review/approval | FAILED | VERIFIED FIXED |
| TC-ADM-ORD-005 — Rejection with reason | FAILED | PARTIALLY IMPLEMENTED |
| TC-ADM-ITM-001 — Products list | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-002 — Product filters | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-003 — Add Product fields | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-004 — Product slug | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-005 — Create Equipment with image | FAILED | VERIFIED FIXED |
| TC-ADM-ITM-006 — Edit Equipment | FAILED | VERIFIED FIXED |
| TC-ADM-ITM-007 — Deactivate Equipment | FAILED | VERIFIED FIXED |
| TC-ADM-CAT-001 — Categories list/add link | PASSED | VERIFIED FIXED |
| TC-ADM-CAT-002 — Add Category fields/slug | PASSED | VERIFIED FIXED |
| TC-ADM-CAT-003 — Category propagation | UNTESTED | VERIFIED FIXED |
| TC-ADM-SLS-001 — Sales filters/summary | PASSED | VERIFIED FIXED |
| TC-ADM-SLS-002 — Exclude refundable deposits | FAILED | VERIFIED FIXED |
| TC-ADM-PAY-REP-001 — Payment Report schema | FAILED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-001 — Payment Methods list | PASSED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-002 — Create payment method/QR | PASSED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-003 — Inactive methods hidden | FAILED | VERIFIED FIXED |
| TC-ADM-CUST-001 — Read-only directory | PASSED | VERIFIED FIXED |
| TC-ADM-CUST-002 — Sensitive fields excluded | PASSED | VERIFIED FIXED |
| TC-ADM-SEC-001 — Admin noindex | PASSED | VERIFIED FIXED |
| TC-ADM-SEC-002 — Admin CSRF | FAILED | VERIFIED FIXED |
| TC-ADM-SEC-003 — Invalid upload rejection | BLANK | VERIFIED FIXED |
| TC-ADM-SEC-004 — Production-safe errors | FAILED | VERIFIED FIXED |
| TC-ADM-RSP-001 — Admin responsiveness | PASSED | VERIFIED FIXED |


## F. Confirmed gap and bug register

| ID / severity | Reproduction and root cause | Narrow fix / validation |
| --- | --- | --- |
| AUD-01 / High — full public product retrieval | Populate 1,001 products; public catalogue/model originally selected all rows, then client filtering hid cards. | SQL filter/count/limit/order and real GET controls; 76 assertions plus browser/public HTTP regressions. |
| AUD-02 / Medium — full inventory for cart lookups | Individual `find()` and cart catalogue loaded every product; coupling lookup to previews would lose off-page items. | Keyed lookup/batches, explicit projection, inactive-removal flag and bounded legacy fallback; off-page/alias/checkout/HTTP tests pass. |
| AUD-03 / Medium — unbounded category cards/Admin list | Many populated categories rendered all cards/records; old Admin text implied a 200-row limit that did not exist. | SQL category pagination/count/search, correct counts and empty messages; 27-category fixture checks pass. |
| AUD-04 / Medium — missing Customer role/search filtering | Customer list always selected all users; no role control/query predicate. | Allowlisted roles, Admin+Superadmin grouping, bound search, explicit COUNT and preserved pages; 60 assertions including Customer denial. |
| AUD-05 / Low — pagination in metadata | Any of six lists with a nonzero page count put navigation markup inside its title; customer Orders also inside canonical href. | Removed only metadata partial calls; footer pagination retained. Real rendered title/canonical checks pass. |
| AUD-06 / Low — duplicate Orders shortcut | Dashboard Recent Orders "View all" and Quick Actions "Review orders" linked to the same destination. | Removed redundant quick action, kept contextual View all and product action; renderer/browser checks pass. |
| AUD-07 / Low — incomplete account overview | Signed-in account showed name/email/actions without existing account type/date information. | Escaped read-only role/member-since/last-login facts; no credential editing; renderer/focus/browser checks pass. |
| AUD-08 / Medium — implicit mobile date grid track | At ≤560px the dates grid became one column but its selector still spanned two, creating an implicit track. | `grid-column:1 / -1` at mobile breakpoint; real 320–430 modal measurements pass. |
| AUD-09 / Medium — mobile modal/control reachability | Close/month/gallery controls were 32px; scrolling could lose the close action and reduce usable dialog space. | Scoped mobile target height, sticky close, dynamic viewport bounds and full-width Add action; browser range/scroll/focus/quantity checks pass. |
| AUD-10 / Medium — intrinsic Admin/filter widths | Report filter minimum columns could exceed medium-width content; long account/report labels had intrinsic minimum widths. | Earlier filter collapse, zero minimum tracks and wrapping; required-width layout checks pass. |
| AUD-11 / Low — keyboard table scroll access | Scroll containers were not keyboard focus targets or named regions. | Focusable named wrappers, visible focus styles; actual ArrowRight scrolling/focus tests pass. |
| AUD-12 / Medium — narrow password form fields | Password recovery/reset plain label/input layout lacked the other form wrappers' width/stacking behavior. | Scoped stacked/full-width field rules; public form renderer and responsive checks. |
| AUD-13 / Medium — Equipment rejection reason | Order review form/controller/schema contain only payment decision/reviewer/time; no dedicated reason persistence. | **Partial / not changed:** rejection itself passes. Senior must approve field, limits, required/optional policy, visibility and additive migration before implementation. |
| AUD-14 / Medium — Categories heading overflow | Open the public Categories page at 320px; the long hero heading imposed an intrinsic minimum width, producing 324px scroll width in a 305px document. | Mobile hero children use `min-width:0` and the heading uses `overflow-wrap:anywhere`; final 301-layout and focused 86-layout runs pass. |

Files for the register: AUD-01 model/public controller/pagination helper/catalogue views and `js/rentals.js`; AUD-02 `RentalCatalog`, `RentalCart`, `RentalCheckout`; AUD-03 model/Admin controller/public and Admin category views; AUD-04 Admin controller/Customers view; AUD-05 Admin Orders/Products/Categories/Customers/Service Requests and customer Orders/Service Requests views; AUD-06 Admin dashboard partial; AUD-07 account view and Rentals CSS; AUD-08/09/12/14 `css/rentals.css`; AUD-10 both Rentals stylesheets; AUD-11 both Rentals JavaScript files and stylesheets. AUD-13 changes no application file. Section G links every exact path; the companion QA/mobile reports provide the corresponding regression evidence and reproduction details.

Exact application/test/document file inventory follows in section G. Previous HOME PC Response 419 and QA stat-cache fixes were preserved and verified; they were not newly invented in this audit. No current Products 500 was reproduced. Local Add Payment Method persistence and Customer HTTP403 behavior passed; these already working features were not rewritten.

## G. Git inventory and HOME PC separation

Current branch `feature/website-ui-refresh`, local HEAD `117abd59257c4267e7a564498d9f7184b63791f3`, matched the GitHub tip of **that same branch** via read-only `git ls-remote` on October 9. This does not claim other branches contain no additional work. No fetch, commit, push, merge or switch occurred; all audit edits remain uncommitted.

The complete modified/untracked path inventory appears below. Review portable application edits and tests separately. **Do not stage everything with `git add .`**: the following HOME PC-only files are untracked and are not ignored, so accidental staging remains possible:

- [HOME initializer](../bin/home-pc-setup.php)
- [HOME setup guide](HOME-PC-SETUP.md)
- [HOME reconstructed fresh schema](home-pc-fresh-schema.sql)

Do not deploy these or run their initializer on a populated/staging/production database. Local `.env`, private account files, local proof key, browser profiles/metrics and uploaded fixture files are ignored/private. Existing production keys, SMTP settings and upload storage must remain controlled by the senior. No ignore-rule or Git-index mutation was performed simply to hide this risk.

### Complete working-tree inventory

Read-only final inventory: **41 paths** (29 modified tracked files and 12 untracked files). All remain uncommitted. `M` means modified; `??` means untracked.

#### Application files for senior code review

| Status | Path |
| --- | --- |
| `M` | [app/Controllers/Rentals/RentalAdminController.php](../app/Controllers/Rentals/RentalAdminController.php) |
| `M` | [app/Controllers/Rentals/RentalsController.php](../app/Controllers/Rentals/RentalsController.php) |
| `M` | [app/Core/Response.php](../app/Core/Response.php) |
| `M` | [app/Models/RentalCatalog.php](../app/Models/RentalCatalog.php) |
| `M` | [app/Services/RentalCart.php](../app/Services/RentalCart.php) |
| `M` | [app/Services/RentalCheckout.php](../app/Services/RentalCheckout.php) |
| `M` | [app/Services/RentalPagination.php](../app/Services/RentalPagination.php) |
| `M` | [app/Views/rentals/account.php](../app/Views/rentals/account.php) |
| `M` | [app/Views/rentals/admin/categories.php](../app/Views/rentals/admin/categories.php) |
| `M` | [app/Views/rentals/admin/customers.php](../app/Views/rentals/admin/customers.php) |
| `M` | [app/Views/rentals/admin/items.php](../app/Views/rentals/admin/items.php) |
| `M` | [app/Views/rentals/admin/orders.php](../app/Views/rentals/admin/orders.php) |
| `M` | [app/Views/rentals/admin/service-requests.php](../app/Views/rentals/admin/service-requests.php) |
| `M` | [app/Views/rentals/categories.php](../app/Views/rentals/categories.php) |
| `M` | [app/Views/rentals/index.php](../app/Views/rentals/index.php) |
| `M` | [app/Views/rentals/items.php](../app/Views/rentals/items.php) |
| `M` | [app/Views/rentals/orders.php](../app/Views/rentals/orders.php) |
| `M` | [app/Views/rentals/partials/admin-dashboard.php](../app/Views/rentals/partials/admin-dashboard.php) |
| `M` | [app/Views/rentals/partials/catalogue-categories.php](../app/Views/rentals/partials/catalogue-categories.php) |
| `M` | [app/Views/rentals/partials/equipment-catalogue.php](../app/Views/rentals/partials/equipment-catalogue.php) |
| `M` | [app/Views/rentals/partials/service-catalogue.php](../app/Views/rentals/partials/service-catalogue.php) |
| `M` | [app/Views/rentals/service-requests.php](../app/Views/rentals/service-requests.php) |
| `M` | [css/rentals-admin.css](../css/rentals-admin.css) |
| `M` | [css/rentals.css](../css/rentals.css) |
| `M` | [js/rentals-admin.js](../js/rentals-admin.js) |
| `M` | [js/rentals.js](../js/rentals.js) |

#### Tests for code review and local execution

| Status | Path |
| --- | --- |
| `M` | [tests/rentals-admin-qa.php](../tests/rentals-admin-qa.php) |
| `M` | [tests/rentals-completion-ui.mjs](../tests/rentals-completion-ui.mjs) |
| `M` | [tests/rentals.php](../tests/rentals.php) |
| `??` | [tests/rentals-browser-audit.mjs](../tests/rentals-browser-audit.mjs) |
| `??` | [tests/rentals-browser-fixtures.php](../tests/rentals-browser-fixtures.php) |
| `??` | [tests/rentals-catalogue-pagination.php](../tests/rentals-catalogue-pagination.php) |
| `??` | [tests/rentals-concurrency-qa.php](../tests/rentals-concurrency-qa.php) |
| `??` | [tests/rentals-senior-view-qa.php](../tests/rentals-senior-view-qa.php) |

#### Audit documentation

| Status | Path |
| --- | --- |
| `??` | [docs/rentals-admin-qa-reconciliation-2026-10-09.md](../docs/rentals-admin-qa-reconciliation-2026-10-09.md) |
| `??` | [docs/rentals-mobile-audit-2026-10-09.md](../docs/rentals-mobile-audit-2026-10-09.md) |
| `??` | [docs/rentals-security-compatibility-2026-10-09.md](../docs/rentals-security-compatibility-2026-10-09.md) |
| `??` | [docs/rentals-senior-system-audit-2026-10-09.md](../docs/rentals-senior-system-audit-2026-10-09.md) |

#### HOME PC only — exclude from commits and deployment

| Status | Path |
| --- | --- |
| `??` | [bin/home-pc-setup.php](../bin/home-pc-setup.php) |
| `??` | [docs/HOME-PC-SETUP.md](../docs/HOME-PC-SETUP.md) |
| `??` | [docs/home-pc-fresh-schema.sql](../docs/home-pc-fresh-schema.sql) |

`app/Core/Response.php` and `tests/rentals-admin-qa.php` include earlier HOME setup compatibility fixes retained during this audit. The new browser fixture helper is a guarded local test tool, not a deployment initializer. Test suites, headless profiles and screenshots do not belong in the public production document root. The inventory contains filenames only, never private file contents.


## H. Production compatibility and safe next steps

**Verified locally:** XAMPP PHP 8.2.12 satisfies the project's `php >=8.2` requirement. Required PDO, PDO MySQL, mbstring, JSON, fileinfo, OpenSSL and cURL extensions are present; local MariaDB is 10.4.32. Current Rentals schema columns/types/unique keys/foreign keys and corporate visitor identity columns pass the read-only HOME diagnostic. Mount-aware URLs, upload/proof routing, ownership, CSRF and local error redaction pass. A static PSR-4 path check found zero casing mismatches across 51 application types.

**Not live verification:** repository comments describe a different deployed PHP/MariaDB environment, but the live version, Linux filesystem, grants, Apache overrides, upload directories, stable key, SMTP provider and real existing data were not accessed. Headless Windows success does not prove these settings.

No schema migration is needed for the pagination/UI/account/list changes when the current application schema is present. The senior's earlier SQL export is a separate older-schema/data source and must not be imported unchanged into this working local schema. It contains historical account/order/proof references and incompatible status/gallery/reset/token details. A database dump does not transfer uploaded image bytes or make encrypted proofs portable. Preserve production upload storage and keys; never copy the HOME credentials/key/files there.

Safe review/deployment sequence for the senior:

1. Review only the portable application/test edits listed below and this report; exclude HOME-only setup files and all private artifacts.
2. Apply reviewed code to an authorized isolated staging copy using the existing deployment process; do not run the HOME initializer.
3. Run read-only schema comparison, actual query plans/dataset benchmark and root/deployed-mount regression tests. Choose any index migration only after that review.
4. Confirm Linux path casing, PHP extensions, Apache rewrite/source denial, external storage permissions, unchanged proof key, HTTPS/session settings and existing notification recipients.
5. Test representative authorized existing images/proofs and SMTP behavior on staging. Physical mobile keyboard/touch and production font behavior need human device review.
6. Resolve Equipment rejection reason and the existing Admin/customer maximum rental horizon policy before implementing any related schema/business changes.

## I. Actual test commands and outcomes

Commands were run from this workspace with `C:\xampp\php\php.exe` and `C:\Program Files\nodejs\node.exe`. Fixture suites were coordinated sequentially; only their owned rows/files were cleaned. No migrations or export imports were part of the test run.

| Run | Actual command pattern | Result |
| --- | --- | --- |
| Local schema/environment preflight | `C:\xampp\php\php.exe bin/home-pc-setup.php --check` | PASS: exact loopback/local database, mail disabled, 15 tables, current columns/types/keys/FKs and visitor identity columns. |
| PHP syntax | PHP `-l` over `rg --files app config routes tests bin -g '*.php'` | Final lint: 192/192 passed. |
| JS syntax/whitespace | Node `--check js/rentals.js`, `--check js/rentals-admin.js`; `git diff --check` | PASS. |
| Standard PHP regressions | `C:\xampp\php\php.exe -d session.save_path=C:/xampp/htdocs/3am-web/storage/sessions tests/<suite>.php` | 19/19 suites passed; names below. |
| Public pagination fixture | `C:\xampp\php\php.exe tests/rentals-catalogue-pagination.php` | PASS: 76 assertions; all fixtures rolled back. |
| Focused metadata/account/Customer filter | `C:\xampp\php\php.exe tests/rentals-senior-view-qa.php --database` | PASS: 60 assertions; 28 fictional account fixtures rolled back. |
| JS real-handler regressions | `C:\Program Files\nodejs\node.exe tests/<suite>.mjs` | 6/6 suites passed; paginated server-control regression also added/executed. |
| XAMPP HTTP | PHP tests `rentals-http-flow`, `rentals-services-http`, `rentals-completion-http` with `http://127.0.0.1:80/3am-web`; `rentals-qr-ownership` with `http://localhost/3am-web` | 4/4 suites passed. |
| Temporary QA-router HTTP | PHP `-S 127.0.0.1:8012 tests/rentals-dev-router.php`, process environment `RENTALS_QA_MOUNT=/3am-web`; `rentals-admin-qa` and `rentals-mailer-http` with `http://127.0.0.1:8012/3am-web` | 2/2 passed; hidden loopback server stopped in finally. Router simulates production-style failures locally, not on another server. |
| SMTP adapter | `C:\xampp\php\php.exe tests/rentals-smtp.php` | PASS; loopback fake SMTP only. |
| Concurrent reservations | `C:\xampp\php\php.exe tests/rentals-concurrency-qa.php http://127.0.0.1:80/3am-web` | PASS: 24 assertions, exactly one Pending order, losing cart preserved, correct one-day totals, no owned proof orphan; latest run 0.149s. Scheduling does not prove both arrived at the item lock at the exact same instant. Fixtures cleaned. |
| Read-only workbook checks | PHP renderer from stdin with synthetic values; guarded `START TRANSACTION READ ONLY` dashboard/analytics checks and rollback | 14 render checks + four database cases passed. No workbook credentials used. |
| Headless browser | Guarded PHP fixture `--prepare`; Node `tests/rentals-browser-audit.mjs --fixtures --capture`; guarded fixture `--cleanup` in finally | PASS: 301/301 layouts, 26/26 interactions, 4/4 protected proof reads; all seven required widths. |
| Focused screenshot/interaction verification | Same guarded fixture sequence; Node `tests/rentals-browser-audit.mjs --fixtures --focused --capture` | PASS: 86/86 layouts, 7/7 interactions, 4/4 proof reads at 390px and 1440px; three final captures visually inspected. |

The 19 PHP suites are: `rentals`, `rentals-urls`, `rentals-integration`, `rentals-completion`, `rentals-category-classification`, `rentals-catalogue-categories`, `rentals-services`, `rentals-services-availability`, `rentals-service-validation`, `rentals-review-regression`, `rentals-system-audit`, `rentals-payment-report`, `rentals-analytics-charts`, `rentals-mailer`, `rentals-project-completion`, `rentals-latest-qa`, `rentals-live-regression`, `rentals-ui-categories`, `rentals-proof-view-qa`. The last name containing "live" is a guarded local transactional regression, not a live-server test. Payment-report failure/schema tests use connection-scoped TEMPORARY shadows; persistent tables were not altered.

The six JS suites are: `rentals-calendar-selection`, `rentals-completion-ui`, `rentals-services-ui`, `rentals-product-types-ui`, `rentals-submit-ui`, `rentals-qa-ui`.

### Failed attempts and their resolution

- The first new healthy-empty model test used an incomplete fake PDO statement. Added bound-parameter and scalar-metadata behavior to the fake; real empty/unavailable assertions remain intact and pass.
- The first focused Customer-denial test rejected any fixture prefix in the whole403 response. The branded header legitimately shows the Customer's own name. Corrected it to assert403/denial/no directory and absence of all26 Admin fixture emails. No authorization code weakened.
- Setting `sys_temp_dir` inside the project caused the existing mailer/proof harnesses to fail the correct external-storage safeguard. Re-ran with external default temporary storage; both pass. No product storage guard was changed.
- Sandbox Chrome launch was blocked; scoped approved local headless execution succeeded. The initial navigation assertion timed out on the expected empty-checkout redirect; corrected the harness to record local redirects. Native dialog focus checks now wait for its queued close event and pass.
- Default sandbox GitHub verification could not resolve the tip. Scoped read-only verification succeeded; current branch and remote tip match. No Git state mutation.
- The expanded browser run reproduced actual Categories overflow at 320px; a narrow mobile hero wrapping fix resolved it. The Admin calendar check initially waited on a deliberately closed details panel; the test now opens the panel before waiting for its real calendar load. CLI browser fixtures use the actual `/3am-web/index.php` script mount when generating URLs; no `.env` URL or application routing was changed.
- An earlier screenshot capture option temporarily resized the viewport, producing misleading clipped captures. Viewport-preserving capture and an explicit modal-content bounds assertion now pass; the final three captures were visually inspected.

### Remaining untested or policy-dependent behavior

Live deployment configuration/data/permissions/SMTP and real-device keyboard/touch are deliberately untested. Valid-token password-reset and deliberately triggered branded error pages were source/handler/HTTP reviewed, but were outside the rendered browser matrix; short landscape heights and extreme long-value fixtures also need visual acceptance. Full-size scanned payment-proof legibility and pixel zoom were not visually tested: protected image decoding/ownership/rendering were tested with small fictional PNGs. Equipment rejection-reason persistence is not implemented pending the dedicated policy/schema decision. Headless checks and the concurrent test are evidence for the tested scenarios, not a promise about all possible device/traffic schedules.

### Fixture cleanup and final local state

All browser/concurrency fixtures were cleaned using their owned IDs and marked files; the browser cleanup confirmed that saved counts for all 15 application tables were restored. Its private state/account files were removed. Existing local carts, cart lines and blackout records were preserved. The final read-only environment check still identifies only `127.0.0.1:3306` / `d3am_rentals_new`, with mail disabled. No schema import, persistent table alteration, `.env` edit, encryption-key replacement or production connection was part of this audit.
