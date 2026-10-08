# 3AM Rentals comprehensive system audit — 2026-10-08

## 1. Executive summary

The tested local Rentals workflows are operational. This audit reproduced and fixed **two application defects (Medium)** and **one group of outdated test expectations (Low)**. No Critical or High defect was reproduced. This is evidence from the tests and code paths reviewed, not a guarantee that every possible defect has been excluded.

Verified areas include inclusive calendar/date logic, date-dependent stock, Cart/Checkout, Equipment/Service separation, category/product CRUD, encrypted payment proofs, ownership and CSRF, Admin review, reports, nested deployment URLs, notification deduplication and simulated SMTP failures. The live server was not contacted or modified during this audit. The owner confirms the previously missing production request table and notification relationship have been repaired; that remains owner-provided production information.

Limitations: browser automation could not initialize because the Windows sandbox helper failed. Desktop/tablet/mobile visual checks at 1440/768/390px remain unverified. Real simultaneous checkout contention, real SMTP delivery, Linux deployment parity and production configuration were not tested. These limits prevent an unconditional production-readiness claim.

## 2. Confirmed bugs

### AUD-001 — Medium — Cart update validates the wrong product

- **Component:** `RentalCartController::update()`; guest and authenticated carts.
- **Reproduction:** create Equipment A with stock 1 and B with stock 10. Add A quantity 1 for a valid same-day range. POST A's Cart line identifier with B's product identifier and quantity 5.
- **Expected:** reject the mismatched product/line; preserve A quantity 1.
- **Actual before fix:** capacity validation used B, but the update changed A's line to quantity 5. Reproduced for guest and customer carts.
- **Root cause:** the request's product identifier was not checked against the product belonging to the owned Cart line.
- **Fix:** resolve the line from the current customer's/guest's Cart, resolve its product, and compare database item IDs before validating/updating. Legitimate slug, name and numeric aliases remain supported by the catalogue resolver.
- **Impact boundary:** an invalid Cart state was demonstrated. Checkout independently rechecks stock and did not thereby become an overbooking bypass. No cross-customer mutation was demonstrated.
- **Test:** `tests/rentals-system-audit.php` failed both tampering checks before the fix and passed afterwards; existing Cart, checkout, stale-session and availability regressions passed.

### AUD-002 — Medium — malformed dates throw before validation

- **Components:** public/Admin month availability, Admin blackout submission, and Admin report filters.
- **Reproduction:** send an embedded null byte in a month such as `2026-\0` followed by `10`, a blackout date such as `2026-10-\0` followed by `01`, or a report `from` date with the same malformed date shape.
- **Expected:** public/Admin availability returns 422; blackout returns its existing validation redirect without saving; report filters follow the existing invalid-filter policy.
- **Actual before fix:** PHP `DateTimeImmutable::createFromFormat()` threw `ValueError` instead of reaching field validation. All four boundaries were reproduced directly against controllers/services using local fixtures.
- **Root cause:** the parsers were called before checking exact input shape. PHP rejects null bytes by exception rather than returning false.
- **Fix:** exact ASCII `YYYY-MM` or `YYYY-MM-DD` guards before parsing. Existing round-trip validity checks and business-date bounds remain. Invalid report filters continue to be ignored, as before; no new report policy was invented.
- **Test:** the focused audit test initially failed the three calendar/blackout checks, then independently failed the added report case; each passed after its corresponding fix. Availability, blackout splitting, calendar bounds and report regression suites were rerun.

### AUD-003 — Low — stale HTTP regression expectations

- **Components:** `tests/rentals-admin-qa.php`, `tests/rentals-http-flow.php`.
- **Reproduction:** run against the local mounted QA server. Admin QA expected a guest Checkout request to end at Cart; workflow QA expected a separate Analytics navigation/page.
- **Expected:** tests enforce current authenticated Checkout and current Dashboard analytics behavior.
- **Actual:** tests failed even though current controllers deliberately authenticate guests before Checkout and redirect legacy analytics URLs to the Dashboard analytics anchor.
- **Root cause:** expectations predated existing application changes.
- **Fix:** expect the guest authentication destination; require the current analytics panel and query-preserving compatibility redirect. No application UI or navigation changed to satisfy these tests.
- **Test:** both corrected suites passed, including their remaining CRUD, upload, report, authorization and checkout assertions.

## 3. Calendar and booking rules

**Same-date rentals are valid under the implemented policy.** `RentalDateRange` permits equal start/end dates. For unit `day`, `RentalCheckout` uses `diff()->days + 1`; the focused test verifies rate 100, quantity 1, same-day subtotal 100, per-unit deposit 25 and total 125. Other rental units retain the existing one-unit-rate calculation. No same-day prohibition was added.

- Public range validation rejects reversed, malformed and past dates. The existing maximum is 400 inclusive days, covering the current-month plus 12-month picker window; 401 days are rejected. Leap years and date round-trip checks are tested.
- A second click on the same available date completes a one-day range. An earlier date replaces the start. Clicking after a completed range starts a new selection. The actual handler regression verifies these behaviors.
- Completed selection keeps the calendar visible. Users can change dates directly. No calendar dimensions, styling, positioning or image/modal dimensions changed.
- `rentalRangeCapacity` evaluates the minimum remaining capacity across the selected dates, including cross-month ranges. A blocked interior day rejects completion. Quantity is limited to remaining capacity and checked again by the backend.
- Pending and Approved Equipment orders reserve stock; Rejected orders do not. Manual blackouts set capacity to zero independently of reservation status. Physical quantity is not decremented as a side effect of selection.
- Availability aggregates overlapping order lines per date rather than summing disjoint rental ranges. Cart lines are also considered per date. Checkout revalidates the combined Cart quantity for each date.
- Admin removing part of a blackout preserves the surrounding blocked intervals and leaves customer reservations intact. Existing tests cover full/start/end/middle/single/month-boundary removals and overlapping blackouts.
- JavaScript uses local calendar components for month keys; timezone regression fixtures cover four timezones. Backend comparison uses configured application dates. Actual production timezone parity remains unverified.
- Request counters discard stale async selection responses. Opening products resets form/calendar state; code review confirms request invalidation/reset on product opening. Rapid selection is tested; complete modal close/reopen interaction is not browser-verified.

## 4. Architecture, security and business-logic review

The custom MVC flow remains routes -> controller -> Rentals services/model -> parameter-bound Database -> escaped views. `index.php` centrally validates CSRF on mutations. Admin controller guards require Admin/Superadmin; customer histories and proof routes enforce ownership. Login regenerates the session identifier; password stamps/reset timestamps invalidate stale authentication. Session cookies use HttpOnly and SameSite settings; production secure-cookie behavior depends on the existing HTTPS configuration.

Classification reads `rental_categories.is_service` through category joins. The review found no query targeting the deleted `rental_items.is_service`. Aliased result keys and category form fields named `is_service` are legitimate. Active category/item checks exclude invalid/missing/inactive relationships from the public catalogue and new Service requests. Admin type/category validation and locked type changes are covered by tests.

Checkout computes rates/deposits/totals on the server, locks the customer's Cart and sorted item rows, rebuilds its summary and checks payment eligibility/availability within the transaction. Proof storage is cleaned up after a failed order transaction. Payment-review transitions use locks or pending-state conditions; Service quotations/payments use request row locks and quote revisions. These mechanisms were reviewed and replay tests pass. This audit did not run competing checkout workers concurrently, so it does not certify contention/deadlock behavior.

Service Requests remain independent from Equipment orders. Submitted customer identity comes from the authenticated account, not posted identity fields. Saved snapshots preserve history after later category changes. Quotation creation, payment proof states, cancellation/completion guards, replay safety and customer visibility passed local tests.

Payment proofs are MIME/size/upload validated, stored encrypted using the existing key/configuration and served through protected routes. Managed product images are validated and referenced through constrained paths. JPEG/PDF proof upload/replacement, malicious image rejection, missing proofs, ownership and CSRF were tested over HTTP. No storage configuration or encryption key changed.

Notifications run after the saved operations, use existing configured owner/CC routing, and deduplicate tested events. Mocked transport and loopback SMTP tests demonstrate failure isolation and uncertain-delivery handling; no real emails were sent. Removed editable mail pages remain inaccessible.

Equipment reports use approved payments for rental revenue, distinguish security deposits from revenue, and avoid multiplying header totals across detail rows. Existing grouping/date/status/null/empty fixtures pass. Service quotation totals are kept separate; no new combined revenue policy was invented. Equipment has a payment-status model, not a newly introduced return/completion lifecycle. Pending expiry, returns management and a combined Equipment/Service revenue definition would require explicit business decisions if desired.

`RentalAdminReservation` has no current route/controller/test call site (repository search). Its legacy direct-item lookup was noted for future review if reintroduced; this audit did not enable it, change it or classify its unreachable behavior as a confirmed website exploit.

## 5. Changed files and scope

Changes made **during this audit**:

| File | Purpose |
|---|---|
| `app/Controllers/Rentals/RentalCartController.php` | Bind updates to actual owned Cart-line product; guard malformed month input. |
| `app/Controllers/Rentals/RentalAdminController.php` | Guard Admin month and blackout date input. |
| `app/Services/RentalAdminInsights.php` | Guard report filter date parsing. |
| `tests/rentals-system-audit.php` | Transaction-isolated reproductions and one-day price verification. |
| `tests/rentals-calendar-selection.mjs` | Actual modal click-handler regression for range changes, same day, blocked dates and async selection. |
| `tests/rentals-admin-qa.php` | Correct stale guest Checkout expectation. |
| `tests/rentals-http-flow.php` | Correct stale analytics assertions; verify filter-preserving redirect. |
| This report | Findings, evidence, limitations and review assessment. |

Pre-existing uncommitted work from the preceding Service Request audit was preserved: `app/Services/RentalServiceRequests.php`, `bin/rentals-services-check.php`, `tests/rentals-service-validation.php`, `tests/rentals-services-availability.php`, `tests/rentals-completion-http.php`, and `docs/rentals-service-workflow-audit.md`. Those are not newly attributed to this audit. No CSS, product-card size, modal/calendar size, branding, `.env`, mail recipients or database architecture was changed.

## 6. Tests executed and exact commands

Environment: Windows, local `d3am_rentals_new`, MariaDB 10.4.32, PHP 8.4.25. Use PHP 8.2+ as declared by the project; the older default PHP command is unsuitable. Tests use local isolated fixtures with transaction rollback or fixture-only cleanup; existing business records are not intentionally modified. The Payment Report missing-column test uses connection-local temporary tables, not persistent schema changes.

All named PHP tests below passed on their final execution:

```powershell
$tests=@('rentals.php','rentals-integration.php','rentals-latest-qa.php','rentals-analytics-charts.php','rentals-payment-report.php','rentals-catalogue-categories.php','rentals-ui-categories.php','rentals-category-classification.php','rentals-services.php','rentals-project-completion.php','rentals-review-regression.php','rentals-completion.php','rentals-services-availability.php','rentals-service-validation.php','rentals-urls.php','rentals-proof-view-qa.php','rentals-system-audit.php','rentals-live-regression.php','rentals-mailer.php','rentals-smtp.php')
foreach($test in $tests) { & C:\php84\php.exe "tests/$test" }
& C:\php84\php.exe bin/rentals.php --check
& C:\php84\php.exe bin/rentals.php --payment-report-check
& C:\php84\php.exe bin/rentals-services-check.php
```

The command list consolidates individual invocations and loops executed during the audit. Affected tests were rerun after their corresponding fixes; unchanged suites were not needlessly repeated.

Mounted local HTTP server (stopped after testing):

```powershell
$env:RENTALS_QA_MOUNT='/web-dev/3am-web'
& C:\php84\php.exe -S 127.0.0.1:8767 tests/rentals-dev-router.php
```

In another terminal, these six suites passed:

```powershell
$base='http://127.0.0.1:8767/web-dev/3am-web'
foreach($test in @('rentals-admin-qa.php','rentals-http-flow.php','rentals-services-http.php','rentals-completion-http.php','rentals-qr-ownership.php','rentals-mailer-http.php')) {
  & C:\php84\php.exe "tests/$test" $base
}
```

All six JavaScript suites passed under Node 24.21.0 via the installed VS Code runtime:

```powershell
$oldElectron=$env:ELECTRON_RUN_AS_NODE
try {
  $env:ELECTRON_RUN_AS_NODE='1'
  foreach($test in (Get-ChildItem tests -Filter '*.mjs')) {
    & 'C:\Users\PC-3\AppData\Local\Programs\Microsoft VS Code\Code.exe' $test.FullName | Out-Host
  }
} finally {
  if($null -eq $oldElectron) { Remove-Item Env:ELECTRON_RUN_AS_NODE -ErrorAction SilentlyContinue }
  else { $env:ELECTRON_RUN_AS_NODE=$oldElectron }
}
```

Includes `rentals-calendar-selection`, `rentals-completion-ui`, `rentals-product-types-ui`, `rentals-qa-ui`, `rentals-services-ui`, `rentals-submit-ui`. These exercise JavaScript with fixtures, not a real browser layout engine.

PHP syntax validation passed across 187 files in `app`, `routes`, `config`, `tests`, and `bin`; changed files were also checked. `git diff --check` passed. Exact syntax command:

```powershell
Get-ChildItem app,routes,config,tests,bin -Recurse -Filter '*.php' | ForEach-Object { & C:\php84\php.exe -l $_.FullName }
git diff --check
```

Initial failures: Admin QA was first invoked without its required URL (harness invocation error), then exposed its stale guest expectation; workflow HTTP exposed its stale analytics expectation. The new audit test reproduced the application failures described above. Corrected invocations/expectations/fixes passed. Payment Report tests intentionally reproduced SQLSTATE 42S22 for missing review/date columns in temporary tables, then passed; those messages do not indicate a broken configured database.

Read-only schema diagnostics found no missing required columns, unique keys or relationships. Final counts: users 3; categories 2; items 8; blackouts 7; carts 2; cart items 0; payment methods 3; order headers 30; order details 34; notification deliveries 24; Service Requests 2; password resets 0. Core integration checks passed for status mapping, secure status tokens and order-detail/header relationships. Service item 10 does not exist locally, which is a local/live data difference rather than a local missing-schema failure.

## 7. Production risks and remaining recommendations

1. **Unverified visual checks:** run actual browser tests at 1440/768/390px, including keyboard focus, modal reopen/product switching, long content and mobile overflow. Automation failed before opening a browser: `windows sandbox failed: helper_unknown_error: setup refresh had errors`.
2. **Unverified concurrency:** test competing last-unit checkouts and Cart changes from separate sessions/workers against an isolated database. Locks and rollback paths were reviewed and sequential replay tests pass; concurrency was not simulated by the single-worker QA server.
3. **Production compatibility:** confirm PHP >=8.2, required extensions, case-sensitive deployment paths, configured timezone, nested base URL, HTTPS cookie settings, database account privileges and external storage permissions. Production was not inspected during this audit.
4. **Mail/storage continuity:** preserve the existing encryption key and storage root; validate configured SMTP delivery separately with authorized accounts. Local mail tests use mocks/loopback only.
5. **Business decisions:** no ambiguity was found requiring a change to same-day rentals. Automatic pending-reservation expiry, Equipment return/completion lifecycle and combining Service income into Equipment reports remain policy questions, not demonstrated defects to fix silently.
6. **No migration required:** current local schema diagnostics pass. No persistent schema migration was proposed or executed. Do not rerun additive deployment SQL merely because earlier production structures were missing; the owner reports that repair is complete.

## 8. Deployment assessment

The changes are small local candidates for code review, with focused reproductions and broad local regression coverage. They enforce existing behavior rather than redesigning workflows. Review the **new audit changes separately from the pre-existing Service Request changes** before selecting deployment files.

No deployment approval is implied. Browser responsiveness and multi-worker concurrency remain additional checks before a broader production-readiness sign-off. Nothing was deployed, committed, pushed, merged or switched to another branch; production and `.env` were untouched.
