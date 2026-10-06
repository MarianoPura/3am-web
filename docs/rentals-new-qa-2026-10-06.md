# 3AM RENTALS — NEW QA FIX REPORT

Date: 2026-10-06. Branch: `feature/website-ui-refresh`.

The requested changes are implemented and tested against the current local checkout. The senior's separated views, controllers, storage, shared availability model, protected routes, mailer, and schema were retained. Changes on the senior's computer that have not been pulled are outside this verification; compare this uncommitted diff with those changes before integrating it. Nothing was deployed.

## Blocked Date Mismatch

- **Root cause reproduced locally:** the customer calendar previously rendered every `available=false` day as `is-unavailable`. This includes both manual blackouts and past dates. The supplied screenshot shows October 1–3 blocked in Admin, with October 4 also disabled in the customer calendar; a past date can account for the extra disabled day. This is a misleading state presentation, rather than a reproduced extra stored blackout date. Live database values were not read during this pass.
- **Admin stored range:** the actual Admin POST stores the submitted `start_date` and `end_date` without expansion. Tests verified `2026-10-10` through `2026-10-12` as exactly those saved bounds.
- **Customer rendered range:** October 10, 11, 12 only are Admin-blocked for that range. In the screenshot reproduction, exactly October 1, 2, 3 have the blocked class; October 4 and 5 are separately marked as past on the October 6 test date.
- **Exact off-by-one cause:** no additional-day arithmetic defect was found in the current implementation. `RentalCatalog::availabilityByDate()` compares blackouts inclusively. Its reservation end `+1 day` removes booked quantity on the next date; it does not extend a blackout. Both calendars consume the same model. The extra disabled date was previously indistinguishable from the three blocked dates.
- **Fix:** add an explicit `past` flag to the existing customer API. Render separate Admin-blocked, past, customer-reserved and generally unavailable states, with an explanatory legend, tooltip and accessible labels. Partial reservations remain selectable if sufficient quantity remains. Stored dates and booking rules are unchanged; no second availability implementation was introduced.
- **Tests:** actual Admin writes, stored bounds, Admin/customer API parity for 1/2/3 days, month/year boundaries, past dates, full unblock, partial unblock, retained Pending/Approved reservations, rejected reservations excluded, quantity-sensitive partial availability, unchanged capacity outside ranges. JS tests cover labels in Manila, UTC, Los Angeles and London. Browser modal shows exactly three blocked dates at 1440/768/390/320px.

## Status Page

- Centered the existing status panel, title, badge, text, QR and actions. Kept the panel at a compact maximum width of 42rem.
- Preserved status text, QR generation/download, protected status link and Rental Support. Added a Return to Rentals action beside Support.
- Owner/Admin/Superadmin access and other-customer 403 behavior passed unchanged. All tested widths fit the viewport.

## Responsiveness

- **Cart:** removed fixed minimum grid tracks that forced narrow date fields and summary panels to overflow. Long names, status labels and bottom actions wrap cleanly.
- **Request Summary:** amounts wrap independently, align right, and remain inside the panel; CTA can wrap within its width. Verified with a rental subtotal of PHP 9,675,926,546.00 and a total of PHP 10,117,192,174.00.
- **Checkout:** uses balanced dedicated columns on desktop and stacks below 1020px; payment account/totals and long input values stay within the panel.
- **Tablet/mobile:** checked Rentals Home, Equipment, Services, Cart, Checkout, Confirmation, Status, Add Product, equipment Edit, service Edit, and product modal. Customer pages checked at 1440/768/390/320px; Admin forms and modal at the same four widths. No horizontal page overflow remained after the narrow Home heading fix. Internal equipment/use-case carousels retain their intentional horizontal scrolling.
- **Narrow Home:** corrected the category heading flex child's minimum size and allowed long section-heading words to wrap at 320px. This is scoped to Rentals; main-site Home was not redesigned.
- Main `/` and `/information` loaded successfully. Their application views, styles, routes and scripts were not changed.

## Rental Type

- Moved the native Rental Type select to the first form group in Add/Edit Product.
- **Equipment:** retains category, name, optional slug/SKU, description, ideal use, image, quantity, availability, unit, rate, deposit, record status and existing Manage Availability on Edit.
- **Service:** retains category (required by the existing catalogue join), name/identifiers, description, ideal use, image, unit, rate and active status. Hides stock quantity, physical availability and deposit controls; no equipment calendar on service Edit.
- One canonical change handler hides and disables irrelevant inputs. Switching back restores the equipment controls without erasing typed values. The select uses native keyboard interaction.
- **Backend validation:** validates the selected type and all common fields. Equipment quantity/deposit/availability remain validated. New services ignore posted equipment-only values and use zero quantity/deposit plus the existing `inquire` status. Existing services preserve stored equipment-only values rather than overwriting them with hidden/stale input.
- Existing records retain their saved type, with a disabled type select and explanation. Forged conversion POSTs are rejected before any update. Error drafts cannot replace the actual saved type. This prevents silent conversion from destroying the meaning of existing stock/bookings.

## Quantity

- Positioned beside Rental Type before Category/Name.
- Compact yellow border emphasis and helper: “Maximum number of units that can be rented for overlapping dates.”
- Hidden and disabled for services; backend ignores submitted stock values for services while preserving existing stored values on Edit.

## Services UI

- Removed the 420px image-height floor and the layout stretch that made cards follow the large visual.
- Cards have numbered mono labels, stronger names, concise descriptions/ideal-use text, an emphasized dynamic rate/unit and a consistent Request Service CTA. Existing request destination is retained; no service details route was invented.
- Smaller section/hero headings and padding reduce blank space. The existing production visual becomes a shorter banner on tablet/mobile.
- Two cards per row on desktop/tablet; one on mobile. Card heights matched within rows in the test fixture. All content remains database-driven; no external stock imagery or hardcoded service records were added.

## Regression

- **Mailer:** fixed Owner/actual CC and dynamic saved-order customer; combined Pending/Approved/Rejected; transition and delivery deduplication; SMTP failure safety all PASS. Fake transport and loopback SMTP only, no real email.
- **QR ownership:** login/return target; owner/Admin/Superadmin; other customer 403; missing token; private/proof paths omitted all PASS.
- **Checkout:** dated cart, uploads, 422 error recovery, spinner, repeated POST/order/proof/email protection, confirmation and review PASS.
- **Availability:** exact date parity, manual blocks, unblock/reservations, quantities PASS.
- **Admin:** separated CRUD views, category/product uploads, invalid files, secure managed routes, QR replacement, validation, CSRF, auth/RBAC, safe errors PASS.
- **Reports/analytics:** Admin QA covers report filters, payment history, dashboard/analytics calculations and CSV behavior; Payment Report read-only schema/query check PASS.
- Temporary local test fixtures were removed. Existing user records were not intentionally changed. Local-only tests insert and clean their uniquely identified fixtures.

## Files Modified

All paths are beneath `C:/Users/PC-3/3am-web/`:

1. `app/Controllers/Rentals/RentalAdminController.php`
2. `app/Controllers/Rentals/RentalCartController.php`
3. `app/Views/rentals/admin/items-create.php`
4. `app/Views/rentals/admin/items-edit.php`
5. `app/Views/rentals/checkout.php`
6. `app/Views/rentals/items.php`
7. `app/Views/rentals/services.php`
8. `app/Views/rentals/status.php`
9. `css/rentals-admin.css`
10. `css/rentals.css`
11. `js/rentals-admin.js`
12. `js/rentals.js`
13. `tests/rentals-admin-qa.php`
14. `tests/rentals-qa-ui.mjs` (new)
15. `docs/rentals-new-qa-2026-10-06.md` (new report)

## Tests

Commands ran from `C:/Users/PC-3/3am-web`. HTTP test base: `http://127.0.0.1:8018/web-dev/3am-web`. The QA server used a separate external test storage root and disabled real mail through process-only settings; no `.env` edits.

| Command | Result |
| --- | --- |
| `C:/php84/php.exe tests/rentals-admin-qa.php http://127.0.0.1:8018/web-dev/3am-web` | PASS, including added type/date/quantity assertions |
| `C:/php84/php.exe tests/rentals-http-flow.php http://127.0.0.1:8018/web-dev/3am-web` | PASS |
| `C:/php84/php.exe tests/rentals-qr-ownership.php http://127.0.0.1:8018/web-dev/3am-web` | PASS |
| `C:/php84/php.exe tests/rentals-ui-categories.php http://127.0.0.1:8018/web-dev/3am-web` | PASS |
| `C:/php84/php.exe tests/rentals-mailer.php` | PASS |
| `C:/php84/php.exe tests/rentals-smtp.php` | PASS, loopback fake SMTP only |
| `node tests/rentals-submit-ui.mjs` | PASS |
| `node tests/rentals-qa-ui.mjs` | PASS |
| `C:/php84/php.exe -l` for all 9 modified PHP files | PASS |
| `node --check js/rentals.js` | PASS |
| `node --check js/rentals-admin.js` | PASS |
| `node --check tests/rentals-qa-ui.mjs` | PASS |
| `C:/php84/php.exe bin/rentals.php --payment-report-check` | PASS, schema plus SELECT; 26 rows after fixture cleanup |
| `C:/php84/php.exe bin/rentals.php --check` | FAILED: the current checkout lacks its required `database/rentals.sql` source; full schema comparison cannot run. No migration executed. This missing source existed before this pass and was not restored speculatively. |
| Browser desktop/tablet/mobile | PASS after correcting narrow Home text overflow |
| `git diff --check` | PASS |
| `git status --short`, `git branch --show-current`, `git diff --stat` | Reviewed; branch unchanged, requested local diff left uncommitted |

The broad schema-check result is a tooling/source limitation, not a claim that the functioning database needs changes. Runtime availability and Admin queries were exercised against the local database successfully. Full canonical schema verification remains pending restoration of the authoritative schema source.

## Safety Confirmations

- `.env` unchanged; secrets and SMTP credentials unchanged.
- No live database changes, no schema changes, no migration executed.
- No senior architecture redesign or second availability implementation.
- No commit, push, merge, rebase, branch switch or pull.
- No live deployment; live confirmation of these local UI changes remains pending.
