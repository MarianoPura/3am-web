# 3AM RENTALS — REMAINING QA FIXES

Date: 2026-10-01. Branch: `feature/website-ui-refresh`. No commit, push or merge.

## Senior Refactor

The removed `app/Views/rentals/admin.php` was not recreated. Separated Admin views remain separate; the Analytics controller/views were untouched. No new availability system or blackout table was added. The manually repaired live `rental_item_blackouts` table is treated as resolved.

## Existing Diff Review

| Pre-existing change | Decision | Reason |
|---|---|---|
| `.htaccess` managed-image rewrite | Keep | A valid legacy managed file inside the checkout otherwise bypasses the app route and may hit direct-access denial. Apache behavior needs live confirmation. |
| `RentalAdminController` unknown blackout action guard | Keep | The removed `reserved` action previously fell through to creating a maintenance block. |
| `RentalAdminController` QR upload fix, payment create/edit views and shared payment-image partial | Keep | Confirmed current refactor regression: the payment QR fields existed in the canonical model, but upload forms/save method ignored them. Mounted Admin test passes new/replacement QR. |
| `tests/rentals-admin-qa.php` stale manual-reservation test removal | Keep | Current senior-refactored blackout form has no reservation order creation; pending/approved/rejected order fixtures still test authoritative stock. The test also covers duplicate category names and no-image fallback. |
| `bin/rentals-diagnose.php` | Removed | Temporary local diagnostic is no longer needed: the live read-only query identified the missing image references. |
| Old schema precheck/migration SQL, isolated missing-column fault test, separate schema-only report | Removed | Those untracked local artifacts served the now confirmed and manually repaired missing-table investigation. Nothing was run live. |
| `docs/rentals-post-refactor-qa-2026-10-01.md` | Rewritten | Prior report called the live 500 unresolved; this report reflects the confirmed repair and current QA. |

## Resolved Critical Regression

The user confirmed the live table was missing, then manually created it and observed the four flows working. Locally, public Availability returns HTTP 200 with valid JSON; mounted customer/Admin tests cover Add to Cart, Product Edit, Admin calendar, blocks and unblocking. The suite verifies Pending and Approved reservations consume units, Rejected reservations release units, and removing an admin block preserves customer reservation stock.

## Latest Live Follow-up: Three Separate Issues

1. **Product image display — confirmed URL guard failure.** The live Equipment page now has an assigned managed path for Sony FX3 Cinema Camera, and `RentalCatalog::imagePath()` accepted it while rendering. Its former public URL under `/web-dev/3am-web/micro/rentals/products/...` returned **HTTP 404** with `X-Reserved-Path: 1`. `ReservedPath` intercepted the `/micro` segment before the product-image controller ran. The live evidence does not establish a missing physical file or Apache `.htaccess` denial. The code now keeps the same relative DB path and external physical file but generates `/rentals/product-image/<filename>` for cards, detail modal, and Admin preview; the controller validates and streams it. The old `/micro` public URL remains denied. Mounted Admin QA verifies new route HTTP 200, image bytes, and rejection of the old/invalid URLs. Live deployment and retest are still required.
2. **Checkout proof — confirmed key configuration failure.** The newly reported live HTTP 422 says `A stable application key is required for payment proof storage.` In any non-local/testing environment, `RentalPaymentProof::key()` requires `config('app.key')`, sourced from `APP_KEY`, to have at least 32 bytes. This is separate from storage permissions and database state. The server administrator must securely configure one stable value in the live environment and preserve it across deployments; rotating it would prevent decryption of proofs encrypted with the prior value. No secret was generated, printed or committed here.
3. **Payment Method edit — confirmed missing live column.** The user's live `SHOW COLUMNS FROM payment_methods` output has no `qr_image_path`, while canonical `database/rentals.sql` defines nullable `varchar(255)`. On edit, `validateAndSavePayment()` executes `SELECT qr_image_path FROM payment_methods WHERE id = ?` before any image upload or UPDATE. MySQL's unknown-column exception enters the generic `Throwable` catch, producing the misleading required-fields/unique-name notice. The case-insensitive duplicate query already excludes the edited ID (`id <> ?`); required-field and duplicate-name validation have specific messages, and a blank QR upload preserves the old path. Mounted Admin QA exercises the separated edit endpoint with unchanged name and no replacement QR and passes. No local schema or save-code change is required. After a separate authorization/backup, the safe **additive** live repair is `ALTER TABLE payment_methods ADD COLUMN qr_image_path VARCHAR(255) DEFAULT NULL AFTER provider;`; do not run it if the column already exists. This pass did not execute any live migration.

## Equipment Images

- **Historical live snapshot:** the user's earlier read-only query returned `image_path = NULL` for active equipment IDs **1, 2, 3 and 4**. The later successful Sony FX3 upload means that snapshot is no longer current for every row. A NULL reference still receives the branded placeholder; the Sony FX3 reference exists and exposed the separate reserved-URL failure documented above.
- **Read path:** `RentalCatalog::load()` selects `i.image_path`; `app/Views/rentals/items.php` passes it to the image partial; `RentalCatalog::imagePath()` checks the referenced local file. Managed images now use the Rentals public controller URL while legacy static media still uses `site_media()`. The placeholder remains only a fallback.
- **Write path:** both Admin Add/Edit Product forms use `multipart/form-data` and `product_image`. `RentalManagedImage::store()` validates a JPG/PNG/WebP upload and returns a generated `micro/rentals/products/...` path. `RentalAdminController::validateAndSaveItem()` writes it to `rental_items.image_path` on insert/update, or retains the old value when no replacement is uploaded. Mounted Admin QA confirms creation, persistence, public image delivery and replacement locally. No additional application code fix was identified for this NULL-data cause; live upload/persistence still needs a manual check.
- **No verified ID-to-photo map:** `database/seeds/rentals.php` supplies preview-only visual fixtures and never inserts live products. `bin/rentals.php --seed-test` creates explicitly named `[TEST]` products, not authoritative live IDs 1–4. The repository contains camera and lighting assets, but neither schema nor seed/migration/Admin data maps those files to the four live IDs. Local IDs differ from live IDs, so local rows cannot establish a live mapping. Do not assign files by ID or apply guessed SQL updates.
- **Safe remediation:** deploy the corrected public image route and retest the already assigned Sony FX3 photo first. For any remaining item without a photo, confirm its identity and upload its approved image through live Admin Edit Product. Verify each saved `image_path` and public image response. There is no justified per-ID SQL mapping from the repository assets.
- **Routing/fallback:** the old `.htaccess` rewrite remains to prevent direct serving of legacy `/micro` copies. The new `/rentals/product-image/<filename>` route is inside the permitted Rentals namespace. The branded fallback SVG returned HTTP 200 locally; the managed route returned HTTP 200 in mounted tests.

## Earlier Upload Storage Diagnostic

The earlier upload messages prompted the following diagnostic. Later live evidence shows a product upload saved, and the current Checkout 422 is explicitly the missing stable application key. Neither current issue should be attributed to storage permissions without new evidence.
- Both paths use `RentalStorage::root()`: the configured absolute root or, by default, `dirname(BASE_PATH)/micro`. The physical targets are `<root>/rentals/products` and `<root>/payment`; `/web-dev/3am-web/` is a URL prefix and is not used in either physical path. They are automatically created with `0750` when the PHP worker can create them. The worker must be able to traverse parents and write target directories. A missing `.htaccess` deny guard must also be creatable there. `.htaccess` routing affects HTTP serving, not PHP's upload write itself.
- `bin/rentals-upload-check.php` is a read-only path/configuration diagnostic. It passed on local Windows and distinguished a deliberately absent local root without creating it. CLI/root status is not proof of PHP-FPM/Apache worker access on live. The helper now logs distinct `mkdir`, not-writable, and deny-guard failures; proof storage logs later file-create/write failures. These are diagnostic-only code changes; validation, encryption, relative DB paths, and public access policy are unchanged.
- If a future upload fails before saving, run the diagnostic under the actual PHP worker identity/configuration and inspect the resolved root and target directories. Preserve existing uploads, keep deny guards, avoid world-writable permissions, and ensure the root survives release replacement. No live filesystem write was performed in this pass.

## Checkout Fallback Follow-up

The reported proof error is not hardcoded in the Checkout view or JavaScript. Normal GET sets `error = null`; the view renders an alert only for a non-null actual submission error. A failed POST renders an escaped safe error with HTTP 422; a fresh GET has no stale proof error. Empty Cart redirects to Cart before Checkout GET and shows its actual empty state. The mounted HTTP test now asserts that normal/fresh Checkout content has no false storage error, and successful Checkout renders confirmation.

## Checkout Payment QR Follow-up

The Admin QR edit and managed `/rentals/payment-qr/{id}` route were already working, but `RentalCheckout::activePaymentMethods()` omitted `qr_image_path` from its SELECT. Checkout therefore had account details but no path for its QR condition. The query now includes the column. Each manual payment panel renders its own QR inline above the account details only when `RentalManagedImage::publicPath()` confirms a saved file; its `src` uses the existing managed route, never `/micro`. The existing selector shows one panel at a time, so switching methods switches the QR with the account information. A NULL/missing QR path renders no image or QR control; a failed image request shows a short safe message and leaves the account details available. Mounted Admin QA covers query payload, inline markup, no-QR rendering and managed route. Both `tests/rentals-admin-qa.php` and `tests/rentals-http-flow.php` passed at `http://127.0.0.1:8015/web-dev/3am-web` using the same isolated storage root as the QA server. No Admin edit, schema, or payment-proof changes were made for this follow-up.

## Admin Payment Proof Follow-up

`ordersView()` loads `order_header.payment_proof_path` through `h.*`. The Admin view links to `/rentals/admin/proof/{orderId}`, and that route checks Admin/Superadmin access before looking up the order and delegating to `RentalPaymentProof::response()`; guests and customers now get HTTP 403 on this file route. Encrypted managed files remain in external storage; the response decrypts them with the configured `APP_KEY`, returns `image/*` or `application/pdf` with inline disposition, and is private/no-store. Direct `/micro/payment` access remains denied. A path in the database alone does not establish that the file still exists or can be decrypted.

The Admin view now shows a clean empty state for no reference or a missing file, uses a protected inline image for JPG/PNG/WebP, and opens PDF through the same protected route in a new tab. A browser image-load failure shows a safe message and protected link. Failed AES-GCM authentication returns HTTP 422 rather than being confused with a missing-file 404; missing/invalid references still return 404, while absent live `APP_KEY` remains a server configuration error. The new read-only database QA script `tests/rentals-proof-view-qa.php` passed image/PDF content types and bytes, inline disposition, no-store, tampered-ciphertext 422, protected Admin markup, and both empty states. The exact live response status for the affected order is still needed to distinguish file/path, authorization, and key/decryption causes; this local pass did not inspect a logged-in live Admin request. No schema, live database, or secret was changed.

The user subsequently reported HTTP **404** for the affected live Admin proof request. A separate unauthenticated live request to `/web-dev/3am-web/rentals/admin/proof/1` redirected to `/rentals/account`, confirming that the live route is registered and access-controlled. A logged-in 404 therefore points beyond route registration: an incorrect order ID/URL, an absent or invalid `payment_proof_path`/physical file, or decryption authentication failure in the older live service (which also returned 404). The exact request URL and affected order path are still needed to distinguish these branches. The local 422 distinction and empty state are not deployed yet.

The user then confirmed live order **2** has a non-NULL managed `payment_proof_path`. This removes the NULL-reference branch, but does not prove the referenced file exists or decrypts. `bin/rentals-proof-check.php 2` is a read-only CLI diagnostic for the live PHP configuration: it reports external/legacy file presence, readability, file format, whether a stable production `APP_KEY` is configured, and the protected response status without printing proof bytes or key material. Run it as close to the web worker's environment as possible; differing CLI environment or permissions can change the result. If the file is present and encrypted, an absent `APP_KEY` should cause a server configuration error, whereas a wrong key or damaged ciphertext fails AES-GCM authentication (older live code reported 404; current local code reports 422). No live check or change was executed here.

## Remaining QA

- **Missing-image fallback:** the branded SVG has a neutral caption. An item with no image reference is announced as coming soon; an assigned path whose file is absent shows a visible "Image temporarily unavailable" label. Browser load errors switch to the same fallback and label. Local root and mounted HTTP 200; Admin QA covers both states without assigning a guessed product photo.
- **Modal:** existing fixed desktop grid, anchored left visual, and independently scrollable right panel retained. Calendar changes only update date cells. The in-app browser used here lacks native `dialog.showModal()`, so interactive modal motion remains a full-browser manual QA item.
- **Mobile/header:** one Rentals nav with a responsive toggle, `aria-expanded`, Escape/focus support, and visible focus styles. Cart and account stay in the header. Desktop, 768px tablet and 390px mobile DOM geometry show no horizontal page overflow; account icon/caret alignment uses flex.
- **Equipment grouping/carousel:** grouped from active database categories; filtering/search and category deep links retained. Shared scroll-snap control powers equipment rows and the four Use Cases cards, with Previous/Next, native swipe/scroll and arrow-key support. Boundary buttons disable correctly. Mobile cards use a shorter image ratio and constrained row width.
- **403:** customer Admin access remains real HTTP 403, with branded safe links. Guest still redirects to sign-in.
- **Empty Cart:** redirect notice is integrated into the empty panel, instead of competing with it.
- **Duplicate categories:** case-insensitive duplicate-name check gives a clear Admin error before insert/update, while preserving existing records. Mounted Admin QA covers it.
- **Support/Inquiry:** Home CTA still goes to `/rentals/support`. The Support CTA leads to `/start?type=rentals`; the unified inquiry now offers/presets Equipment Rentals. The same type allowlist controls POST, and existing persistence/email code carries the submitted type. HTTP 200 and selected preset verified; no real inquiry email was sent.
- **Login/Account:** no markup rebuild. Local page checks and HTTP customer flow pass. No overlap seen in desktop/mobile header checks.
- **Reject:** already present in the separate Order View and Admin review POST. HTTP tests cover approval, rejection and stock release; untouched.
- **Admin availability:** existing calendar and `rental_item_blackouts` retained; mounted QA covers block, partial unblock, customer reservation and rejected order behavior. Removed dead JS listener for a reservation form removed by the senior refactor.

## Regression

Mounted customer and Admin suites pass product/category CRUD, image upload/replacement, payment QR, auth, Cart, Checkout/proof, reports, approval/rejection, RBAC, CSRF, mounted URLs and safe production errors. Payment Report and Sales Report pass in those suites. Analytics architecture was untouched; it was not independently browsed. Main `/` and `/information` returned HTTP 200; their files were not changed.

## Tests

- `C:\php84\php.exe bin/rentals.php --check` — PASS.
- `C:\php84\php.exe tests/rentals-completion.php` — PASS.
- `C:\php84\php.exe tests/rentals-integration.php` — PASS.
- `C:\php84\php.exe tests/rentals-urls.php` — PASS.
- `C:\php84\php.exe tests/rentals-payment-report.php` — PASS (temporary-table schema fault checks only).
- `C:\php84\php.exe bin/rentals-upload-check.php` — PASS locally; no live-worker claim.
- `C:\php84\php.exe tests/rentals-http-flow.php http://127.0.0.1:8013/web-dev/3am-web` — PASS with shared isolated upload root.
- `C:\php84\php.exe tests/rentals-admin-qa.php http://127.0.0.1:8013/web-dev/3am-web` — PASS with shared isolated upload root.
- `node --check js/rentals.js`, `node --check js/rentals-admin.js` — PASS.
- PHP lint on every modified/new PHP file and `git diff --check` — PASS.
- Direct HTTP checks: `/rentals`, `/rentals/items`, `/rentals/support`, `/rentals/cart`, `/start?type=rentals`, `/information`, `/` — 200. Root and mounted fallback SVG — 200. Browser search/filter, carousel buttons, mobile menu/Escape and responsive overflow — PASS where supported.
- October 1 follow-up: mounted customer/Admin suites rerun after storage diagnostics, the Checkout false-error assertion and the image-state change; both PASS. Desktop, 768px tablet and 390px mobile Rentals browser checks found no page-level horizontal overflow; mobile menu/Escape passed. This in-app browser lacks native `dialog.showModal()`, so interactive modal movement remains unverified here.
- Latest live follow-up: the Sony FX3's old `/micro` image request was verified as **HTTP 404 with `X-Reserved-Path: 1`**. After the route change, `tests/rentals-admin-qa.php http://127.0.0.1:8014/web-dev/3am-web` passed the new image URL, old-URL denial, and unchanged-name Payment Method edit; `tests/rentals-http-flow.php` on the same mounted server also passed. The new route has **not** been deployed or tested on live.

A first mounted test run failed because the test client and server used different temporary storage roots. It passed on rerun with the same isolated root. Record counts after reruns matched the baseline: users 3, categories 1, items 4, blackouts 2, carts 2, cart_items 1, methods 3, orders 17, details 19.

## Files Modified

Current upload/Checkout/image follow-up: `app/Services/RentalStorage.php`, `app/Services/RentalPaymentProof.php`, `app/Views/rentals/items.php`, `app/Views/rentals/partials/image.php`, `bin/rentals-upload-check.php` (new), `css/rentals.css`, `docs/rentals.md`, `js/rentals.js`, `media/rentals-equipment-placeholder.svg`, `tests/rentals-admin-qa.php`, `tests/rentals-http-flow.php`, and this report. The service changes add diagnostics only; the HTTP test adds a no-false-Checkout-error assertion. The image changes distinguish unassigned photos from assigned paths that fail; Admin QA tests both states.

Latest live follow-up changes on the current branch: `.htaccess` (comment only), `app/Models/RentalCatalog.php`, `app/Views/rentals/admin/items-edit.php`, `app/Views/rentals/items.php`, `app/Views/rentals/partials/image.php`, `routes/web.php`, `tests/rentals-admin-qa.php`, `docs/rentals.md`, and this report. The Payment Method save code and proof encryption code were not changed in this latest follow-up.

Earlier QA pass (already part of the current branch before this follow-up):

`.htaccess`, `app/Controllers/Rentals/RentalAdminController.php`, `app/Controllers/Web/InquiryController.php`, `app/Views/rentals/admin/payments-create.php`, `app/Views/rentals/admin/payments-edit.php`, `app/Views/rentals/partials/admin-payment-image.php`, `app/Views/rentals/partials/header.php`, `app/Views/rentals/partials/image.php`, `app/Views/rentals/forbidden.php`, `app/Views/rentals/items.php`, `app/Views/rentals/index.php`, `app/Views/rentals/cart.php`, `app/Views/rentals/support.php`, `config/forms.php`, `css/rentals.css`, `js/rentals.js`, `js/rentals-admin.js`, `media/rentals-equipment-placeholder.svg`, `tests/rentals-admin-qa.php`, `tests/rentals-http-flow.php`, and this report. The temporary untracked `bin/rentals-diagnose.php` was removed in the final image pass.

## Manual Live QA Still Needed

1. Deploy and retest the Sony FX3 image via `/rentals/product-image/<filename>`: expect HTTP 200 and the actual photo; verify the old `/micro/...` URL remains denied. Confirm other approved photos through Admin without guessing file mappings.
2. The server administrator must configure a stable live `APP_KEY` of at least 32 bytes, held outside the repository and preserved across deployments. Retest proof upload/Checkout with the same key and preserve any previously encrypted proofs.
3. After an authorized backup, add the missing nullable `payment_methods.qr_image_path` column using the inspected additive SQL above, then retest edit with unchanged name, edit with/without a QR, and QR display. No migration was executed here.
4. In a full browser, open a product detail dialog and verify image position through month/quantity/date changes on desktop, then test the same on mobile. The in-app test browser lacks `showModal()`.
5. After deploying remaining changes, check the live menu, category filtering/carousels, customer 403 presentation, Support inquiry preset and category duplicate feedback. No live database writes were performed in this QA pass.

`.env` untouched. No secrets exposed. No destructive live database actions. No commit, push or merge.
