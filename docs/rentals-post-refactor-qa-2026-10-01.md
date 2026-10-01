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

## Equipment Images

- **Confirmed live state:** the user's read-only query on October 1 returned `image_path = NULL` for all four active equipment rows, IDs **1, 2, 3 and 4**. Public `/rentals/items` returned four cards but no equipment image URLs. Without a stored reference, `RentalCatalog::imagePath()` deliberately returns null and the branded placeholder renders. These rows do not establish an HTTP image-serving failure. A subsequent live Admin upload attempt failed while saving, so the NULL values and the unresolved live storage failure must both be addressed.
- **Read path:** `RentalCatalog::load()` selects `i.image_path`; `app/Views/rentals/items.php` passes it to the image partial; `RentalCatalog::imagePath()` checks the referenced local file and the partial uses `site_media()` for a valid path. The placeholder stays as the fallback.
- **Write path:** both Admin Add/Edit Product forms use `multipart/form-data` and `product_image`. `RentalManagedImage::store()` validates a JPG/PNG/WebP upload and returns a generated `micro/rentals/products/...` path. `RentalAdminController::validateAndSaveItem()` writes it to `rental_items.image_path` on insert/update, or retains the old value when no replacement is uploaded. Mounted Admin QA confirms creation, persistence, public image delivery and replacement locally. No additional application code fix was identified for this NULL-data cause; live upload/persistence still needs a manual check.
- **No verified ID-to-photo map:** `database/seeds/rentals.php` supplies preview-only visual fixtures and never inserts live products. `bin/rentals.php --seed-test` creates explicitly named `[TEST]` products, not authoritative live IDs 1–4. The repository contains camera and lighting assets, but neither schema nor seed/migration/Admin data maps those files to the four live IDs. Local IDs differ from live IDs, so local rows cannot establish a live mapping. Do not assign files by ID or apply guessed SQL updates.
- **Safe remediation:** first identify and correct the live external storage directory/path/permission failure described below. Then in live Rentals Admin, open Products and edit each of IDs 1–4. Confirm its name/identity, select that item's approved product photo in **Replace image**, and save. Repeat per item; leave other fields as they are. Confirm each save shows an image preview, then verify the public cards and modal display the corresponding images and that `image_path` is non-NULL via a read-only query. This action has **not** been performed here. There is no justified exact per-ID SQL until the correct photo for each ID is confirmed.
- **Prior routing/fallback QA:** the earlier `.htaccess` managed-image rewrite and branded placeholder remain. The SVG returned HTTP 200 at root and mounted URLs locally; Apache rewrite behavior on live still needs verification with a newly uploaded file.

## Live Upload Storage Follow-up

- The live Product image message can come from `RentalStorage::directory('rentals/products')` or the later `move_uploaded_file()` call. The live Checkout proof message comes specifically from failure to obtain `RentalStorage::directory('payment')`, before encryption, `fopen()`/`fwrite()`, or order insertion. This points to the shared external storage root/directory/permissions, but the precise live filesystem condition is **LIVE FILESYSTEM CONFIRMATION PENDING**.
- Both paths use `RentalStorage::root()`: the configured absolute root or, by default, `dirname(BASE_PATH)/micro`. The physical targets are `<root>/rentals/products` and `<root>/payment`; `/web-dev/3am-web/` is a URL prefix and is not used in either physical path. They are automatically created with `0750` when the PHP worker can create them. The worker must be able to traverse parents and write target directories. A missing `.htaccess` deny guard must also be creatable there. `.htaccess` routing affects HTTP serving, not PHP's upload write itself.
- `bin/rentals-upload-check.php` is a read-only path/configuration diagnostic. It passed on local Windows and distinguished a deliberately absent local root without creating it. CLI/root status is not proof of PHP-FPM/Apache worker access on live. The helper now logs distinct `mkdir`, not-writable, and deny-guard failures; proof storage logs later file-create/write failures. These are diagnostic-only code changes; validation, encryption, relative DB paths, and public access policy are unchanged.
- Server action: run the diagnostic under the actual PHP worker identity/configuration, inspect the exact resolved root and target directories, and precreate/assign ownership or group access only to the required external storage paths if needed. Preserve existing uploads, keep deny guards, avoid world-writable permissions, and ensure the root survives release replacement. No live filesystem write was performed in this pass.

## Checkout Fallback Follow-up

The reported proof error is not hardcoded in the Checkout view or JavaScript. Normal GET sets `error = null`; the view renders an alert only for a non-null actual submission error. A failed POST renders an escaped safe error with HTTP 422; a fresh GET has no stale proof error. Empty Cart redirects to Cart before Checkout GET and shows its actual empty state. The mounted HTTP test now asserts that normal/fresh Checkout content has no false storage error, and successful Checkout renders confirmation.

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

A first mounted test run failed because the test client and server used different temporary storage roots. It passed on rerun with the same isolated root. Record counts after reruns matched the baseline: users 3, categories 1, items 4, blackouts 2, carts 2, cart_items 1, methods 3, orders 17, details 19.

## Files Modified

Current upload/Checkout/image follow-up: `app/Services/RentalStorage.php`, `app/Services/RentalPaymentProof.php`, `app/Views/rentals/items.php`, `app/Views/rentals/partials/image.php`, `bin/rentals-upload-check.php` (new), `css/rentals.css`, `docs/rentals.md`, `js/rentals.js`, `media/rentals-equipment-placeholder.svg`, `tests/rentals-admin-qa.php`, `tests/rentals-http-flow.php`, and this report. The service changes add diagnostics only; the HTTP test adds a no-false-Checkout-error assertion. The image changes distinguish unassigned photos from assigned paths that fail; Admin QA tests both states.

Earlier QA pass (already part of the current branch before this follow-up):

`.htaccess`, `app/Controllers/Rentals/RentalAdminController.php`, `app/Controllers/Web/InquiryController.php`, `app/Views/rentals/admin/payments-create.php`, `app/Views/rentals/admin/payments-edit.php`, `app/Views/rentals/partials/admin-payment-image.php`, `app/Views/rentals/partials/header.php`, `app/Views/rentals/partials/image.php`, `app/Views/rentals/forbidden.php`, `app/Views/rentals/items.php`, `app/Views/rentals/index.php`, `app/Views/rentals/cart.php`, `app/Views/rentals/support.php`, `config/forms.php`, `css/rentals.css`, `js/rentals.js`, `js/rentals-admin.js`, `media/rentals-equipment-placeholder.svg`, `tests/rentals-admin-qa.php`, `tests/rentals-http-flow.php`, and this report. The temporary untracked `bin/rentals-diagnose.php` was removed in the final image pass.

## Manual Live QA Still Needed

1. Diagnose and repair the live external storage root/target directory as the actual PHP worker. The two observed upload errors cannot be called fixed from local Windows tests. Preserve all existing uploads through deployment.
2. Supply the four correct product photos through live Admin Edit Product for IDs 1–4, after confirming each product's identity. Verify saved `image_path` values, image previews and public card/modal photos. No per-ID SQL mapping is justified by the current evidence.
3. Verify `.htaccess` managed image routing on Apache with a valid externally stored file and a legacy copy; PHP's built-in server does not exercise `.htaccess`.
4. In a full browser, open a product detail dialog and verify image position through month/quantity/date changes on desktop, then test the same on mobile. The in-app test browser lacks `showModal()`.
5. After deploying remaining changes, check the live menu, category filtering/carousels, customer 403 presentation, Support inquiry preset and category duplicate feedback. No live database writes were performed in this QA pass.

`.env` untouched. No secrets exposed. No destructive live database actions. No commit, push or merge.
