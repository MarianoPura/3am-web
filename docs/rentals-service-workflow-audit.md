# Service Request workflow audit — 2026-10-08

## Confirmed findings

Customer `show()` and Admin `adminList()` in `RentalServiceRequestController` both return the reported HTTP 503 view when `RentalServiceRequests::ready()` returns false. The check counts `information_schema.tables` entries for `rental_service_requests` in `DATABASE()`. It does not inspect request rows: an empty, accessible table returns HTTP 200. A missing table, wrong selected database, or table not visible to the database account can produce this branch. A SQL exception propagates rather than becoming an empty result.

This identifies the exact branch in this checkout, not the physical production database cause. Production code parity, schema, grants and logs were not available. The public URL could not be retrieved by the web tool; no authenticated production actions were attempted. Do not claim that live SQL errors or a missing production table were independently confirmed.

Read-only local inspection: database `d3am_rentals_new`, MariaDB 10.4.32, PHP 8.4.25. The Service Request schema, required columns, unique keys, parent foreign keys and payment/notification dependencies passed `RentalSchema::inspect()`. Local Service item 10 does not exist. Local fixture Services successfully open and submit through the same routes. Before/after counts: 3 users, 2 categories, 8 items, 3 payment methods, 2 requests, 24 notification deliveries. No existing customer records were changed.

Service eligibility already uses an inner category join with `c.is_service=1`, active item/category checks and available/inquire status. Missing or invalid category relationships are excluded. Historical requests use saved snapshots and remain accessible after a category becomes inactive. No dependency on `rental_items.is_service` was found in this workflow.

## Changes in this audit

- `app/Services/RentalServiceRequests.php`: reject malformed date shapes before PHP's parser, preventing an embedded null byte from throwing `ValueError`; log a credential-free missing/invisible-table diagnostic for the existing 503 branch. No runtime migration or fallback success/empty data.
- `bin/rentals-services-check.php`: explicit read-only, CLI-only schema/relationship/runtime check. Reports schema issues, record counts and item 10's category flags; no credentials, customer fields or raw driver messages. Driver SQLSTATE/error number are available on failure.
- `tests/rentals-service-validation.php`: malformed/null-byte dates and valid-date regression checks without database writes.
- `tests/rentals-services-availability.php`: also covers the customer request form's missing-table 503, alongside Admin empty-table 200 and SQL exception propagation.
- `tests/rentals-completion-http.php`: correct a stale assertion which expected the Equipment fixture's category in the Service sidebar; now requires the Service category and excludes the Equipment category.
- This report: evidence, commands, production boundaries and deployment checklist.

No UI dimensions, Equipment logic, authentication, mail recipients, `.env`, database schema or business records were changed. No commit, branch change, deployment or production migration was performed.

## Workflow verification

Passed locally with disposable named fixtures, transaction rollback where supported, fixture-only cleanup and mail disabled/mocked:

- Catalogue Service/Equipment separation, Service categories/search behavior, request CTA and authenticated form.
- Required fields, malformed/past/reversed dates, entered-value retention, inactive categories, Equipment rejection and invalid Services.
- Transactional submission, random unique reference, owner/item association, initial pending status, repeated POST idempotency and notification deduplication.
- Owner-only history/details/proofs, guest authentication, Admin/Superadmin guards and CSRF enforcement over HTTP.
- Admin queue/details, status filters, pagination, approval/rejection and conflicting/replayed review protection.
- Separate Service quotation/payment process, quote revision checks, encrypted JPEG/PDF multipart uploads, rejection/replacement, proof authorization, duplicate payment/review handling and completion/cancellation transitions.
- Configured owner/CC routing with mocked transport, SMTP failure safety, post-commit notification behavior and no repeated sends for the tested events.
- Equipment dated Cart/Checkout totals, stock behavior, mandatory proof, stale sessions and independent customer sessions.
- Production-style nested mount `/web-dev/3am-web` including generated links and redirects.

Actual production PHP/extensions, DB version/account/grants, storage permissions/encryption key continuity, SMTP delivery, Linux deployment filenames and deployed artifact parity remain unverified. Existing PHP files/namespaces/routes resolve locally; that does not establish Linux server parity. Project requirement is PHP >=8.2; the local default PHP 8.0 must not be used. No new business rule was needed.

## Exact tests and results

All commands below passed. Run from the repository root using PHP 8.2+; this audit used `C:\php84\php.exe`.

```powershell
& C:\php84\php.exe bin/rentals-services-check.php
& C:\php84\php.exe tests/rentals-service-validation.php
& C:\php84\php.exe tests/rentals-services-availability.php
& C:\php84\php.exe tests/rentals-services.php
& C:\php84\php.exe tests/rentals-project-completion.php
& C:\php84\php.exe tests/rentals-review-regression.php
& C:\php84\php.exe tests/rentals-urls.php
& C:\php84\php.exe tests/rentals-completion.php
& C:\php84\php.exe tests/rentals-category-classification.php
& C:\php84\php.exe tests/rentals-proof-view-qa.php
```

In a separate loopback-only terminal, with mail disabled by the existing test router:

```powershell
$env:RENTALS_QA_MOUNT='/web-dev/3am-web'
& C:\php84\php.exe -S 127.0.0.1:8767 tests/rentals-dev-router.php
```

```powershell
& C:\php84\php.exe tests/rentals-services-http.php http://127.0.0.1:8767/web-dev/3am-web
& C:\php84\php.exe tests/rentals-completion-http.php http://127.0.0.1:8767/web-dev/3am-web
```

The upload suite initially failed at `Service category filter missing` because its assertion used the Equipment fixture slug. After the test correction above, the entire suite passed. No remaining local test failures.

JavaScript tests passed under Node 24.21.0 using VS Code's installed Electron in Node mode (no standalone Node command was available):

```powershell
$oldElectron=$env:ELECTRON_RUN_AS_NODE
try {
  $env:ELECTRON_RUN_AS_NODE='1'
  foreach($test in @('tests/rentals-services-ui.mjs','tests/rentals-submit-ui.mjs','tests/rentals-qa-ui.mjs','tests/rentals-completion-ui.mjs')) {
    & 'C:\Users\PC-3\AppData\Local\Programs\Microsoft VS Code\Code.exe' $test | Out-Host
    if($LASTEXITCODE -ne 0) { throw "Failed: $test" }
  }
} finally {
  if($null -eq $oldElectron) { Remove-Item Env:ELECTRON_RUN_AS_NODE -ErrorAction SilentlyContinue }
  else { $env:ELECTRON_RUN_AS_NODE=$oldElectron }
}
```

PHP `-l` passed for every changed/new PHP file; `git diff --check` passed. Browser-based visual checks and actual SMTP sends were not performed. The JavaScript tests exercise event behavior using fixtures.

## Production diagnosis and conditional deployment

1. With production shell access, run `php bin/rentals-services-check.php` against the existing configuration. This is read-only; inspect the database identity, issues, item 10 relationship and eligibility. Confirm deployed code matches this checkout and use PHP >=8.2 with the declared extensions.
2. If the table is reported missing, distinguish a wrong database from missing schema or insufficient account visibility using the database administrator's read-only inspection. Do not grant DDL to the application user. The new server log identifies this readiness branch without exposing internals publicly.
3. **No production missing table or column is confirmed yet, so no new migration is proposed or executed.** Existing reviewed proposals are `docs/rentals-service-requests-additive.sql` (base table), the Service-only clauses of `docs/rentals-completion-additive.sql` (quotation/payment fields and notification link), and `docs/rentals-notification-ledger.sql` (ledger if absent). If diagnostics confirm missing dependencies, prepare only the absent clauses after checking parent ID types/signedness, InnoDB, existing keys and columns. `CREATE TABLE IF NOT EXISTS` does not repair an incompatible existing table; blindly rerunning ALTER statements can fail. Do not apply unrelated Equipment/password-reset changes to repair Services.
4. Preserve the existing application encryption key and protected proof storage. Verify runtime SELECT/INSERT/UPDATE permissions and storage access without printing credentials. Confirm configured SMTP routing using the existing mailer requirements.
5. Only after explicit deployment/migration authorization, deploy reviewed files and any confirmed necessary additive SQL; verify customer form, Admin queue, quotations/proofs and empty states in production with authorized test accounts. The live 503 repair remains pending this evidence and deployment; passing local tests does not mean the live site is fixed.
