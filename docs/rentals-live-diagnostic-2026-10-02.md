# 3AM RENTALS — LIVE DIAGNOSTIC REPORT

> Retired after the user confirmed that live is fully working. Temporary request/stage logging and its helper/test were removed locally. Normal safe error handling, read-only CLI checks, working fixes and functional QA tests remain. The report below records the earlier diagnostic pass, not the current runtime. No live deployment was performed during cleanup.

Cleanup verification: PHP lint, completion, integration, URLs and encrypted proof/view QA passed. Mounted HTTP customer and Admin suites both passed at `http://127.0.0.1:8017/web-dev/3am-web`; Home, Information, Rentals, Equipment and Account returned 200. JavaScript syntax and `git diff --check` passed. No runtime references to the removed helper remain. Config, routes, schema, CSS, JavaScript and media are unchanged. Existing local record counts remain the same; no live changes or deployment were made.

Branch: `feature/website-ui-refresh`. Diagnosis: **locally verified / live confirmation pending**. This pass adds diagnostics; it does not claim that deploying logging alone fixes live storage or key configuration.

## Other AI Review Verification

- Route mismatch found: **no**. `routes/web.php` registers `GET /rentals/admin/proof/{id:int}`; the Admin Order view generates that route.
- Controller mismatch found: **no**. `RentalAdminController::viewProof()` authorizes Admin/Superadmin, selects `order_header.payment_proof_path`, and delegates to `RentalPaymentProof::response()`.
- The other AI's route/controller conclusion is confirmed. Its testing limitation is also correct: service/view tests alone cannot establish live worker permissions or the live encryption key.
- Latest supplied live status: **404 for Order #2**. The supplied DB result confirms a non-NULL managed proof reference. No authenticated live request was repeated during this pass.
- Older live code conflates missing/unreadable files and failed decryption into 404. It therefore does not prove a missing route. Current local code preserves the previously reviewed 403/404/422 distinctions.

## Diagnostic Architecture

The existing bootstrap error/shutdown handlers, front-controller catch, database failure logger, and `storage/logs/php-error.log` are reused. No second global exception handler, logging framework, or public error-ingestion route was installed.

`RentalDiagnostic` buffers at most 64 safe stage records and flushes them on a failure/warning. Every emitted line in one PHP request uses the same random eight-character correlation ID. Repeated catalog image warnings are deduplicated within the request. Ordinary successful flows emit no diagnostic log. A successful HTTP response may still log a genuine condition such as an assigned but missing image or failed notification delivery.

Context includes method, route with dynamic segments replaced by `:id`/`:value`, controller/action when known, safe failure category, exception class, code-relative source/line, SQLSTATE, and response status where available. PHP automatic raw error logging is disabled for Rentals requests to prevent a second, unsanitized fatal log; the existing handlers explicitly emit sanitized diagnostics. Main-site error handling remains unchanged.

Fields and string values are allowlisted. No exception messages, SQL/bindings, form bodies, query strings, cookies, session/CSRF/status tokens, credentials, APP_KEY values, customer details, uploaded bytes, plaintext, or ciphertext are logged. Storage roots are compared using a fingerprint, with only the allowlisted storage subdirectory and external/legacy source logged. The CLI checker can show the configured directory to its authorized operator without printing private filenames.

The two diagnostic commands define a read-only flag before bootstrap. It suppresses explicit and automatic log writes and prevents local proof-key creation. They use SELECT/file reads only and never call the upload directory-creation flow. An absent local key is reported instead of created. Local runs verified that the log file size stayed unchanged.

## Admin Proof #2

| Check | Evidence |
| --- | --- |
| Route/controller | Verified in repository; real local routed Admin/Superadmin proof tests pass |
| Live authorization | Pending; local guest/customer requests return 403 |
| Live DB path | Present, confirmed by the user; NULL is ruled out |
| Resolved live storage root | Pending; configured `rentals.storage_root` / `RENTALS_STORAGE_ROOT`, default sibling `micro` |
| External / legacy file exists | Pending on live; now logged separately |
| File readable / encrypted format | Pending on live; now separate file-read, format and encrypted-length stages |
| APP_KEY configured / length valid | Pending on live; missing and short keys now distinguishable without the value |
| Decryption | Pending on live; failure cannot alone distinguish a different key from damaged ciphertext |
| Response / exact failure stage | Supplied older-live 404 remains ambiguous; local checker reports **Order not found**, because local DB has no Order #2 |

The logged chain is start → authorization → order lookup → DB path → external/legacy candidates → readability → format → key status → decrypt → MIME/response. Legacy guarded proofs retain their existing compatibility behavior and log decrypt as skipped. No plaintext proof file is exposed through `/micro`.

## Product Image Upload

Local real HTTP uploads pass PHP receipt, upload error/size/temp validation, image MIME/content validation, external storage resolution, directory creation/readiness, deny guard, `move_uploaded_file()`, final file existence, and `rental_items.image_path` save. Target: `<RentalStorage root>/rentals/products`; DB reference: `micro/rentals/products/<generated hash>.<jpg|png|webp>`.

Live failing stage remains pending. Diagnostics now distinguish PHP upload rejection, validation, invalid root, failed mkdir, nonwritable directory, failed guard write, failed move, and DB save failure. The existing user-facing error and upload limits are preserved; no security check or file mode was relaxed.

## Product Image Serving

The current URL is `<application base>/rentals/product-image/<generated filename>`. Mounted local HTTP QA verifies the route, external file, readable PNG bytes, `image/png`, and HTTP 200. Invalid filename and unavailable resource cases remain non-200. The old direct `/micro` request remains denied.

The supplied old live `/micro/rentals/products/...` 404 is a separate delivery/deployment issue from upload writes. Current deployment/cache state cannot be determined from local tests. After deployment, inspect the actual request URL and status: an old `/micro` request means stale/undeployed markup or helper code; a managed-route failure can now be traced through safe filename validation and storage checks. No hardcoded replacement image was added, and the existing branded fallback was retained.

## Checkout Payment Proof

Local routed checkout passes mandatory proof validation, encrypted storage, order transaction and cleanup, and subsequent protected decryption. Target: `<RentalStorage root>/payment`; DB reference: `micro/payment/<generated hash>.<jpg|png|webp|pdf>` stored in `order_header.payment_proof_path`.

Logs distinguish PHP upload error, size/temp readability, MIME, directory readiness, APP_KEY presence/length, encryption, exclusive file creation, write failure and transaction rollback/DB path save. Production still requires the existing `config('app.key')`, populated by `APP_KEY`, with at least 32 bytes. Configure it through the server's secret/environment mechanism and preserve the same value across deployments and PHP workers. Do not replace an existing key merely to clear an error: old proofs require the key used to encrypt them.

The supplied prior live 422 establishes a key configuration problem at that time, but not whether the key was empty or short. Current live key/storage state remains pending. Local tests separately cover missing, short, different and valid keys, malformed/truncated proofs, and successful PNG/PDF responses. No secret was changed or encryption weakened.

## Payment Method QR

`RentalCheckout::activePaymentMethods()` includes `qr_image_path`. The Checkout view receives it, conditionally generates `/rentals/payment-qr/{id}`, and retains the existing method-switching, account instructions and larger-QR UI. NULL has no broken image. Admin Edit behavior was not changed.

Local Admin QA verifies upload/replacement, unchanged-name edit, Checkout data and markup, NULL handling, and the managed QR route returning the original PNG. The diagnostic-only view addition buffers record ID, path presence and URL-generation status; missing/invalid files or delivery failures emit a log. No new QR/schema fix was necessary in this pass. Live display confirmation remains pending.

## Other Rentals Errors Found

No additional business/schema defect was established. Existing raw database and caught Rentals exception logging could expose submitted values inside exception messages; it has been replaced with safe classifications for Rentals.

Examples below are intentional **local QA failures**, not new live incidents:

| Route | Request ID | Failure stage | Status | Evidence |
| --- | --- | --- | --- | --- |
| `/rentals/admin/proof/:id` | `2d97f1d8` | decrypt | 422 | Test-owned encrypted proof deliberately damaged, then restored |
| `/rentals/order-status/:value` | `aa87e88b` | database query / uncaught | 500 | QA router deliberately selects a nonexistent database; safe PDO classification, no token/credentials |
| `/rentals/admin/items/:id/blackouts` | `3647312d` | CSRF | 419 | Test deliberately omits a valid token; no write dispatched |

Availability records reservation/blackout query stages, result counts and calculation. Query exceptions are labeled separately and rethrown without changing behavior. Cart failures record item/quantity/date validity, availability and DB stages. Admin block failures record authorization, CSRF, dates and insert/removal stages. `reservation_conflict=skipped` on manual block changes is intentional: this existing flow neither edits customer orders nor performs a new reservation-conflict query. Category/product/payment CRUD log field-specific validation, duplicates and save failures. Reports retain failure-only logging. Auth records role categories only.

Missing-image warnings on the isolated QA server can reflect existing local records whose files live in the normal sibling root, not in the test upload root. The tests do not copy or overwrite those files. A different root fingerprint makes this distinction visible.

## Client-Side Findings

Automated Network checks cover mounted requests, statuses, protected proof bytes, image/QR delivery, malformed uploads, CSRF and safe production errors. Mounted `/`, `/information`, `/rentals`, `/rentals/items` and `/rentals/account` return 200. Main-site views/styles and Rentals JavaScript were not changed.

Both Rentals JavaScript files pass syntax checks. No interactive browser Console audit was performed in this diagnostic pass. Server logs cannot detect every JS exception, unhandled promise rejection, CSP block, stale client cache or browser image load failure. Use DevTools Network to inspect the exact URL, method, status, response and initiator, and Console for JS/CSP errors. No browser state is sent to a new reporting endpoint.

## Tests

Commands executed from `C:\Users\PC-3\3am-web`:

| Command | Result |
| --- | --- |
| `C:\php84\php.exe bin/rentals.php --check` | PASS: schema/relationships; existing record counts preserved |
| `C:\php84\php.exe tests/rentals-completion.php` | PASS |
| `C:\php84\php.exe tests/rentals-integration.php` | PASS |
| `C:\php84\php.exe tests/rentals-urls.php` | PASS |
| `C:\php84\php.exe tests/rentals-diagnostics.php` | PASS: redaction, one correlation ID, bounded traces, quiet success, file/key/storage failures, fatal handling and read-only logging |
| `C:\php84\php.exe tests/rentals-proof-view-qa.php` | PASS: encrypted PNG/PDF and protected view/empty-state markup |
| `C:\php84\php.exe -d variables_order=EGPCS tests/rentals-http-flow.php http://127.0.0.1:8016/web-dev/3am-web` | PASS: real routed customer/Admin/Superadmin proof checks, damaged-proof 422, cart/checkout/review flow |
| `C:\php84\php.exe -d variables_order=EGPCS tests/rentals-admin-qa.php http://127.0.0.1:8016/web-dev/3am-web` | PASS: CRUD/upload/QR/availability/security/report/production-error regressions |
| `C:\php84\php.exe bin/rentals-upload-check.php` | PASS/read-only: local root and upload subdirectories exist, writable, guards present |
| `C:\php84\php.exe bin/rentals-proof-check.php 2` | Completed/read-only: **Order not found locally**, not a live proof result |
| `C:\php84\php.exe -l <each modified/new PHP file>` | PASS |
| Bundled `node.exe --check js/rentals.js` and `--check js/rentals-admin.js` | PASS |
| `git diff --check`, `git diff --stat`, `git status --short` | Checked; no whitespace errors; changes uncommitted |

HTTP server command: `C:\php84\php.exe -d variables_order=EGPCS -S 127.0.0.1:8016 tests/rentals-dev-router.php`. Server and HTTP test clients use the same process-only `RENTALS_STORAGE_ROOT` under the permitted visualizations workspace; server mount is `/web-dev/3am-web`. `.env` was not edited.

Local CLI uses `C:\php84\php.ini`, upload limit 2M, POST limit 8M, maximum 20 files and default system temporary directory. CLI may differ from live Apache/FPM in OS user, php.ini, environment, working directory, permissions and deployment paths. CLI success does not prove web-worker success. The upload checker now shows php.ini and working directory to help compare them.

Live next step after an authorized deployment: reproduce each failure separately, then inspect its correlated lines in `storage/logs/php-error.log`. Ensure that directory is writable by the PHP worker. For an upload permission failure, give the actual PHP worker ownership/write access to the external upload directories and traversal access to their parents. Existing directories use 0750; proofs use 0600 and product/QR files 0640. Preserve these protections; do not use chmod 777. Preserve external runtime storage and the original key across releases/backups. No deployment script inspected here automatically deletes external uploads, but the actual live deployment procedure must retain them.

## Files Modified

- `app/Services/RentalDiagnostic.php` (new)
- `app/Services/RentalStorage.php`
- `app/Services/RentalManagedImage.php`
- `app/Services/RentalPaymentProof.php`
- `app/Services/RentalCheckout.php`
- `app/Services/RentalNotification.php`
- `app/Models/RentalCatalog.php`
- `app/Controllers/Rentals/RentalAdminController.php`
- `app/Controllers/Rentals/RentalCartController.php`
- `app/Controllers/Rentals/RentalCheckoutController.php`
- `app/Controllers/Rentals/RentalAccountController.php`
- `app/Controllers/Rentals/RentalsController.php`
- `app/Views/rentals/checkout.php` (diagnostics only)
- `app/Core/Database.php` (Rentals-only log sanitization)
- `bootstrap.php` (existing handlers extended)
- `index.php` (existing dispatch/catch extended)
- `bin/rentals-upload-check.php`
- `bin/rentals-proof-check.php`
- `tests/rentals-http-flow.php`
- `tests/rentals-diagnostics.php` (new)
- `docs/rentals-live-diagnostic-2026-10-02.md` (this report)

Confirmed: no `.env` change; no secret or proof contents logged by the new diagnostics; no application/live DB schema or data change; no security bypass; no commit, push or merge. Existing local tests use temporary records/transactions and clean up their fixtures; local record counts match the pre-HTTP baseline (3 users, 1 category, 4 items, 2 blackouts, 2 carts, 0 cart items, 3 methods, 18 orders, 20 details). No live database mutation was executed.
