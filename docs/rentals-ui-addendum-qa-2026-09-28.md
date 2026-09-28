# Latest Rentals UI / QA addendum — 28 September 2026

Continues the completed master pass documented in `rentals-qa-2026-09-28.md`.
Existing uncommitted work was preserved. Changes use the existing 3AM theme and
Rentals-scoped styles; Home and Information were not edited in this addendum.

## Implementation

1. Pending Admin orders show separate **Approve** and **Reject** buttons even
   without proof. Approval stays disabled until a valid manual proof exists.
2. Review is POST-only, CSRF-protected, Admin/Superadmin-only and transactional.
   Approved = 1, reviewer/time recorded, `paid_at` set. Rejected = 2,
   reviewer/time recorded, `paid_at` NULL. Rejected inventory is released;
   other Pending/Approved reservations remain. Payment Report, My Rentals and
   secure status reflect the same status. Text badges use yellow/green/red.
3. Desktop modal dimensions and image column remain fixed. Only the right
   details column scrolls. Mobile uses a fixed-height image above the details.
   The calendar uses six rows even while loading or on shorter months.
4. Opening an available item opens its calendar on the current month. Next/Prev
   move exactly one month. Reopening the picker with selected dates shows that
   selected month. Past dates cannot be selected. Request sequence guards
   discard stale month, selection and quantity results.
5. Quantity updates availability immediately while preserving month and open
   state. Valid dates remain; invalid dates clear with the exact requested
   message. Errors never replace a newer date selection.
6. Equipment list and Edit expose **Manage availability**. The calendar supports
   single-day/range selection, notes and saving blocks. Legend, day text and
   accessible labels distinguish Available, Customer Reserved and Admin Blocked.
   No customer names/emails are returned by the calendar API.
7. Reuses `rental_item_blackouts` with soft deactivate/reactivate. No schema,
   SQL definition, checker or production database changes were required.
8. Customer calendar includes active manual blocks and reservation capacity.
   Fresh requests avoid stale cached months. Direct Add to Cart and final
   Checkout still revalidate on the server.
9. Header groups coherent customer utility controls on the upper right.
10. **My Rentals** is directly accessible there and preserves guest login return.
11. **Cart** and its live count sit beside it. Main-site return is preserved.
12. Account uses a native disclosure with an anchored overlay. Signed-out links:
    Sign in/Create account. Signed-in: name/My Rentals/Account/POST Logout;
    Admin users also retain Admin access. Admin login still redirects directly.
13. Hover is a desktop convenience; normal click and keyboard toggle also work.
    Escape restores trigger focus; outside click and focus leaving close it.
    Opening the panel does not change header height. Mobile controls fit.
14. My Rentals has scan-friendly summaries and expandable item/quantity/date/
    rate/line-total details, subtotal/deposit/total and working proof actions.
    Queries remain scoped to the authenticated owner; no review controls exist.

## Verification

- PHP lint on modified/new PHP and JS syntax checks on both Rentals scripts.
- `bin/rentals.php --check`: required columns/relationships exist; existing
  nine tables reused, no migration performed.
- All six existing Rentals suites pass: unit, integration, URL, completion,
  HTTP flow and Admin QA. HTTP flow and Admin QA run at root and the simulated
  mount `/web-dev/3am-web`. No double mount in redirects or rendered links.
- New checks cover reject without proof, reviewed metadata, GET writes rejected
  with 405, invalid CSRF, guest/customer Admin-calendar denial, no calendar PII,
  manual block vs reservation stock, and updated report/customer/status badges.
- Completion suite proves a block created after Add to Cart prevents Checkout
  without clearing the cart; a direct forged Add also fails while blocked.
- 20 root/mounted page-response smoke checks pass, including Home, Information,
  Rentals Home, Equipment, Categories, Services, How to Rent, Support, Cart and
  Account. No fatal-error output or duplicated mount.
- Visual/interaction QA in available Chromium in-app browser: desktop 1440,
  tablet 768, mobile 390, plus 320px navigation/subpage checks. Measured desktop modal/image rectangles stay identical
  through calendar open/navigation/quantity/range selection. Valid dates retained;
  invalid range shows the requested message without resetting October.
- Admin calendar tested on desktop/mobile. October 15–17 block disables customer
  dates; removing it exposes remaining capacity only, preserving the underlying
  reservation and disabling quantity 2 where only one unit remains.
- Customer order details tested on desktop/tablet/mobile. Mobile table scrolls
  within its card; no horizontal page overflow. Header click/keyboard/Escape/
  outside-click and logged-in Logout verified. No JavaScript errors observed.
- Mounted Equipment modal also opens and navigates successfully using
  `/web-dev/3am-web/rentals/availability`, with no JavaScript errors.
- Firefox, Edge, Safari and the actual deployed server were unavailable here;
  their final browser/deployment QA remains manual. No cross-browser pass is claimed.
- `git diff --check` passes. Temporary QA records/scripts and owned QA servers
  are removed/stopped at completion. No `.env` edit, branch change, commit,
  push, merge, reset or production write.

## Files changed in this addendum

```text
app/Controllers/Rentals/RentalAdminController.php
app/Controllers/Rentals/RentalCartController.php
app/Models/RentalCatalog.php
app/Views/rentals/admin.php
app/Views/rentals/layouts/admin.php
app/Views/rentals/orders.php
app/Views/rentals/partials/header.php
app/Views/rentals/status.php
css/rentals-admin.css
css/rentals.css
js/rentals-admin.js (new)
js/rentals.js
routes/web.php
tests/rentals-admin-qa.php
tests/rentals-completion.php
docs/rentals.md
docs/rentals-ui-addendum-qa-2026-09-28.md (new)
```

The Git working tree also contains prior master-pass changes listed in that
earlier report. They have not been reverted or committed.
