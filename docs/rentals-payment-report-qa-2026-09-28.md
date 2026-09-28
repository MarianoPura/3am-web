# PAYMENT REPORT 500 FIX

Local investigation and safeguards, 28 September 2026. **The exact live failure
is not yet confirmed.** The live URL redirects this browser to Sign in; no
authenticated live session, live schema output or live exception log was available.
The earlier Add Products and external-storage work remains uncommitted and preserved.

1. **Exact root cause:** Not confirmed for production. The current local database
   runs the Payment Report successfully. A missing-review-column mismatch was
   reproduced precisely on connection-local temporary tables; it is a candidate
   for the live incident, not a claim about the live database.
2. **Exact failing SQL/field concept:** The report row SELECT requests
   `h.payment_reviewed_at` and `h.paid_at`; the reviewer LEFT JOIN uses
   `reviewer.id = h.payment_reviewed_by`. Removing those fields in the isolated
   test produces `SQLSTATE[42S22]` / MySQL 1054: respectively unknown column in
   `field list`, `field list`, and `on clause`. Server logs are required to match
   this reproduction to the live exception.
3. **payment_reviewed_at:** Exists locally, `TIMESTAMP NULL`; already present in
   the canonical schema and fetched `origin/development` schema.
4. **payment_reviewed_by:** Exists locally, `BIGINT UNSIGNED NULL`; already
   present in the canonical schema and fetched `origin/development` schema.
   Reviewer lookup uses the existing `users` table, not a duplicate admin table.
5. **Files changed for this follow-up:** `app/Services/RentalPaymentReportSchema.php`,
   `app/Controllers/Rentals/RentalAdminController.php`, `bin/rentals.php`,
   `tests/rentals-payment-report.php`, `tests/rentals-admin-qa.php`,
   `docs/rentals.md`, and this report. Other working-tree changes belong to the
   already-started Add Products/storage work; they were not reverted or committed.
6. **Project schema changes:** `database/rentals.sql` needed no edits. The checker
   now validates nullable timestamp/reviewer types as well as required columns.
   The explicitly local/testing migration shares those definitions and can add
   missing `paid_at`, alongside the existing missing-review-column handling.
   No persistent local schema changes were executed in this follow-up.
7. **Exact live migration:** Undetermined until inspection on the failing server.
   `php bin/rentals.php --payment-report-sql` reads that environment's schema and
   prints only missing nullable review/payment column additions. It emits no
   ALTER when columns already exist, and refuses a narrow proposal for unrelated
   missing fields or incompatible existing types. No blind live ALTER is supplied.
8. **Migration executed:** NO production migration. Test repairs ran only on
   temporary tables, dropped at connection cleanup; customer tables were preserved.
9. **Report query changes:** None required by the verified current schema. Required
   metadata and the correct totals query remain intact. The controller logs a
   Payment Report-specific failure marker and rethrows to the existing error handler.
10. **Statuses:** Pending=0, Approved=1, Rejected=2. All filters/badges pass. Real
    HTTP review tests confirm Approve sets timestamp/reviewer/paid_at; Reject sets
    timestamp/reviewer and clears paid_at. No duplicate status column was added.
11. **NULL handling:** Empty data, NULL reference/review date/reviewer/paid date/
    method, and an unavailable historical reviewer render without exceptions.
12. **Payment-method JOIN:** Existing LEFT JOIN retains unassigned and inactive
    historical methods; the method filter includes inactive methods.
13. **Calculations:** Five isolated orders with multiple detail snapshots produced
    approved rentals 345.00, deposits 9,015.00, collected 9,360.00, pending 9,315.00.
    Rejected amounts contributed zero. Header sums were not multiplied by detail
    rows, and `[TEST]` item names did not hide legitimate orders.
14. **Error handling:** SQL details go to server logs. Real HTTP QA verifies a
    generic production 500 with no SQLSTATE, exception class, filesystem path or
    stack trace, even with APP_DEBUG mistakenly enabled; security headers remain.
15. **Tests:** PASS: `bin/rentals.php --check`, `--payment-report-check`,
    `--payment-report-sql` (no local migration needed); PHP lint; `tests/rentals.php`,
    `rentals-completion.php`, `rentals-payment-report.php`, `rentals-integration.php`,
    `rentals-urls.php`; `rentals-admin-qa.php` at root and mounted URLs;
    `rentals-http-flow.php` at the root URL; JS syntax and `git diff --check`.
    The mounted Admin suite also tests the lower 2 MB upload / 3 MB POST limits.
16. **Local HTTP result:** HTTP 200 for Payment Report All/Pending/Approved/Rejected,
    inactive-method and empty filters at both root and `/web-dev/3am-web`. Browser
    navigation and all status filters were manually verified at the mounted URL.
    Screenshot: `rentals-payment-report-local.png` in the task visualization folder.
17. **Deployment:** These follow-up changes are local only. Current branch is
    `feature/website-ui-refresh`, HEAD `ca73288`; fetched `origin/development`
    is `50051dc` (merge of PR #22) and already contains the review fields/query.
    The checkout actually served by the live URL cannot be inferred from that.
    Senior must confirm the served branch/revision before deployment; no merge
    or deployment was performed here.
18. **Live database migration needed:** Unknown. Existing-table
    `CREATE TABLE IF NOT EXISTS` does not add columns. Compare the targeted
    checker output with the live `Rentals Payment Report failed` / `Database query
    failed` log entry before selecting the repair. Do not run the local `--migrate`
    command against production.
19. **.env:** Untouched. QA used process-only environment settings.
20. **Git:** No commit, push, merge, reset or branch switch. Temporary UI credentials
    and uniquely owned QA records were removed. Existing local counts after cleanup:
    users 3, categories 1, items 4, blackouts 1, carts 2, cart_items 0, methods 3,
    headers 15, details 17. Working tree remains modified for review.
