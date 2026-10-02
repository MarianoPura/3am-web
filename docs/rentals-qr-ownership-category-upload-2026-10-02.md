# Rentals QR ownership and category image uploads

## Protected QR/status flow

- Both `/rentals/order-status/{token}` and `/rentals/confirmation/{token}` now require an existing authenticated account. A token locates an order but grants no access by itself.
- Guests redirect to Rentals login. A strictly allowlisted session return target sends them back to the scanned status/confirmation URL after login, including after an incorrect-password attempt. Admin/Superadmin login also honors this target; ordinary Admin login still opens Dashboard.
- Access is allowed only when `order_header.user_id` matches the authenticated database user ID, or the database role is Admin/Superadmin. Another customer receives HTTP 403 with no order data. Unknown tokens receive 404 for authenticated users.
- The shared query returns reference, review status and a proof-present boolean. It does not pass proof paths, customer names, emails, phone numbers, payment references or internal notes to either view. QR content remains only the protected status URL with its opaque 64-character token. Proof encryption and existing protected proof routes are unchanged.
- Redirects, successful pages and denials are no-store/private responses.

## Categories: upload instead of local path

- Add/Edit Category now uses a multipart `category_image` upload field for JPG/PNG/WebP and displays the existing server upload limit. The local image-path input and unused path suggestions are removed.
- Category uploads reuse RentalManagedImage and the existing managed product-image delivery route/storage; no separate storage implementation or migration was introduced.
- Existing `rental_categories.image_path` remains unchanged unless a valid replacement is uploaded. A forged posted image_path is ignored.
- The existing image preview uses the managed URL. Invalid files cannot change the stored category image. If saving the record fails, the newly stored file is removed. Replacing an unshared managed image removes the superseded file; repository assets and images referenced by other categories/items are preserved.

## Files changed in this pass

- app/Controllers/Rentals/RentalCheckoutController.php
- app/Controllers/Rentals/RentalAccountController.php
- app/Controllers/Rentals/RentalAdminController.php
- app/Views/rentals/confirmation.php
- app/Views/rentals/status.php
- app/Views/rentals/admin/categories.php
- app/Views/rentals/admin/categories-create.php
- app/Views/rentals/admin/categories-edit.php
- tests/rentals-qr-ownership.php (new)
- tests/rentals-admin-qa.php
- tests/rentals-dev-router.php (the simulated DB-failure target now uses the public payment-QR SELECT because status requires login)
- docs/rentals-qr-ownership-category-upload-2026-10-02.md (new)

Earlier UI/category and fixed-mailer work remains uncommitted.

## Verification

- PHP lint: PASS for all PHP files changed in this pass.
- JavaScript syntax: rentals-qr.js PASS; QR generation code is unchanged.
- rentals-qr-ownership.php: PASS on the `/web-dev/3am-web` mount. Separate guest sessions, failed login, owner, other customer, Admin, Superadmin, both routes, safe QR payload, absence of private fields/paths, unknown token, logout and no-store headers covered.
- rentals-admin-qa.php: PASS, including real category multipart upload/create/edit/preview, image preservation, forged-path rejection, invalid image rejection, replacement/old-file cleanup, and managed image HTTP bytes. Existing product/payment/availability/auth/CSRF/report behavior also passed.
- rentals-http-flow.php: PASS, including Cart/Checkout, duplicate POST/proof/Pending notification protection, current receipt/status after review, encrypted proof delivery and proof replacement.
- No live database mutation, schema migration, secret or .env change. No commit/push/merge. Local fixture records/files are cleaned up by the tests.

The security and upload changes are locally verified; deployment to the live server has not been performed.
