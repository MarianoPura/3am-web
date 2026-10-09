# Rentals security and deployment compatibility review — 2026-10-09

## Scope and evidence limits

This review inspects the HOME PC checkout only. It does not contact a live server, read a production database, import SQL, change environment configuration, or stage/commit/push Git changes. Secret environment values and private account-file contents are excluded from the review and report. Source-code inspection establishes that a control exists; execution evidence below comes from the coordinated local audit runner and is also recorded in the senior system audit. Temporary fictional fixtures were removed after the coordinated tests.

The application uses a custom PHP MVC front controller, prepared PDO queries, native PHP templates, external upload storage, and separate Equipment checkout and Services quotation workflows. Equipment/Services classification is `rental_categories.is_service`, joined through `rental_items.category_id`.

## Senior feedback: baseline findings

| Requirement | Baseline status | Verified implementation or gap | Smallest appropriate action |
|---|---|---|---|
| Remove redundant dashboard order action | CONFIRMED BUG | [Dashboard partial](../app/Views/rentals/partials/admin-dashboard.php) includes both “View all” and “Review orders”, each linking to the same Orders list without different filters. | Retain the Recent Orders “View all” link; remove the duplicate quick action while retaining Manage Products. |
| Add Payment Method | PARTIALLY IMPLEMENTED pending runtime verification | [Admin controller](../app/Controllers/Rentals/RentalAdminController.php) has create/edit/toggle routes, validations, SQL writes, upload cleanup and notices. Active manual methods require account name and number. [Checkout](../app/Services/RentalCheckout.php) excludes inactive methods and unavailable gateways. | Reuse existing implementation; execute the local HTTP suites before claiming that the historical defect is fixed. |
| Admin/Customer directory filter | NOT IMPLEMENTED | Baseline `customers()` only paginates all users; [Customers view](../app/Views/rentals/admin/customers.php) has no role or search controls. The SQL selects only ID/name/email/role/last-login, excluding password hashes. | Add validated SQL role/search filters before pagination, preserving queries on page links. Include Superadmin in the Admin group because existing authorization accepts both roles. |
| Products header causes 500 | UNABLE TO VERIFY by static inspection alone | Registered route, controller action, category join, server authorization, pagination and empty state exist. | Test the existing route locally, then regression-test the pagination changes. Do not recreate a historical fix without reproducing failure. |
| Customer entering Admin URL | PARTIALLY IMPLEMENTED pending runtime verification | `RentalAdminController::deny()` returns a branded 403 for Customer and permits only Admin/Superadmin; every read/write action inspected checks it. Services has a separate equivalent server authorization gate. | Keep server checks. Test with local Customer, Admin and Superadmin fixtures; inspect response/body for leakage. |
| Account details overview | PARTIALLY IMPLEMENTED | [Account view](../app/Views/rentals/account.php) already displays name/email and Cart, Orders, Service Requests, Settings, Sign Out actions. Role and known account timestamps are absent although `RentalAccount::current()` provides them. | Add escaped read-only account facts from the existing data, preserving current actions and supported Settings flow. |
| Equipment rejection with a reason | REQUIRES BUSINESS CLARIFICATION | [Order review](../app/Views/rentals/admin/orders-view.php) submits only decision. `reviewProof()` persists status/reviewer/time, with no dedicated rejection-reason field or customer display. Services separately supports `payment_message`. | Clarify whether Equipment requires a customer-visible reason, whether mandatory, length/audit/notification rules and resubmission handling. Then review a dedicated additive schema change; never overwrite customer `notes`. |

### Confirmed metadata defect

Six paginated templates invoke pagination inside their `title` sections: Admin Products, Orders, Customers and Service Requests, plus customer Orders and Service Requests. Customer Orders additionally includes pagination inside its `canonical` section. The layouts escape the resulting sections, so this is text contamination rather than executable markup: browser titles contain serialized controls and the canonical URL becomes invalid. Footer pagination is already present and should remain; remove only the metadata partial invocations and test titles/canonical URLs with a nonempty multi-page dataset.

### Focused fixes completed during this audit

- The Customers controller now adds validated role/search predicates to SQL before count/limit. The Admin selection includes Admin and Superadmin under existing authorization rules; Customer selection includes only Customer. Unsupported roles fall back to all, search values use prepared parameters and invalid page shapes use the bounded page parser. Returned account columns still exclude credentials/security tokens.
- The Customers GET form uses existing styled search/filter controls and submits without a page field, resetting a changed filter to page one. Pagination retains search/role query values.
- Account overview now displays escaped email, account type, member-since timestamp and last-sign-in timestamp from the existing account data. Existing name, Settings and other actions remain; no new account editing capability was introduced.
- The duplicate Dashboard Review Orders quick action was removed. Recent Orders View All and the distinct Manage Products quick action remain.
- Pagination was removed from all six title sections and the Orders canonical section; each body still has its existing single pagination control.
- [Focused regression](../tests/rentals-senior-view-qa.php) renders the actual templates with a nonempty pagination model, checks clean metadata/single controls/filter URLs/dashboard destinations/account escaping and checks that fixture-only security canaries are absent. Its optional database mode contains exact HOME PC preflight and rollback-only role/search/count/page/authorization tests, for the root audit to execute sequentially with other fixture suites.

These changes add no database columns, tables, sample production accounts or business-rule changes. Equipment rejection reasons remain pending clarification and a separately reviewed schema proposal.

## Existing critical safeguards inspected

| Area | Static evidence | Local execution evidence or suite mapping |
|---|---|---|
| Authentication | [RentalAccount](../app/Services/RentalAccount.php) hashes passwords, verifies login, regenerates session ID, removes the hash from the returned account, validates password/reset stamps and invalidates stale sessions. [Auth throttle](../app/Services/RentalAuthThrottle.php) is called before login/registration. | `tests/rentals-review-regression.php`, `tests/rentals-system-audit.php`, HTTP QA suites. |
| Session isolation | [Front controller](../index.php) gives the application a separate session name/store, mount-scoped cookie, HttpOnly, SameSite=Lax, and configured Secure flag. | Mounted URL tests, HTTP login/logout and HTTPS server configuration review by the senior. |
| Authorization and ownership | Admin gates run before administrative queries/writes. Customer Equipment histories/proof queries constrain user ID. Service detail/payment/proof gates constrain owner, with proof access additionally allowed for Admin/Superadmin. | `tests/rentals-services.php`, `tests/rentals-services-http.php`, `tests/rentals-qr-ownership.php`, Admin HTTP QA. |
| CSRF | [Front controller](../index.php) checks every mutating request before dispatch, including AJAX with HTTP 419. [CSRF service](../app/Core/Csrf.php) uses session token and `hash_equals`. Baseline [Response change](../app/Core/Response.php) sets an explicit 419 status line for Apache. | HTTP suites must exercise missing/incorrect tokens against write routes and assert no record change. Controller-only tests bypass this global check. |
| SQL | [Database](../app/Core/Database.php) disables emulated prepares. Reviewed user/search/filter values use parameters. Interpolated table names in toggle/schema code originate in fixed application mappings. | Pagination SQL tests, invalid filter/page cases and local EXPLAIN. |
| Output escaping | Reviewed account, customer, order and Services views use `e`/`e_attr`; messages and names are escaped. | Services message escaping tests and view regressions with special characters. This is a reviewed subset, not a mathematical guarantee over all templates. |
| Uploads | [Managed images](../app/Services/RentalManagedImage.php) require `is_uploaded_file`, actual MIME allowlist, image readability, server-aware size limit, random names and owned path patterns. [Proof service](../app/Services/RentalPaymentProof.php) applies MIME/size checks and encrypts stored proofs with AES-256-GCM. | Admin upload QA, proof HTTP suites, invalid text/PHP-named content and oversize rejection. No real hostile files are sent to production. |
| File access | [Storage](../app/Services/RentalStorage.php) confines managed references, requires an absolute root outside the project, writes deny guards, and resolves portable `micro/...` references. Public images are delivered through app routes; payment proofs require protected routes. | Read-only XAMPP checks for forbidden source/storage URLs and valid managed image routes. Server permissions and Apache override behavior must be verified separately on deployment. |
| Production errors | Bootstrap disables display outside explicit development/debug mode. Rentals database exceptions become generic messages and safe logs; the front controller renders a branded generic 500 outside debug. | Existing Admin QA production-mode/session-failure endpoints. Passing locally does not establish production environment settings. |
| Rental days/stock | [Date range](../app/Services/RentalDateRange.php) parses exact dates, rejects reversed/extreme/past bookings; current policy allows same-day and daily amounts use inclusive days. [Checkout](../app/Services/RentalCheckout.php) locks Cart/items, reloads prices, rechecks availability and sums overlapping Cart lines before writes. Pending/Approved orders reserve stock; rejected orders release it. | Date/calendar, category-classification, integration/system/review suites. Preserve same-day behavior and the 400-inclusive-day public cap. |
| Concurrent reservations/replay | Checkout locks item IDs in stable numeric order. Admin-created reservations and rejected-order proof resubmission also recheck stock under locks. Services uses a unique submission key and locked rows; repeat pending/approved proof submissions do not save duplicate files. | [Concurrent HTTP QA](../tests/rentals-concurrency-qa.php) passed **24/24 assertions**: two independent Customer sessions submitted the same single-stock item and dates through `curl_multi`; exactly one Pending order was created, the winning Cart cleared and the losing Cart retained. Correct inclusive-day totals and no owned proof orphan were verified. Scheduling does not guarantee both requests arrived at the item lock at the same instant or cover every race schedule. |
| Services state/quotation | [Service store](../app/Services/RentalServiceRequests.php) requires Admin review, an approved request to quote, owner payment, active manual methods, matching quote version before upload, pending payment to review, and date/payment prerequisites for completion. Services does not create Equipment orders. | `tests/rentals-services.php`, `tests/rentals-completion.php`, `tests/rentals-review-regression.php`, Services HTTP/UI tests. |
| Reports | [Admin Insights](../app/Services/RentalAdminInsights.php) sums approved rental `subtotal` for sales and excludes refundable deposit. Payment Reports use left joins for historical null method/reviewer data. [Report schema inspector](../app/Services/RentalPaymentReportSchema.php) proposes only compatible additions and does not execute them. | `tests/rentals-payment-report.php`, `tests/rentals-analytics-charts.php`, completion/system tests. The payment-report suite uses session-local temporary copies for deliberate missing-column DDL. |
| Notifications | Services and Equipment notifications run after successful transactional writes; existing recipients/configuration remain untouched. | Mailer ledger/failure tests with loopback transports and disabled outgoing real mail. |

## HOME PC Git separation

Initial `git status --short` recorded two existing tracked changes and three untracked HOME PC files:

- [Response.php](../app/Core/Response.php): portable custom HTTP 419 Apache response fix, predating this audit.
- [Admin QA test](../tests/rentals-admin-qa.php): portable `clearstatcache(true)` after HTTP upload replacement, predating this audit.
- [HOME PC initializer](../bin/home-pc-setup.php): LOCAL ONLY. It pins local mode, disabled mail, loopback host/port and `d3am_rentals_new`; its initialize/seed options must never be used as a production migration.
- [HOME PC guide](./HOME-PC-SETUP.md): LOCAL ONLY instructions, account-file location and demo setup.
- [HOME PC schema](./home-pc-fresh-schema.sql): LOCAL ONLY setup artifact; not a production migration or instruction to replace existing tables.

`git check-ignore` confirms the root environment file and private `storage/tmp/home-pc-accounts.txt` are ignored by existing rules. The three HOME PC files above are **not ignored** and can be included by a blanket staging command. Git also ignores runtime logs, sessions, temporary files, local upload copies, and `storage/rentals/payment-key.php`.

No Git index, branch, config or exclude rules were changed by this review. Safe future review should explicitly choose application/test/report paths and inspect the staged file list rather than stage every file. The HOME PC initializer, SQL and private setup guide belong outside a production release. Keep the actual environment, demo login file, local proof key and uploads private; their ignore rules do not substitute for checking a release artifact assembled outside Git.

## Production compatibility: static checks and senior verification steps

The live system was **not verified**. Documented production versions in repository comments are historical declarations, not measurements of the current server.

| Topic | Source conclusion | Safe verification for the senior when deployment is separately authorized |
|---|---|---|
| PHP/dependencies | [Composer manifest](../composer.json) requires PHP 8.2+, PDO MySQL, mbstring, JSON, fileinfo, OpenSSL and cURL. Composer platform is pinned to 8.2.31; developer PHP may differ. | Confirm actual PHP version/modules and shipped vendor packages from the candidate release. Run syntax and dependency checks using that interpreter. No package upgrade is needed just to implement pagination. |
| Database portability | Queries use common MariaDB/MySQL features; strict SQL mode and utf8mb4 are configured. Category classification and current Service/payment/password reset/gallery structures remain required. | Run the read-only schema diagnostic against an authorized staging clone; compare changes before deployment. Do not run the HOME PC initializer, recreate tables or replace customer records. |
| Nested paths | [App config](../config/app.php), [Request](../app/Core/Request.php) and URL helpers support mounted paths. New links must use `url`/`absolute_url`. | Test both a root mount and the actual deployment mount, including pagination queries, login redirects, managed images, proof routes and canonical URLs. |
| Apache routing/source protection | Root [.htaccess](../.htaccess) rewrites missing files through the front controller and denies source/dotfiles. External uploads have deny guards. The PHP built-in server needs the existing development router for dotted image paths. | Confirm rewrite modules/AllowOverride and denied `.env`, source, docs, storage and direct upload URLs using status-only checks; inspect no secret response bodies. Confirm an authorized public managed image returns the expected MIME. |
| Linux case sensitivity | A read-only source/path comparison checked 51 declared App classes/interfaces/traits against namespace/type-derived paths and found no case mismatches. This does not verify every dynamically assembled path. Windows success alone is insufficient to prove the entire release. | Run the same tests on an isolated Linux staging copy. Review case-only renames and filename references before producing a release. |
| Storage permissions | Default upload storage is the sibling `micro` directory; it survives code replacement. Existing reference strings do not encode the HOME PC absolute path. | Verify PHP worker write/read permissions and protected direct access. Preserve existing uploaded assets; a SQL export does not transfer image/proof bytes. |
| Proof encryption | Production proof keys derive from the stable application key; local/testing proofs use a private local key file. Changing mode/key or transferring only SQL makes old encrypted proofs unreadable. | Keep the existing production key/storage intact. Do not publish locally encrypted fixture files or the HOME PC key. Verify representative existing proofs through authorized protected endpoints without exposing contents. |
| Cookies/HTTPS | HTTP local mode correctly disables Secure cookies; deployment must use the environment's HTTPS configuration and correct mount. | Confirm HTTPS cookie Secure/HttpOnly/SameSite and session login/logout at the real mount; HOME PC settings must not be copied. |
| Email | Local tests can disable mail or use loopback transports. Fixed existing notification recipients are part of approved application configuration. | Confirm deployment SMTP settings/ledger permissions with the senior. Do not send mail or alter recipients during this local audit. |
| Error handling/logs | Generic Rentals errors prevent client SQL/trace output outside debug; protected local logs record failures. | Confirm production mode/debug disabled, writable protected logs and generic branded response for an authorized controlled staging error. |
| Pagination/indexes | Products require SQL filters/count/limit/order rather than all-record frontend slicing. Additional indexes must follow a measured plan rather than speculative schema changes. | Review local EXPLAIN and dataset timings, then compare indexes on staging. No production DDL is authorized by this review. |

## Policies and limitations requiring an explicit decision

1. Equipment rejection reasons (workbook ORD005) need a dedicated business/schema decision as described above; Services already has a separate supported reason field.
2. Admin-created reservations currently cap the date difference at 365 (366 inclusive days), while customer ranges use a 400-inclusive-day safety bound to cover the calendar window. This is an existing distinction, not silently harmonized by this audit; confirm whether administrative booking horizons intentionally differ.
3. Current code accepts same-day Equipment as one charged day. It is preserved; this audit does not invent a new minimum booking period.
4. Automated tests do not prove payment amounts received externally. Gateway integration remains deliberately unavailable in customer checkout until an approved integration exists.
5. Filled-page headless checks and a simultaneous local booking test now have execution evidence below. Physical-device/on-screen-keyboard behavior, proof pixel clarity at zoom, production permissions/version/configuration and real outbound delivery remain unverified. The single concurrent scenario does not exhaust race schedules. Historical workbook results are not current execution evidence.

## Test execution record for this sub-review

Initial investigation was static/read-only and ran no database fixture suite. After the focused code changes:

```powershell
C:\xampp\php\php.exe tests/rentals-senior-view-qa.php
```

Result: **PASS, 25 assertions**, using actual rendered templates with no database fixtures/connections needed. PHP syntax checks passed on the modified controller, eight views and the new focused test (10 PHP files). A read-only PSR-4 case scan found **0 mismatches across 51 types**.

The optional command below was executed sequentially by the coordinated audit runner:

```powershell
C:\xampp\php\php.exe tests/rentals-senior-view-qa.php --database
```

Result: **PASS, 60 assertions**, as reported by the coordinated runner. An initial overly broad test sentinel failed because the branded 403 header correctly displays the denied Customer's own name, which shared the fixture prefix. The test now checks the 403/branded denial, absence of the directory heading and absence of all 26 administrative fixture emails. No authorization control was weakened.

It requires local mode, database host exactly `127.0.0.1`, port `3306`, database exactly `d3am_rentals_new`, and disabled email before obtaining the connection. It checks actual `DATABASE()` identity and wraps 28 unique fictional account fixtures in a transaction, then rolls back in `finally`. Rows are not retained; normal MariaDB auto-increment gaps can remain after rolled-back inserts. Do not run fixture suites concurrently.

The concurrent HTTP test was executed sequentially by the coordinated runner against the guarded local target:

```powershell
C:\xampp\php\php.exe tests/rentals-concurrency-qa.php http://127.0.0.1:80/3am-web
```

Result: **PASS, 24/24 assertions**, latest run **0.149 seconds**. Two independent fictional Customers requested one stock unit for the same future dates. Exactly one Pending order and detail were created; the winning Cart cleared and the losing Cart remained. The same-day total was **125**, consisting of **100 rental subtotal and 25 refundable deposit**. The owned proof was readable and no owned orphan proof remained. All owned accounts, catalog/payment records, Carts/order rows and proof files were removed after the run. Timing and concurrency do not establish simultaneous lock arrival or exhaustive race coverage.

It refuses other URLs/configurations and never follows HTTP redirects, preventing an automatic jump to an external host. Cleanup identifies rows by owned IDs and proof files by fixture order ownership or a uniquely marked decrypted payload; it never deletes every file returned by a directory diff.

### Filled-page real-browser evidence

[Browser fixture helper](../tests/rentals-browser-fixtures.php) and [headless Chrome audit](../tests/rentals-browser-audit.mjs) were executed sequentially by the coordinated runner. The helper checks local mode, host exactly `127.0.0.1`, port `3306`, database exactly `d3am_rentals_new`, disabled email and actual connected database identity. It uses uniquely marked fictional rows, existing local encryption/key/storage configuration and managed gallery copies. Private account credentials and receipt/status URLs were written only to ignored temporary files.

The browser rendered populated Checkout, customer Services quotation/payment and pending-proof pages, Equipment/Admin payment review, the Equipment editor and its calendar. It checked all **seven widths: 320, 375, 390, 430, 768, 1024 and 1440 pixels**. The expanded run passed **301 layout checks, 26 interaction checks and four protected proof reads**; a focused repeat passed **86 layout checks, seven interactions and four protected proof reads**. Each proof response returned **HTTP 200 with PNG MIME and readable bytes**. Browser requests were restricted to approved loopback GET/HEAD and local account-login POST; it did not submit Checkout, payment/review, catalog editing or other record-write forms.

Helper cleanup removed all owned browser rows, image/proof files and private account/state files. The original counts across **15 local tables were restored**, with no retained browser fixtures. These measurements establish filled-page headless behavior and protected proof delivery; they do not establish physical-device keyboard behavior or proof pixel clarity at zoom.

### Final regression and syntax checks

After the final Checkout preflight/transaction changes used keyed Cart item batches, the coordinated runner repeated catalogue pagination (**76 assertions**), review regression, the suite named live regression **with exact local database guards**, the Equipment HTTP flow and concurrency (**24 assertions**); all passed. The suite name is not evidence of live-server access: the LIVE system remained untouched and untested. Final PHP syntax validation passed **192/192 files**.

Other runtime results and exact commands are recorded by the coordinated senior system audit. Passing local tests does not verify live deployment configuration, externally received payment amounts or physical-device behavior.
