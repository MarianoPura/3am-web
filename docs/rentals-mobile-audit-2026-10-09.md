# Rentals mobile audit — October 9, 2026

## Scope and evidence limits

This review covers the current local PHP templates, customer/Admin JavaScript, and the two Rentals stylesheets. It did not contact production, change environment settings, submit record forms, or alter database structures. Existing uncommitted Home PC setup changes were preserved. The root audit prepared temporary fictional customer/Admin accounts and related local records for populated-page checks; browser login performed normal session/last-login updates. Fixture cleanup completed, restoring the recorded baseline counts for all 15 checked tables and removing the fixture-owned files. Existing local cart and blackout records were retained.

The integrated browser surfaces were unavailable. The installed Chrome executable was subsequently tested through a local headless CDP harness, with a temporary isolated profile and network requests restricted to the local application. External font requests were blocked, so measurements use fallback/local fonts. Raw HTML, account details, credentials and tokens are not logged.

Required viewports **320, 375, 390, 430, 768, 1024, and 1440 pixels** all passed the measured rendered layout checks. The final expanded run returned **301/301 page/layout checks across 43 scenarios, 26/26 interaction checks, and 4/4 authenticated protected-proof reads passing, with zero failures**. A separate focused rerun at 390/1440px returned **86/86 layouts, 7/7 interactions, and 4/4 proof reads passing**. These results include populated checkout, Equipment order and Service quotation/payment/review pages. They do not prove physical touch-device or on-screen keyboard behavior.

The root audit visually inspected three final viewport screenshots: Equipment modal at 390px and 1440px, and Admin Products at 390px. This is visual evidence for those three states; it is not a screenshot review of all 43 scenarios.

## Baseline already implemented

- Customer navigation has a mobile toggle, `aria-expanded`, Escape handling, outside-click closure, and an account menu that returns focus on Escape.
- Equipment detail uses native `<dialog>`, a stacked mobile layout, contained product images, gallery arrows/keyboard/swipe handlers, and focus restoration when closing.
- The rental calendar remains visible after a completed date range. Its date logic preserves selected highlights, rejects unavailable dates within a range, and ignores stale rapid-click responses.
- Cart and checkout stack their columns at narrow widths and make the summary non-sticky, avoiding an overlay on form actions.
- Services use separate request/quotation/payment flows and stack their summary and forms on mobile.
- Customer order and Admin record tables already have independent horizontal scroll wrappers; their table columns do not need to be crushed into unreadable cards.
- Admin navigation, metrics, filters, editing forms, proof actions, and availability controls already contain responsive breakpoints.

## Confirmed source defects and applied CSS changes

| Finding | Root cause/evidence | Change | Validation limit |
| --- | --- | --- | --- |
| Mobile product date fields create an implicit second grid column | At `max-width:560px`, `.rentals-detail__dates` becomes one column while its date selector retains `grid-column:span 2` | Set the selector to `grid-column:1 / -1` in that breakpoint | PASS: one rendered column at 320/375/390/430px, no modal horizontal overflow |
| Product close, month controls and day rows are only 32 pixels high | Existing dimensions are `2rem`; close button is also absolutely positioned at the top of the scrollable dialog | Mobile close/month/gallery controls use 44px; day rows are 44px high; close stays sticky while the dialog scrolls | PASS: close remains inside the dialog after scrolling; seven calendar columns necessarily have narrower widths on 320px screens; physical touch pending |
| Mobile modal gives little usable space and can lose its close action while scrolling | Uses `85dvh` and a fixed top close position | Up to 780px, use a 94dvh dialog, explicit viewport max width, one scrolling surface, scroll padding, and a sticky close; reduce image/body padding below 400px; make Add to Cart full width | PASS: all seven widths; unchanged desktop dimension rules yield 880×644px modal and 418×314px visual at 1024/1440px |
| Admin report controls can exceed the content width near the old 900px breakpoint | Six-column report form combines two 130px and three 150px minimum columns, action width, gaps and padding | Collapse report form to three `minmax(0,1fr)` columns by 1180px; retain existing smaller breakpoints | PASS: report pages have no uncontained horizontal overflow at all seven widths |
| Categories hero overflows at 320px | The added category-index browser check measured 324px scroll width against a 305px content viewport; hero grid children retained their intrinsic minimum width and the heading could not wrap | Below 560px, apply `min-width:0` to `.rentals-page-hero__inner > *` and `overflow-wrap:anywhere` to the hero heading | PASS: category-index layout at all seven widths after the root audit's scoped CSS fix |
| Long Admin display names can stretch the mobile header | Flex account area has automatic intrinsic minimum width | Allow account span to shrink/wrap; preserve brand width | Source verified; long-name visual fixture pending |
| Narrow report metadata and order references can force content outside panels | Some Admin detail/ranking/chart controls have unbounded text/intrinsic widths | Allow wrapping of order metadata, report names, chart labels/selects, proof links and action labels | PASS: populated Equipment/Service detail and proof-review pages at all seven widths; extreme long-value fixtures remain pending |
| Focus and touch usability is incomplete on existing controls | Select/textarea/summary lack the Rentals-specific focus rule; some navigation and clear actions are under 44px | Add visible focus rules, mobile 44px navigation/clear controls, a bounded scrollable open mobile menu, and contained table overscroll | PASS: native Escape/focus restoration, mobile menu closure, and ArrowRight table scrolling; physical touch pending |
| Small mobile form text can trigger mobile browser auto-zoom | Existing form labels inherit fonts below 16px | Set mobile customer/Admin form controls to 1rem; provide scroll margin beneath the customer sticky header; QR dialog scrolls within the dynamic viewport | Physical keyboard/zoom verification pending |
| Password recovery/reset labels and inputs have no field layout | Password template uses plain inline labels, outside the styled account field wrappers; label text plus default-width input can exceed a narrow panel | Scope a stacked label/input form layout and bounded full-width controls to the password panel | PASS: forgot-password form layout at all widths; valid-token reset form remains source-reviewed |

The new read-only account details use a branded two-column definition list with long-value wrapping and one column below 400px. Existing Admin search/role filters use the existing responsive filter class.

The backend catalogue search form now has a wrapping search/select/submit layout, then a single column below 560px. Its page-size selector and Search button are 52px high, category filters keep their existing visual appearance when rendered as links, and narrow pagination actions can wrap instead of exceeding their container.

The mobile selection improvement retains 3AM typography/colors and emphasizes a contained image gallery, readable product facts, dates followed by quantity, a persistent visible calendar, and a full-width Add to Cart action. It uses familiar shopping-app usability patterns without copying another company's branding or adding a new interaction framework. Equipment and Service request logic was not modified in this CSS phase.

## Page review matrix

| Area | Templates/source reviewed | Relevant layout coverage | Visual/browser status |
| --- | --- | --- | --- |
| Home, header, navigation, account menu, footer | `index.php`, `partials/header.php`, `partials/footer.php`, header/carousel JS | Existing responsive grids and mobile menu retained; touch targets/menu bounds improved | PASS: layout at all widths; mobile menu/native Escape checks |
| Equipment/Services catalogues and search/category controls | `items.php`, equipment/service/category partials | Existing grids preserved; small category list stacks below 400px; narrow hero heading wraps | PASS: catalogue/search and separate category-index layouts at all seven widths |
| Product selection, gallery, rental calendar, quantity | Equipment catalogue dialog and modal/gallery/calendar JS | Implicit date column fixed; dynamic dialog height, sticky close, touch targets, full-width cart action | PASS: two-image gallery arrows, no modal overflow, contained image, calendar/highlights persist, quantity clamps, Escape restores opener at all seven widths |
| Cart, checkout, proof upload | `cart.php`, `checkout.php`, order/proof JS | Existing one-column cart/checkout and static summaries retained; mobile form font and scroll margin improved | PASS: populated cart and actual checkout/proof-upload form at all seven widths; form was not submitted |
| Login, registration, account, settings, password recovery | Account/settings/password templates and common field styles | Existing auth panel retained; small padding, readable field font and account facts wrapping | PASS: seven-width layouts and authorized local login; password form submissions not performed |
| My Rentals/order history and order status | `orders.php`, confirmation/status templates | Existing scroll-contained table retained; narrow heading wraps; focus outline supports root's scroll-region accessibility helper | PASS: populated order history, private owner-only status and receipt pages at all seven widths; customer Equipment proof GET passed |
| My Service Requests, service form and quotation workflow | Service request/history/status templates and JS | Existing stacked form/workflow retained; form font/focus improved | PASS: request form, populated history, approved quotation/payment form and pending-proof view at all seven widths; customer Service proof loaded |
| How to Rent and Support | `how-to-rent.php`, `support.php`, shared step/support/CTA styles | Existing responsive step grids, wrapping and CTA stack retained | PASS: layout at all widths |
| Branded error states | Existing error/layout source and common panel rules | No redesign; existing error routes unchanged | Source reviewed; deliberately triggered error pages were outside the rendered browser scenarios |
| Admin Dashboard and Analytics | Dashboard/analytics/time-chart partials | Existing responsive metrics preserved; report filter/chart controls and long text improved | PASS: dashboard and expected Analytics→dashboard redirect; chart handler tests pass |
| Admin Orders and Service Requests | List/detail/review templates | Existing contained tables/proof actions preserved; wrap order details and proof links | PASS: populated Equipment order and Service proof-review forms at all seven widths; both protected proof images loaded |
| Admin Products, Add/Edit Products and availability | Product templates, gallery/admin calendar JS | Existing responsive editors preserved; controls readable on mobile | PASS: list/add/Service edit and actual Equipment edit layouts; seven-column availability calendar and keyboard table scrolling at all seven widths |
| Admin Categories and Payment Methods | List/editor templates | Existing filters/forms/tables preserved | PASS: list/add/edit layouts at all widths |
| Admin Sales and Payment Reports | Report templates | Report filter columns collapse earlier; tables stay independently scrollable | PASS: current filter/table/empty-state layouts at all widths |
| Admin Customers/account directory and navigation | Customers/Admin header templates | Existing filter styles applied to role/search; long display name wraps | PASS: layout at all widths; extreme-length name fixture not used |

Table accessibility requires the scroll wrapper to be keyboard focusable and named. The root audit implemented this for `.rentals-order__table-wrap` and `.rentals-admin__table-wrap`; CSS includes a focus outline and keeps native table semantics intact. In Chrome, the Admin Products table showed a 2px focus outline at every width; native ArrowRight input scrolled its overflowing region at 320/375/390/430/768px. At 1024/1440px the table fit without horizontal scrolling.

## Rendered browser evidence

The 43 scenarios comprise **11 guest, 11 customer, and 21 Admin page/state instances**, each checked at all seven widths with a 900px viewport height. Guest scenarios include Home, both catalogue types, Services, Categories, account/login/register/recovery, How to Rent, Support and Cart. Customer scenarios include account/settings, order and Service history, populated Cart/Checkout, private order status/receipt, Service request, approved quote/payment, and pending-proof views. Admin scenarios include Dashboard, both order types and proof reviews, Products and both product types' editors, Categories/Payments list/add/edit, Customers, Sales/Payment reports and Analytics.

Analytics currently redirects to the Dashboard; the harness recorded the local final route rather than claiming a separate Analytics page rendered. Earlier empty-checkout redirects were replaced with an asserted populated checkout form in the expanded fixture run. The harness also asserted the Service payment form and both Admin review forms, so a redirect or empty state could not silently count as those pages.

| Check | Final evidence |
| --- | --- |
| Document/page overflow | 301/301 layouts passed document `scrollWidth`/`clientWidth` and visible element bounds; content inside intentional table/carousel scroll regions was handled separately |
| Equipment modal | 7/7 widths passed contained image layout, available quantity clamp, persistent selected calendar highlights, gallery arrows and native Escape/focus restoration |
| Mobile navigation | 5/5 mobile widths passed toggle, native Escape closure and focus restoration |
| Admin availability calendar | 7/7 widths passed seven columns, rendered day buttons and bounded horizontal dimensions |
| Keyboard table region | 7/7 widths passed focus/outline and native ArrowRight scrolling where the table overflowed |
| Protected proofs | 4/4 customer/Admin Equipment/Service proof GETs returned nonempty image data successfully; expanded review pages also verified loaded inline proof images |
| Desktop dimensions | At 1024/1440px, the Equipment modal remained 880×644px and its contained image visual remained 418×314px |
| Focused rerun | 86/86 layouts, 7/7 interactions and 4/4 proof reads passed at 390/1440px after correcting the screenshot capture method |

The inspected 390px Equipment screenshot shows a contained image with its aspect ratio preserved, accessible gallery arrows/counter, clean wrapping of price/deposit/stock and date/quantity fields, and a visible close control. Internal dialog scrolling is available; the lower Add to Cart action is not claimed to be in the initial viewport. The 1440px screenshot retains the two-column desktop layout and the dimensions above. The 390px Admin Products screenshot shows the header/navigation and search/type/category controls wrapping or stacking without page overflow. These fictional-fixture screenshots remain in ignored `storage/tmp/` and are not committed.

The browser run blocked 331 external requests. It did not retrieve production assets or external fonts. Browser record mutations and uploads were blocked; only authorized local login POSTs were allowed. A screenshot capture setting initially altered the captured viewport and was corrected in the test harness; the focused rerun passed without requiring an application fix.

## Regression checks actually executed

All commands below completed with exit code0:

```text
node tests/rentals-calendar-selection.mjs
node tests/rentals-qa-ui.mjs
node tests/rentals-services-ui.mjs
node tests/rentals-product-types-ui.mjs
node tests/rentals-completion-ui.mjs
node tests/rentals-browser-audit.mjs --fixtures --capture
node tests/rentals-browser-audit.mjs --fixtures --capture --focused
git diff --check -- css/rentals.css css/rentals-admin.css
```

**5 JavaScript suites passed.** They verify calendar persistence, same-day/leap/month/year dates, blocked interior ranges, stale request handling, stock/quantity bounds, cart synchronization, Service loading/double-submit/browser-history recovery, product category/type controls, gallery arrows/wraparound/keyboard/swipe/vertical-scroll/pointer cancellation, chart values, and existing category/search interaction.

The five JavaScript suites execute actual handlers against controlled test DOM doubles. They do not render CSS. They were rerun successfully after the backend catalogue integration and scroll-region helper were present. The separate installed-Chrome harness provides the rendered CSS and native keyboard evidence above. The root audit executed the browser runs and fixture prepare/cleanup; this CSS audit did not independently create or modify database records. Repeating `--fixtures` requires preparing new disposable fixture state because the audited fixture records and private credentials were cleaned up.

## Files changed in this CSS phase

- [css/rentals.css](../css/rentals.css): scoped customer mobile dialog/calendar/navigation/form/focus rules, account facts and the Categories hero correction.
- [css/rentals-admin.css](../css/rentals-admin.css): report breakpoint, header/text wrapping, focus/touch/form rules.
- [tests/rentals-browser-audit.mjs](../tests/rentals-browser-audit.mjs): isolated local Chrome/CDP measurements, permitted login, mutation/external-request blocking and safe result/screenshot capture.
- This document.

## Remaining acceptance checks

Physical Android/iOS touch gestures, Safari behavior, on-screen keyboard/auto-zoom and short landscape heights still require device testing. Extreme long-name/description fixtures, valid-token password reset, deliberately triggered branded errors and full-size proof zoom were not included in this rendered run. Successful/failed record submissions, actual upload interaction and slow-network recovery are outside this read-only browser phase; relevant handler/HTTP tests should be assessed separately from visual layout evidence.

The required widths passed the measured local scope. Production deployment and production browser behavior were not tested or changed. The three screenshot reviews do not imply that every page/state received a visual inspection.
