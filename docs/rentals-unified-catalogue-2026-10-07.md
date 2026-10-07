# Rentals unified catalogue — 7 October 2026

## Scope and behavior

Equipment and Services now share `/rentals/items`, with clickable choices at the top. Services uses `?type=services`. The existing `/rentals/services` URL remains valid and renders the same shared catalogue in Services mode. Both choices are ordinary links, so navigation works without JavaScript.

The existing `rental_items.is_service` boolean remains authoritative: `0` selects equipment and `1` selects services. No new schema, classification, or duplicate booking implementation was introduced. Only the selected type's cards are rendered. The existing catalogue model still loads the active records and excludes inactive categories/items.

- Equipment uses a wrapping grid within each category: four cards per desktop row, two on tablet and one on mobile. Horizontal scrolling and carousel arrows were removed from the equipment catalogue. The detail/gallery dialog, stock, rental dates, quantity and Add to Cart flow remain intact.
- Services shows production support descriptions, ideal use, listed rates/quotation guidance, Request service and service request history. It does not show equipment stock, deposits or Add to Cart. The existing service request/quotation/payment workflow remains separate.
- Search and category filters work for both types. Service category choices derive from the actual active service records, including categories containing services only.
- Rentals header/footer now have one Equipment & Services entry. Styling uses existing 3AM tokens and is scoped to `.rentals-module`.

## Micro storage and image delivery

The existing implementation already stores new product/service images in the external `RentalStorage` root, under `rentals/products`. The local configured root is `C:/Users/PC-3/micro`, outside the checkout. Database references remain portable (`micro/rentals/products/<random filename>`), and generated public URLs use the managed `/rentals/product-image/<filename>` route, including the application base path on mounted deployments. Legacy storage lookup remains intact.

The storage root/configuration, upload validation, random filenames, permissions and encrypted proof implementation were not changed in this pass. A read-only `bin/rentals-upload-check.php` confirmed that the local external root and product/payment/QR directories exist, are writable and have their deny guards. Actual multipart product/gallery uploads and encrypted service proof uploads passed HTTP QA. This verifies the local runtime, not the live Linux PHP worker's permissions.

The managed product-image response now uses browser-private one-day caching, ETag and Last-Modified revalidation. Unchanged requests return an empty HTTP 304 before reading image bytes. Failed/missing images remain non-cacheable. Existing lazy loading, asynchronous decoding and explicit image dimensions are preserved. Payment proofs and payment QR response caching remain unchanged; encrypted proofs are still protected and uncached.

An external folder is storage, not a microservice or CDN: the managed image route still runs through PHP on a cache miss. Caching reduces repeat image transfers and file reads; it does not establish a separate image server or prove a particular live speed improvement. Source files were not re-encoded, and no new runtime image-processing dependency was added.

## Verification

All passed:

- PHP lint: RentalsController, shared items/services views, equipment/service catalogue partials, header/footer/image partials and updated HTTP test (9 files).
- `php tests/rentals-completion-http.php http://127.0.0.1:8017` — catalogue type separation, selected choice, filter data, actions, legacy Services URL, invalid type fallback, image delivery/cache/304/404; existing upload/gallery and separate service payment/proof/ownership/CSRF/deduplication regressions. Mail disabled.
- Same HTTP suite under `http://127.0.0.1:8018/web-dev/3am-web` — mounted paths and image delivery.
- `php tests/rentals-services-http.php http://127.0.0.1:8017` — existing service CTA/login/request/history/Admin review workflow.
- `node tests/rentals-qa-ui.mjs` — equipment calendar, remaining-stock quantities and Admin type controls.
- `node tests/rentals-completion-ui.mjs` — existing gallery interactions and chart labels.
- `node --check js/rentals.js`.
- `php bin/rentals.php --check` — required columns, keys, relationships and external storage; read-only.
- Browser: 1440px desktop, 768px tablet, 390px mobile; Equipment/Services selector, service search/category filters, mobile menu, service login redirect and equipment gallery/calendar controls. No page-wide horizontal overflow at tablet/mobile widths. Service images loaded successfully.
- Equipment grid follow-up: browser geometry confirmed four cards on the first desktop row and the fifth below, two tablet columns and one mobile column, with no grid horizontal overflow. Equipment search correctly hides unmatched cards. PHP lint and `git diff --check` passed again after this layout change.
- `/` and `/information` returned HTTP 200 in both mounted/unmounted integration runs.
- `git diff --check`.

Disposable HTTP test fixtures and uploaded test files were cleaned up after visual QA. No real email was sent.

## Files touched in this incremental pass

- `app/Controllers/Rentals/RentalsController.php`
- `app/Views/rentals/items.php`
- `app/Views/rentals/services.php`
- `app/Views/rentals/partials/equipment-catalogue.php` (extracted equipment UI)
- `app/Views/rentals/partials/service-catalogue.php` (service UI)
- `app/Views/rentals/partials/header.php`
- `app/Views/rentals/partials/footer.php`
- `app/Views/rentals/partials/image.php`
- `css/rentals.css`
- `js/rentals.js`
- `tests/rentals-completion-http.php`
- This report.

Pre-existing uncommitted completion work was preserved. This incremental pass did not modify the main website/inquiry view, database schema or permanent records, `.env`, SMTP configuration, mail recipients, APP_KEY or the senior's storage architecture. No live changes, commit, push or merge were performed.
