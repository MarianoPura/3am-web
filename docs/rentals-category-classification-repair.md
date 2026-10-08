# Rentals category classification repair — 2026-10-08

## Confirmed SQL root cause

A guarded read-only reproduction against local d3am_rentals_new produced:
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_service' in 'where clause'

The dashboard's active-equipment count queried rental_items.is_service. The catalogue category EXISTS query and item SELECT also depended on that removed column, triggering the existing unavailable-catalogue response. Database.php correctly retained the underlying PDOException as the previous exception; its generic customer-facing error was preserved.

## Changed files and corrected references

Application:
- app/Models/RentalCatalog.php: category EXISTS classification now uses rental_categories.is_service; item SELECT exposes c.is_service AS is_service for existing consumers. Inner joins exclude missing categories; unsupported category type values are excluded.
- app/Services/RentalAdminInsights.php: active-equipment count, top-equipment analytics and type grouping use category joins/classification.
- app/Controllers/Rentals/RentalAdminController.php: product listing/type filters, edit lookups, availability/blackout eligibility and save validation use category classification. Item INSERT/UPDATE no longer include is_service; bindings match the revised column list. Posted product type cannot override the selected category. Existing products cannot move between equipment/service types. Category create/edit persists and validates its type; populated categories cannot change type.
- app/Services/RentalServiceRequests.php: service eligibility uses c.is_service.
- app/Services/RentalSchema.php: requires is_service on categories and no longer requires it on items.
- app/Views/rentals/admin/items-create.php and items-edit.php: category options provide classification; type display is derived from category.
- app/Views/rentals/admin/categories-create.php and categories-edit.php: expose category type using existing form styling.
- js/rentals-admin.js: category selection updates existing Equipment/Service controls and labels.
- bin/rentals.php: legacy local seed INSERTs no longer write the removed item column. Seed commands were not executed.

Audit documentation:
- docs/rentals-category-audit-readonly.sql
- docs/rentals.md
- docs/rentals-unified-catalogue-2026-10-07.md

Legacy test fixtures/queries updated:
- tests/rentals-admin-qa.php
- tests/rentals-completion-http.php
- tests/rentals-project-completion.php
- tests/rentals-review-regression.php
- tests/rentals-services-http.php
- tests/rentals-services.php
- tests/rentals-ui-categories.php

New regression:
- tests/rentals-category-classification.php: guarded local test with transaction rollback.

## Verified results

Local original records: 8 items, classified as 7 Equipment and 1 Service. No orphaned category relationships.
Catalogue load: 7 Equipment, 1 Service, 1 populated Equipment sidebar category, catalogUnavailable=false.
The Services view derives its own sidebar from returned service records.
Dashboard active equipment: 7; dashboard controller renders HTTP 200 and all analytics queries execute successfully.

Passed using C:/php84/php.exe:
- PHP syntax checks for every changed PHP file and the new regression.
- tests/rentals.php
- tests/rentals-integration.php
- tests/rentals-catalogue-categories.php
- tests/rentals-analytics-charts.php
- tests/rentals-urls.php
- tests/rentals-category-classification.php
- tests/rentals-review-regression.php
- bin/rentals.php --check
- git diff --check

Regression coverage includes actual product/category create and edit, forged posted type, forbidden type conversion, missing category rejection, admin search/category/type filters, public page rendering, service eligibility, Equipment calendar access, service cart rejection, checkout totals, required payment-proof validation, service request submission/review/quotation, stale authentication and quotation revision handling.

All executed fixture writes were rolled back. Counts of items, categories, users, orders, order details, carts, cart items and service requests matched their pre-test values. Transactional INSERT tests may advance local AUTO_INCREMENT counters even though fixture rows roll back.

## Limits and remaining issues

No remaining SQL failures were found in the executed checks.

Full HTTP/browser upload and checkout-completion suites were not executed. A CLI checkout attempt correctly stopped at RentalPaymentProof::store with "Choose a JPG, PNG, WebP or PDF payment proof." PHP requires a real HTTP-uploaded file via is_uploaded_file; that requirement was not bypassed. Successful proof upload and final order insertion therefore remain unverified in this run. The regression asserts the expected validation and passes.

Customer-side JavaScript search interactions and visual appearance were not browser-automated. Existing search code and styling were preserved; public catalogue data, sidebar rendering and admin search filters were checked.

The default php command resolves to PHP 8.0.30, below composer.json's PHP >=8.2 requirement. Checks used the installed PHP 8.4.25 runtime explicitly. No PHP/server configuration was changed.

No .env edits, DDL, migrations, existing-record deletion/reset, live database access, styling changes, image-storage changes or Git branch/commit/push operations were performed.