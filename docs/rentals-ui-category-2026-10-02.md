# RENTALS UI / CATEGORY FIX REPORT

## Request Received Page

- Centered a compact, responsive panel in the existing navy/yellow Rentals theme.
- Reference and actual review status sit above the QR. Status uses the existing Pending/Approved/Rejected badge.
- Removed the redundant View Order Status button. Download QR, Open status link and Return to Rentals remain.
- Successful checkout redirects to a token-protected GET receipt. Reloading reads the current database status after approval/rejection without resubmitting checkout. Unknown tokens return 404; responses are not cached.

## Category Number Badge

- Replaced the oversized numeric label with a small yellow badge, aligned at the start of each card body. Explicit intrinsic width prevents flex layouts stretching it into a strip.
- Existing category names, descriptions, counts and links are unchanged.

## Live vs Local Categories

- Public categories previously included every active category, including those with no active equipment. The user confirmed **Hide empty public categories**.
- RentalCatalog now requires at least one active non-service item through EXISTS. This affects public Home/Categories and Equipment filter options. Admin still shows all records. Services remain available through their existing query.
- Local read-only audit before temporary visual fixtures: category ID 5, `[TEST] Rental Category`, slug `test-rentals-category`, active=1, active equipment=4. The active duplicate-name query returned no rows.
- The reported live Camera / repeated Cameras & Optics cards are not verified duplicate IDs yet. Rendering iterates each returned category once; there is no name-based deduplication or CSS hiding of records. Local test data and live business records can legitimately differ.
- Existing Admin validation rejects another category with the same trimmed, case-insensitive name and excludes the current ID. The schema enforces unique slug, not unique name: pre-existing or bypassed records may still share names. No new uniqueness constraint, automatic merge or deletion was introduced.
- Tests verify that two populated categories with the same name but distinct IDs both remain visible, while empty, inactive-item-only and service-only equipment categories are excluded.

## Live DB

- Live category IDs/counts/duplicates remain **locally verified / live confirmation pending**. The live page was not available to the read-only web check; no live DB connection was used.
- Exact SELECT-only queries are in `docs/rentals-category-audit-readonly.sql`. The first two outputs establish live counts and repeated names; the third maps normalized repeated names to IDs and assigned items. Report these results before any cleanup decision.
- Destructive live DB change: **NO**. No migration is required for this pass.

## Submit Rental Request Protection

- First valid native submit immediately disables the button, shows a decorative spinner, sets form/button aria-busy and displays SUBMITTING.... Repeated submit events are prevented; the button stays disabled through navigation.
- HTTP 422 validation failures render the existing error with a fresh idle button and hidden spinner. A browser history restore resets the button while preserving any initial unavailable state. Invalid browser validation does not enter the busy state; reduced motion retains the loading label without rotation.
- Existing backend transaction locks the cart and lines with FOR UPDATE, re-reads the cart, creates the order and clears the cart atomically. A failed transaction removes its stored proof. The notification ledger retains event/state-transition uniqueness. No business-flow or schema changes were needed.
- Replayed POST integration test confirms one order and no additional retained proof or Pending ledger rows. True multi-worker simultaneous requests were not load-tested; the transaction-locking code was reviewed.

## Files Modified in This UI / Category Pass

- app/Controllers/Rentals/RentalCheckoutController.php
- app/Models/RentalCatalog.php
- app/Views/rentals/checkout.php
- app/Views/rentals/confirmation.php
- css/rentals.css
- js/rentals.js
- routes/web.php
- tests/rentals-http-flow.php
- tests/rentals-submit-ui.mjs (new)
- tests/rentals-ui-categories.php (new)
- docs/rentals-category-audit-readonly.sql (new)
- docs/rentals-ui-category-2026-10-02.md (new)

Earlier uncommitted fixed-mailer changes remain in the working tree and are documented separately in docs/rentals-mailer-2026-10-02.md.

## Tests

- PHP lint of changed/new PHP files: PASS.
- JavaScript syntax checks: rentals.js and rentals-qr.js PASS.
- rentals-submit-ui.mjs: PASS (busy state, repeated submits, native POST, validation and history recovery).
- rentals-ui-categories.php: PASS (public category rules, services retained, duplicate IDs preserved, Admin duplicate validation).
- rentals-http-flow.php using the /web-dev/3am-web mount: PASS (dated Cart/Checkout, HTTP 422 button reset, repeated POST, retained proof and Pending notification counts, current receipt badges after review, QR/status markup, protected proof, reports and proof replacement).
- rentals-admin-qa.php at the same mount: PASS (category/product CRUD/uploads, managed QR, availability, Admin auth, searches, reports, CSRF and errors).
- rentals-mailer.php: PASS (fixed recipients, actual customer, combined status transitions, duplicate/legacy delivery protection and SMTP failure isolation); fake transport only, no real email.
- rentals-mailer-http.php: PASS (removed editable mailer pages stay 404 for every role; existing main/Rentals pages and orders remain intact).
- Browser visual checks: centered receipt and category badges at desktop, 768px tablet and 390px mobile. QR renders; Open status link reaches the correct status page. Download QR control and existing Blob/SVG handler remain unchanged. The browser download-event observation timed out, so completed file download was not independently confirmed in this pass.
- Browser category link selected the matching Equipment filter and displayed exactly one fixture item, matching the card count.
- Public /, /information, /rentals, /rentals/items, /rentals/categories and /rentals/cart: HTTP 200 after fixture cleanup. Main-site views/styles have no changes in this pass.
- Final bin/rentals.php --check: PASS after fixture cleanup; required columns and relationships are compatible. All three read-only category audit queries executed locally: one populated category, no repeated names or duplicate item mappings.
- git diff --check: PASS. git status confirms the listed new UI/category files plus earlier uncommitted mailer work; .env is absent from the changes.

## Scope Confirmation

- No .env changes, live DB mutation, commit, push or merge.
- No main website view/style edits and no redesign of the separated MVC/Admin architecture.
- Temporary local test fixtures are cleaned up. Existing local business records are retained.
