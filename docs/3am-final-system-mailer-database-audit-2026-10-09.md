# 3AM Website and Rentals final system, mailer and database audit

October 9, 2026. HOME PC only, `C:\xampp\htdocs\3am-web`, branch `feature/website-ui-refresh`.

**Assessment: REQUIRES BUSINESS CLARIFICATION.** The narrow fixes below pass local tests and are suitable for senior code review. This is not production approval. Recipient policy, generic enquiry duplicate/contact rules, Equipment rejection reasons and private runtime-file version control need senior review. No production connection, deployment, import, commit, push, merge, branch switch or Git-index change occurred. Outgoing application email remains disabled.

## Latest homepage correction and plain-language handover

Following your explicit request, **All categories** and **Browse inventory** have now been removed from the Rentals homepage. They were previously retained because they opened different pages. The main **Equipment & Services** navigation and category-card **View items** links remain available. The category and inventory pages still exist; this change only removes the two homepage section links.

The correction changes [the homepage template](../app/Views/rentals/index.php) and updates [the existing local HTTP audit](../tests/3am-inquiry-http-audit.php). Both PHP syntax checks passed. The updated audit passed **19 local HTTP assertions**, including checking the rendered links are absent, catalogue navigation remains available, enquiry validation works and enquiry records/backup remain unchanged. Existing heading layout rules were retained. The earlier full browser/mobile results below describe the preceding audit; that full matrix was not rerun for this small correction.

Your HOME database is configured for **127.0.0.1 / d3am_rentals_new** and contains the locally prepared schema and fictional data. Local tests do not establish that your HOME installation has the same data, uploaded images or settings as the office PC or LIVE system. This homepage correction made no database, mail configuration, production or Git branch changes.

Next: refresh the Rentals homepage to see the removed links, review the unresolved decisions listed at the end of this report with your senior developer, and obtain their approval for any later schema, recipient-policy or deployment changes. Keep the HOME setup files and private runtime files out of deployment.

## Investigation and implementation decisions

The starting working tree already contained 41 changed/untracked paths from HOME setup and the previous Rentals audit. Existing pagination, mobile changes, category-owned classification, separate Service workflow, protected uploads and authentication were preserved.

| Finding | Initial status and cause | Action in this pass |
| --- | --- | --- |
| SQL product pagination | VERIFIED FIXED: model/controller already filter/count/limit in SQL | Reran the 76-assertion fixture suite; no rewrite or index migration |
| Start a Project choices | VERIFIED FIXED: exactly four existing general-purpose choices | Tested every preset and radio choice; no duplicate choices added |
| Contact Rental Support | CONFIRMED BUG: prominent CTA and empty-state links still used `/rentals/support` | Changed four relevant links to mount-aware `/start?type=rentals` |
| All categories versus Browse inventory | Previously retained because their destinations differ; user explicitly requested removal in the follow-up | Removed both homepage section links; retained main catalogue navigation and category-card links |
| Enquiry reference collisions | CONFIRMED BUG: two random bytes produced 138 collisions in 4,096 draws, with no writes | Use six random bytes; existing references unchanged; existing varchar(40) fits the 21-character new code |
| Internal enquiry fallback | CONFIRMED BUG: owner alert was skipped after customer failure when owner was in CC | Allow the existing fallback destination; SMTP adapter removes To/CC duplication |
| Uncertain enquiry SMTP acceptance | Customer/CC may already have received the first message | Preserve the enquiry and log uncertainty; do not blindly send another company copy |
| Service notification detail | CONFIRMED GAP: values existed but messages omitted quote amount and explicit payment status | Added reference/service/request status/payment status/quotation/event dates and request link to existing messages |
| General confirmation wording | Production-only team/event wording did not cover technology/general projects | Use “our team” and “Project Details”; retained current confirmation subject, sender/recipient resolution and styling |
| Recipient policy | REQUIRES BUSINESS CLARIFICATION: older report disagrees with current combined delivery | User chose to leave this pending senior confirmation; no recipient/configuration changes |
| Generic enquiry replay/contact rules | PARTIALLY IMPLEMENTED: PRG and rate limit exist; payload replay is not idempotent; phone checks presence/length only | Characterized and reported; no invented repeat-submission window or national phone policy |
| Equipment rejection reason | PARTIALLY IMPLEMENTED: decision works, dedicated reason storage absent | Kept pending approved policy/schema; no new field or use of customer notes |

## A. Database verification

### Verified target and structure

The inspection checked configuration before opening the database, then verified `DATABASE()`, port and server version. Metadata and starting counts were read in `START TRANSACTION READ ONLY` and rolled back. The actual target is **127.0.0.1:3306 / d3am_rentals_new**, MariaDB **10.4.32**. No production server or shared development database was examined.

The local database contains **15 InnoDB tables, 184 columns, 58 distinct indexes including 15 primary keys and 10 other unique indexes, and 19 foreign keys**. `RentalSchema::inspect()` returned no issues. The read-only metadata inspection covered types, defaults, nullability, keys and FK update/delete rules and executed no DDL. Existing payment-report regression tests use connection-scoped TEMPORARY shadow tables; those are separate from the permanent schema and require only the local QA account's temporary-table privilege, not a new production runtime privilege.

| Table | Records already present at the start of this pass |
| --- | ---: |
| users | 2 |
| rental_categories | 2 |
| rental_items | 3 |
| payment_methods | 2 |
| carts | 2 |
| cart_items | 0 |
| rental_item_blackouts | 1 |
| order_header | 1 |
| order_details | 1 |
| rental_notification_deliveries | 1 |
| rental_service_requests | 0 |
| rental_password_resets | 0 |
| inquiries | 0 |
| landing_page_visits | 0 |
| tracking_events | 0 |

The order/detail/delivery records existed before any changes in this pass. They were retained. They differ from earlier audit checkpoints; a current count alone cannot prove who submitted or changed a record between checkpoints.

Important schema contracts checked:

- Equipment/Service type is `rental_categories.is_service`, non-null tinyint default0. `rental_items.category_id` is unsigned bigint and references category `id`. There is no `rental_items.is_service` column.
- Equipment `payment_status` is unsigned tinyint, non-null default0: Pending0, Approved1, Rejected2. Subtotal, refundable deposit and total are decimal(12,2), default0.00. `paid_at` and review timestamps are nullable. Reporting queries use these existing fields.
- Optional item slug/SKU/unit/image/gallery and category slug allow NULL. MariaDB represents the nullable JSON gallery as LONGTEXT; it is not a missing second type column.
- Slugs/SKUs, account email, order number/status token, Service reference/submission key, delivery dedup key and reset token have the required single-column unique keys. Repeated NULL optional slugs remain valid.
- Services contain quotation amount/notes/version, method/reference/proof, separate payment status/review/message and completion/cancellation fields. Customer/item/reviewer/payment relationships exist. The delivery ledger relates independently to Equipment orders and Services; nullable references and `ON DELETE SET NULL` preserve recorded delivery outcomes.
- Reset records use user_id as primary key, unique token hash and expiry index; user FK cascades. No token or key value was queried for this report.
- Corporate inquiry/visit/tracking tables exist; visit email/contact columns are present. Inquiry reference is varchar(40) with a non-unique lookup index; the new reference fits without ALTER. The generator is collision-resistant, not a database-enforced uniqueness guarantee.

### Separate previous setup from recent code changes

| Work category | Confirmed local database effects | Evidence and production effect |
| --- | --- | --- |
| Initial HOME setup | Created the 15-table current schema in the then-empty HOME database, including initial keys/indexes/FKs; inserted two fictional accounts, two categories, three offerings and two methods | Guarded HOME initializer/schema and [setup guide](HOME-PC-SETUP.md). Not an import of the senior export. Deploying application PHP does not run this initializer |
| HOME compatibility correction | Added nullable `landing_page_visits.email VARCHAR(255)` and `contact VARCHAR(20)` | Explicit `--complete-corporate` code/documentation; preserved records. These were added before the recent audits |
| Previous pagination audit | Temporary 0/1/12/13/100/1,001 product populations, category and account fixtures; rolled back/owned cleanup | No permanent column/type/index/FK changes; proposed indexes were not applied |
| Previous mobile audit | Browser fixtures, local sessions and normal login metadata for its owned accounts | No schema change; owned records/images/proofs removed |
| Earlier mailer history | Repository documents ledger/setup requirements and changes in older environments | Current HOME ledger was part of fresh setup. An older report mentioning other local counts is not proof those records existed on this HOME PC |
| This final mailer/enquiry pass | Transactional inquiry/visit/event fixtures; in-memory or loopback-captured mail; uniquely owned Rentals/Services/Admin/browser fixtures | No persistent schema change. Cleanup/rollback retained starting records. No actual external delivery |
| Other normal local use | Carts, bookings, blackouts, delivery outcomes and last-login/session metadata can change through legitimate application use | Existing records were not reset or deleted to reproduce older counts |

No column removal/type conversion, index/FK removal, table drop/reset or production data import was executed in this pass. Normal fixtures allocate auto-increment IDs even after rollback; gaps can remain. The source, documentation and saved checkpoints support the effects above. There is no complete database audit/binlog history available here, so this is not a claim to reconstruct every historical UPDATE/DELETE by every earlier process.

### Offline export and production compatibility

Only structure was read from the approved offline `three-am-dev.sql`; INSERT rows were not printed or imported. It defines 17 tables, includes 17 DROP statements and 14 INSERT statements. Its exact schema is not evidence of the current LIVE schema.

Confirmed differences from the current application/local contract:

| Offline development export | Current application/local requirement | Deployment risk if the target still matches the export |
| --- | --- | --- |
| No `rental_password_resets` | Current password recovery/reset table and constraints | Recovery/reset unavailable until a reviewed additive update exists |
| No `rental_items.additional_image_paths` | Current gallery storage/editing | Public catalogue has a NULL projection fallback; full gallery/Admin writes still need the column |
| `order_header.payment_status VARCHAR(30) DEFAULT 'pending'` | Numeric unsigned tinyint0/1/2 | Numeric casts/filter/report/review logic can misinterpret text statuses; requires an explicit reviewed data-aware conversion if present |
| Legacy mail recipient/template/subscription tables | Current code uses configuration plus delivery ledger | Legacy tables are not evidence the old editable-mail policy is active; do not delete them speculatively |

Existing repository additive documents cover Services creation, completion/gallery/reset fields and the notification ledger. They are reviewable proposals/scripts, not automatically executed migrations. Some older documents point to a removed `database/` tree and previously reported missing live QR/blackout columns; those historical reports must not be treated as a current live inspection.

**No migration is required for the new pagination or this pass's routing/message/reference changes on the verified schema.** The new reference needs at least21 characters; both the local column and approved export support40. Exact LIVE migration requirements remain unknown until the senior compares its actual structure with `RentalSchema` and the corporate fields. Do not deploy HOME fresh SQL or replay DROP/INSERT exports. No new SQL privilege, dependency or index is required by the applied fixes. Runtime schema diagnostics can generate proposed ALTER text, but do not execute it.

## B. Mailer and notification results

### Current architecture and configuration

Main enquiries use `InquiryController → InquiryStore → SmtpMailer`; Rentals events use post-commit controllers/models → `RentalNotification → RentalMailTransport → RentalSmtpTransport → SmtpMailer`. HTML is rendered by the existing escaped templates. There is no editable Admin mailer interface.

| Setting | Verified HOME value/resolution | October6 quote reference |
| --- | --- | --- |
| Application sending | **MAIL_ENABLED=false** | User supplied evidence of one external quote delivery |
| From name | 3AM Digital Media | Matches display name |
| From address | noreply@example.test | Reference used cafeotphandler@gmail.com; HOME placeholder is intentional |
| Inquiry Reply-To | local-team@example.test, from `MAIL_REPLY_TO` fallback | Reference used puramariano0206@gmail.com |
| Inquiry internal fallback destination | local-team@example.test | Does not establish approved production owner rules |
| Inquiry CC | Empty on HOME | Reference copied iannopura0206@gmail.com |
| Rentals owner/effective CC | local-team@example.test; combined configured owner/CC list | Quote screenshot does not establish Rentals/Services policy |
| Rentals/Services Reply-To | `app.contact_email`, currently local-team@example.test | Separate resolution from enquiry `MAIL_REPLY_TO`; senior should confirm intended production distinction |
| Customer destination | Enquiry submitted email; saved Equipment/Service customer email snapshot | Reference To was the submitting customer |
| Quote subject | `We received your quote inquiry — <dynamic reference>` | Same subject structure; new references have a longer random suffix |

No SMTP login, password or application/proof key is included. From is a display/sender setting and does not establish SMTP authentication identity. No production addresses were copied into HOME configuration.

Inquiry success sends one customer message with configured inquiry CC and returns. It does not automatically add `MAIL_INQUIRY_TO` as a second message/CC; that destination is the fallback on a definite customer delivery failure. The corrected fallback works even when that mailbox was in the attempted CC. Uncertain acceptance logs a recovery warning and sends no further company copy. Persistent lead capture remains successful if SMTP fails.

Current Equipment/Services delivery sends **one customer email with configured company CC**, not two separate customer/owner messages. Rental configuration merges owner and CC case-insensitively and excludes the customer mailbox from copies. The October2 report describes separate owner messages and fixed address counts; it is historical and conflicts with current implementation/tests. The October6 reference proves only the supplied quote delivery. The user explicitly chose **leave recipient policy pending senior confirmation**; no routing addresses were replaced.

### Supported event matrix

| Workflow/action | Notification and in-system result |
| --- | --- |
| Equipment checkout/new Pending order | One `rental_submitted` combined request/payment Pending receipt after commit; saved order/history/status updates |
| Equipment Pending review | Existing Pending state, not a second independent transition/email |
| Initial checkout proof | Covered by the combined submission receipt, not a duplicate independent message |
| Genuine replacement proof | `payment_proof_received`, after actual saved replacement, with its version/dedup key |
| Equipment approval/payment approval | Same current numeric status1 and `rental_approved`; no invented separate order/payment lifecycle |
| Equipment rejection/payment rejection | Same status2 and `rental_rejected`; customer history/status reflect rejection and reservation release |
| Equipment cancellation | No supported dedicated order cancellation transition/event found; not invented |
| Service submission/Pending | Saved separate Service request, history/Admin queue; initial generic inquiry confirmation attempt through `InquiryStore`, guarded by `notification_attempted_at` |
| Service approved/rejected | `service_approved` / `service_rejected`, plus saved customer message/review metadata |
| Quotation created/changed | `service_quoted`, versioned by existing quote revision; identical quote is not another revision |
| Service payment proof | `service_payment_received`; request remains separate from Equipment orders |
| Service payment approved/rejected | `service_payment_approved` / `service_payment_rejected`, matching saved payment status |
| Service completion/cancellation | Existing `service_completed` / `service_cancelled` with workflow guards; customer history updates |

“In-system” here means current records, history/status pages and customer messages/Admin queues. It does not mean a new bell/unread-notification table. The mail delivery ledger stores outcomes; it is not a customer-visible inbox.

### Payment rejection trace and evidence

Equipment Admin review validates role/CSRF/decision, locks the order, requires Pending status, changes numeric status/reviewer/time in a transaction, commits, then invokes `reviewed(REJECTED)`. The mail snapshot checks the stored status and review metadata before registering `rental_rejected`. The saved customer snapshot determines To; configured company copies determine CC. Message includes reference, Rejected request/payment status, dates, items, subtotal/deposit/total and the protected customer status link. Internal notes are not exposed. Equipment reason remains blank because its dedicated field/policy is absent.

Services review independently commits its payment decision/message, then emits `service_payment_rejected` only when the record matches rejected payment status. This pass added explicit quotation/request/payment summary to its existing email. Request status can correctly remain Approved while **payment status is Rejected**. The regression checks recipient/CC, rejected subject/body,250.75 quotation, one delivery attempt, duplicate suppression and no approval email for that rejected record. Its customer payment message remains visible in the authorized request page; no new rejection field was added.

Equipment mock tests exercise real Admin approval/rejection controllers, correct post-commit routing, duplicate review protection, proof replacement, safe escaping, legacy dedup groups, failures and uncertainty. Services mock tests cover review/quotation/payment/terminal states, rejection and failure safety. These do not prove actual external delivery.

### Delivery, retry and failure limits

- Rentals ledger registration has a unique dedup key, event/version/customer/audience identity and an atomic first-attempt claim. Repeat events do not resend. Failures do not undo bookings/reviews.
- `sent` in an in-memory test means the fake accepted the message. In SMTP operation it means acknowledged SMTP acceptance, not guaranteed inbox delivery. Captured loopback SMTP proves adapter envelope/header/body behavior only.
- Failed, uncertain or interrupted attempts are not automatically retried. There is no worker queue or delivery retry UI; CLI mailer checks schema, not delivery. Process interruption before registration cannot guarantee eventual delivery.
- Main inquiry emails have no durable mail delivery ledger/queue. Service initial notification records an attempt before inquiry capture; failure is logged and is not retried automatically. These are existing reliability limits for senior policy/design review.
- HOME sending remained disabled. No real customer, senior or production recipient was emailed. The supplied October6 Gmail reference is user evidence of that one earlier delivery, not an external test conducted here.

## C. Start a Project results

Current choices, exactly once each: **Media / Production; Technology / Event Systems; Equipment Rentals; Other / General Inquiry**. Event support fits the Media/Technology choices and freeform project details. Presets map `media`, `technology`, `rentals`, `other` to these labels. All choices are selectable; labels, required name/email/contact/details, optional company and generic requirements/location/dates wording are present.

Server checks supported type, required values, email validity/length, name/company/details limits and minimum details length. Phone currently enforces required/nonempty and maximum40 characters, rather than a strict phone-number syntax. Array input is normalized by `Request`; tests reject invalid scalar/array fields. Global CSRF rejects invalid POST with419. Honeypot yields silent non-persisting success; minimum-time check rejects fast submissions; file rate limit permits five successful submissions/IP/hour and rejects the next with429.

Persistence keeps the architecture: `inquiries` is the primary DB store; `storage/inquiries/inquiries.jsonl` is append-only backup/fallback; associated visits and Lead/Contact attribution use their existing tables. One functioning persistence path can accept the lead even if the other fails. Email failure does not claim inquiry loss. Tests use an isolated temporary backup and rolled-back real database transaction, never append to the existing backup.

Native valid submission uses Post/Redirect/Get to `/start/received`, shows and consumes a session reference. AJAX returns ok/reference. Invalid native input returns linked, escaped field errors through redirect; invalid AJAX returns422. HTTP and browser tests verify mounted URLs, all presets, no-cache, actual invalid-form errors and safe reflected input.

**Duplicate limit:** refreshing the confirmation does not resubmit. Replaying a valid POST or calling capture twice still creates two separately referenced leads; the five/hour throttle is not idempotency. A per-form idempotency design or deliberate repeated-enquiry policy needs senior confirmation; it was not silently added. The rate limiter is best-effort file based and is not an atomic distributed admission control. Reference collision resistance is a separate fix, not duplicate-submission protection.

**Privacy/version-control follow-up:** the existing JSONL is already tracked in Git and contained four nonempty records before this pass. Its contents were not displayed, copied into reports or changed by these tests. XAMPP denies direct access with403. Do not stage/commit its runtime contents. An ignore rule alone cannot untrack an existing file. Git investigation was read-only, so removing that runtime path from version control while preserving private disk data is a separate senior-authorized repository hygiene step.

## D. Contact Rental Support

Before: `/rentals/support`. Now: `url('start?type=rentals')`, yielding `/3am-web/start?type=rentals` here and the appropriate mounted/root URL elsewhere.

Updated the homepage CTA, empty category CTA, unavailable/empty equipment CTA and checkout's unavailable-payment contact link. Generic Support navigation/page remains useful and unchanged. Structured Service Request/quotation routes remain separate and unchanged. Actual HTTP and all-seven-width browser checks verify the homepage destination and Equipment Rentals preselection. Existing URL regression tests verify root/nested mount generation.

Files: [homepage](../app/Views/rentals/index.php), [categories](../app/Views/rentals/categories.php), [equipment empty state](../app/Views/rentals/partials/equipment-catalogue.php), [checkout](../app/Views/rentals/checkout.php).

## E. Homepage navigation

The two section links previously opened different destinations: **All categories** opened `/rentals/categories`, the category directory, and **Browse inventory** opened `/rentals/items`, the product catalogue. They were initially retained for that reason. Your follow-up explicitly requested their removal, so both anchors have now been removed from the homepage template.

The main **Equipment & Services** menu and category-card **View items** links remain. Routes, catalogue filtering, pagination, cards and heading styles were preserved. The final targeted HTTP audit verifies the two action labels are absent from rendered homepage anchors and the main menu still links to `/rentals/items`. PHP lint passes. No database modification or full browser-fixture rerun was needed for this correction.

## F. Pagination verification

Public Equipment/Services apply joined active-item/active-category, category-owned type, category and bound literal search predicates before COUNT and SELECT. Stable `ORDER BY name,id` and SQL LIMIT/OFFSET are authoritative. Default12/max48, malformed-input fallback and excessive-page clamping remain intact. Native GET search/category/pagination preserve filters; JavaScript manages interactions without hiding/filtering a full dataset. Admin Products retains SQL search/type/category filtering and25-row pagination with explicit projection/count. Public/Admin categories are paginated; small dashboard previews are bounded rather than blindly paginated.

Cart/checkout keyed batches remain independent of the visible page. Inactive lines can be removed, but unavailable items cannot check out. Category type stays on categories; no new type column was created.

The rerun passed **76 assertions** covering0/1/12/13/100/1,001 products, invalid page/size values, literal wildcard/injection cases, category/type intersection, stable adjacent pages, aliases, inactive cart removal, off-page checkout, Admin/category lists and rollback. On the representative1,000-product measurement: prior retrieval returned1,000 rows /443,740 encoded bytes /7.675ms; limited comparison returned12 /1,371 bytes /1.440ms. This is one warm local query, not end-to-end latency or production performance. The comparison uses four illustrative list fields versus prior all-item columns; projection and limiting both reduce payload.

EXPLAIN used category PRIMARY and `rental_items_category_index`, estimated1,001 matching item rows and filesort. LIMIT bounds returned rows; it does not guarantee scanning only12 rows. Existing indexes suffice for correctness. A category/active/name/id composite or broader active/name/id index is a measurement proposal, not executed DDL. Substring `%term%` search is not solved by an ordinary name index. No pagination migration/new privilege is required.

## G. Mobile responsiveness

Final expanded Chrome result: **350/350 layouts,40/40 interactions,4/4 protected image-proof reads**, zero failures. Required widths:320,375,390,430,768,1024,1440; viewport height900. Fifty page/state scenarios comprise18 guest,11 customer and21 Admin instances, each at seven widths. These include corporate Home, all Start presets/confirmation, both catalogues/category directory, auth/account/settings, populated cart/checkout/status/receipt, Service request/quote/payment, Admin lists/editors/proof reviews/reports and Customers.

Actual interactions cover seven sets each of enquiry choices/support destination/product calendar-gallery-quantity/availability calendar/table keyboard access and five mobile navigation checks. Main enquiry fields and radio labels fit without page-wide overflow. A390px Start screenshot was visually inspected; field layout and selectable options remained readable. The capture is ignored local test evidence, not a complete screenshot review of every page.

Prior mobile fixes were preserved: visible date selection/highlights, contained gallery, bounded modal/internal scrolling/reachable close, quantity clamping, stacked forms, named keyboard-scroll table regions and readable reports. Desktop modal880×644/image418×314 rules remain unchanged. No new application CSS/desktop redesign was needed in this pass.

The first expanded form measurement incorrectly flagged the deliberately off-screen, aria-hidden honeypot35 times although document widths matched. The harness now excludes only that intentional anti-spam element; actual document overflow checks remain. The corrected full run passed. The honeypot and its server-side rejection remain intact.

Limits: physical Android/iOS/Safari touch, on-screen keyboard, landscape heights, extreme content and full-size proof legibility/zoom still require real-device acceptance. External requests/fonts were blocked. Browser record POSTs were blocked except fixture login; invalid enquiry writes/feedback were tested separately over local HTTP. No live browser test occurred.

## H. QA and executed regression results

The supplied workbook is available as `3am_admin_testcase.xlsx` in the previously provided local directory. ZIP/XML was inspected read-only without using historical credentials: exactly39 IDs;26 historical Passed,11 Failed,1 Untested,1 blank. Its SHA256 was recorded for before/after verification. The historical filename with `(1)` is not needed because this supplied workbook has the expected39 cases.

All39 current classifications and detailed reproduction/files/evidence are in [the QA reconciliation](rentals-admin-qa-reconciliation-2026-10-09.md): **38 VERIFIED FIXED, ORD-005 PARTIALLY IMPLEMENTED**. Historical passes were not treated as current proof. The present Admin HTTP regression rechecked CRUD/images/categories/payment methods/search/status/CSRF/reports/errors; focused tests rechecked Customers/account/metadata; mail tests rechecked approval/rejection. Equipment reason remains unsupported; correct rejection/status/stock behavior passes.

Commands ran from this workspace with XAMPP PHP8.2.12 and Node24.13.0. Each database fixture suite was coordinated sequentially; independent syntax/JS checks could run during read-only browser work.

| Actual run | Result |
| --- | --- |
| PHP stdin: guarded identity + `START TRANSACTION READ ONLY`, information_schema metadata, counts, schema inspection, rollback | PASS:15 tables/184 columns/58 indexes/19 FKs; no issues |
| `php bin/home-pc-setup.php --check`; `bin/rentals.php --check`; `bin/rentals-mailer.php --check`; `bin/rentals-completion.php --check` | PASS:exact local identity/mail-disabled guard, schema/visitor columns, relationships/ledger, external storage checked with scoped local access; no changes |
| `php -d session.save_path=... tests/3am-inquiry-audit.php` | PASS:4,155 assertions including4,096 reference-format samples plus persistence/validation/spam/CSRF/rate-limit/captured SMTP/fallback/failure/uncertainty checks; all baseline table counts and original backup preserved |
| `php -d session.save_path=... tests/3am-inquiry-http-audit.php` | PASS:18 assertions in the initial audit; final homepage correction rerun PASS:19 assertions, adding rendered action removal and preserved main catalogue navigation; mounted presets/support destinations, CSRF419, invalid form feedback/escaping, legacy redirects, backup403; enquiry counts/backup unchanged and no valid HTTP enquiry submitted |
| Final homepage correction: PHP lint for `app/Views/rentals/index.php` and `tests/3am-inquiry-http-audit.php` | PASS: both files have no syntax errors |
| Existing standard PHP suites | PASS:19/19;17 normal suites plus mailer/proof-view suites with required external temporary storage |
| `php tests/rentals-catalogue-pagination.php` | PASS:76 assertions, fixture populations/query plans and rollback |
| `php tests/rentals-senior-view-qa.php --database` | PASS:60 assertions; fictional directory accounts rolled back |
| Existing Node real-handler suites | PASS:6/6 calendar/completion/services/product-types/submit/QA suites |
| Guarded browser fixture `--prepare`; `node tests/rentals-browser-audit.mjs --fixtures --capture`; fixture `--cleanup` in finally | PASS:350 layouts/40 interactions/4 proofs; all owned rows/files/private state removed; saved15-table counts restored |
| `php tests/rentals-smtp.php` | PASS:loopback adapter envelopes/headers/CC, success/QUIT failure/uncertain acceptance/explicit rejection; no external email |
| Hidden PHP QA router on127.0.0.1:8012, mount `/3am-web`; `rentals-mailer-http`, `rentals-admin-qa`, `rentals-http-flow` | PASS:3/3 HTTP suites; temporary server stopped in finally |
| PHP `-l` over `rg --files app config routes tests bin -g '*.php'` | Final194/194 passed |
| Node `--check tests/rentals-browser-audit.mjs`; `git diff --check` | PASS |

The19 standard PHP suites: rentals, rentals-urls, rentals-integration, rentals-completion, rentals-category-classification, rentals-catalogue-categories, rentals-services, rentals-services-availability, rentals-service-validation, rentals-review-regression, rentals-system-audit, rentals-payment-report, rentals-analytics-charts, rentals-project-completion, rentals-latest-qa, rentals-live-regression, rentals-ui-categories, rentals-mailer, rentals-proof-view-qa. “live” in the regression filename is a guarded local test, not production access. The six JS suites: rentals-calendar-selection, rentals-completion-ui, rentals-services-ui, rentals-product-types-ui, rentals-submit-ui, rentals-qa-ui.

Service completion regression was extended with rejected payment recipient/content/amount/status, dedup and wrong-approval suppression. Existing PHP/HTTP suites exercise same-day inclusive dates, overlap/blackouts/stock, ownership/CSRF/SQL binding/XSS/uploads/encrypted proofs, reports excluding refundable deposits and safe errors. Previous concurrency24 assertions are historical evidence from the earlier audit; that stress test was not rerun for message-only/routing changes. No claim covers every possible traffic schedule.

Resolved test attempts: the first enquiry escaping assertion incorrectly treated plain-text content as HTML; the HTML part is now independently checked. The new Service rejection assertion exposed a real missing message summary, which was fixed. The fake uncertain SMTP listener emitted an expected timeout warning into its JSON stream; only that fixture timeout is suppressed. Sandbox could not access protected browser upload directories, so the authorized guarded local run used scoped elevation. The sandboxed core diagnostic also emitted external-directory writability warnings; the same read-only diagnostic with scoped local access confirmed existing storage. An initial hidden QA-server launch used the same NUL redirect for both streams and failed before the server started; single stderr redirection corrected it. None of these failures justified weakening production validation/storage/security.

### Final preservation check

The final guarded read-only comparison returned **schema metadata unchanged** for structural table engine/collation metadata, 184 column definitions/defaults/nullability, 58 indexes and 19 foreign keys, and **all 15 starting table counts restored**. Auto-increment counters can advance during fixtures; this is not a schema migration. Private browser fixture account/state files were absent. The original inquiry backup was hash-checked unchanged by both enquiry test suites, and the workbook hash was unchanged after inspection. Temporary QA server was stopped; no test credentials were retained in the new report. Runtime sessions/logs may remain as normal private local artifacts.

The one pre-existing delivery record is `rental_submitted`, failed with `smtp_not_configured`, attempts1. That is consistent with intentionally disabled HOME email; it was preserved and not retried. A saved local order does not imply an external email was delivered.

### All 39 historical cases: current index

Full reproduction, expected/actual results, root causes, files and evidence remain in the linked reconciliation. The index preserves every case here.

| Case | Historical | Current |
| --- | --- | --- |
| TC-ADM-AUTH-001 — Admin login | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-002 — Guest Admin access | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-003 — Customer denied Admin | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-004 — Sign out | PASSED | VERIFIED FIXED |
| TC-ADM-AUTH-005 — Back to Rentals | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-001 — Dashboard KPIs | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-002 — Recent/upcoming panels | PASSED | VERIFIED FIXED |
| TC-ADM-DASH-003 — Dashboard shortcuts | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-001 — Analytics presets | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-002 — Custom date validation | PASSED | VERIFIED FIXED |
| TC-ADM-ANL-003 — Analytics panels/KPIs | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-001 — Orders columns | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-002 — Order search | FAILED | VERIFIED FIXED |
| TC-ADM-ORD-003 — Payment-status filter | PASSED | VERIFIED FIXED |
| TC-ADM-ORD-004 — Proof review/approval | FAILED | VERIFIED FIXED |
| TC-ADM-ORD-005 — Rejection with reason | FAILED | PARTIALLY IMPLEMENTED |
| TC-ADM-ITM-001 — Products list | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-002 — Product filters | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-003 — Add Product fields | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-004 — Product slug | PASSED | VERIFIED FIXED |
| TC-ADM-ITM-005 — Create Equipment with image | FAILED | VERIFIED FIXED |
| TC-ADM-ITM-006 — Edit Equipment | FAILED | VERIFIED FIXED |
| TC-ADM-ITM-007 — Deactivate Equipment | FAILED | VERIFIED FIXED |
| TC-ADM-CAT-001 — Categories list/add link | PASSED | VERIFIED FIXED |
| TC-ADM-CAT-002 — Add Category fields/slug | PASSED | VERIFIED FIXED |
| TC-ADM-CAT-003 — Category propagation | UNTESTED | VERIFIED FIXED |
| TC-ADM-SLS-001 — Sales filters/summary | PASSED | VERIFIED FIXED |
| TC-ADM-SLS-002 — Exclude refundable deposits | FAILED | VERIFIED FIXED |
| TC-ADM-PAY-REP-001 — Payment Report schema | FAILED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-001 — Payment Methods list | PASSED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-002 — Create payment method/QR | PASSED | VERIFIED FIXED |
| TC-ADM-PAY-CFG-003 — Inactive methods hidden | FAILED | VERIFIED FIXED |
| TC-ADM-CUST-001 — Read-only directory | PASSED | VERIFIED FIXED |
| TC-ADM-CUST-002 — Sensitive fields excluded | PASSED | VERIFIED FIXED |
| TC-ADM-SEC-001 — Admin noindex | PASSED | VERIFIED FIXED |
| TC-ADM-SEC-002 — Admin CSRF | FAILED | VERIFIED FIXED |
| TC-ADM-SEC-003 — Invalid upload rejection | BLANK | VERIFIED FIXED |
| TC-ADM-SEC-004 — Production-safe errors | FAILED | VERIFIED FIXED |
| TC-ADM-RSP-001 — Admin responsiveness | PASSED | VERIFIED FIXED |


## I. Git safety and changed files

All changes remain uncommitted on the existing branch; no fetch/commit/push/merge/switch/staging/index mutation was performed. The earlier audit's read-only GitHub comparison matched this branch at its check time; no claim is made about other branches or a new remote-tip check in this pass.

New application changes this pass: InquiryStore collision-resistant references/fallback/uncertainty/general text; Service notification summary; inquiry HTML wording; four enquiry links; removal of the two homepage section actions following your latest request. Modified tests: browser scope/honeypot measurement/Start interactions, Service payment-rejection assertions and updated homepage HTTP navigation assertions. New tests: the two 3am enquiry audit files. New documentation: this report, including the final homepage correction; two generated Markdown headings were corrected in the earlier audit report without changing its results. Existing pagination/mobile/Admin changes were retained.

Complete final inventory is appended below. HOME initializer, reconstructed fresh SQL and setup guide are not deployment files and must not be staged wholesale. Private `.env`, account/state files, keys, sessions, logs, captures/profiles/metrics stay private/ignored. The tracked JSONL exception described above needs explicit senior review; it must not be included with runtime data in a future commit. No private values were printed.

### Complete final working-tree inventory

49 changed/untracked paths, all uncommitted. `M` means tracked modification; `??` means untracked. Existing changes are included, not attributed wholesale to this pass.

#### Application code for senior review

| Status | Path |
| --- | --- |
| `M` | [app/Controllers/Rentals/RentalAdminController.php](../app/Controllers/Rentals/RentalAdminController.php) |
| `M` | [app/Controllers/Rentals/RentalsController.php](../app/Controllers/Rentals/RentalsController.php) |
| `M` | [app/Core/Response.php](../app/Core/Response.php) |
| `M` | [app/Models/RentalCatalog.php](../app/Models/RentalCatalog.php) |
| `M` | [app/Services/InquiryStore.php](../app/Services/InquiryStore.php) |
| `M` | [app/Services/RentalCart.php](../app/Services/RentalCart.php) |
| `M` | [app/Services/RentalCheckout.php](../app/Services/RentalCheckout.php) |
| `M` | [app/Services/RentalNotification.php](../app/Services/RentalNotification.php) |
| `M` | [app/Services/RentalPagination.php](../app/Services/RentalPagination.php) |
| `M` | [app/Views/emails/inquiry-confirmation.php](../app/Views/emails/inquiry-confirmation.php) |
| `M` | [app/Views/rentals/account.php](../app/Views/rentals/account.php) |
| `M` | [app/Views/rentals/admin/categories.php](../app/Views/rentals/admin/categories.php) |
| `M` | [app/Views/rentals/admin/customers.php](../app/Views/rentals/admin/customers.php) |
| `M` | [app/Views/rentals/admin/items.php](../app/Views/rentals/admin/items.php) |
| `M` | [app/Views/rentals/admin/orders.php](../app/Views/rentals/admin/orders.php) |
| `M` | [app/Views/rentals/admin/service-requests.php](../app/Views/rentals/admin/service-requests.php) |
| `M` | [app/Views/rentals/categories.php](../app/Views/rentals/categories.php) |
| `M` | [app/Views/rentals/checkout.php](../app/Views/rentals/checkout.php) |
| `M` | [app/Views/rentals/index.php](../app/Views/rentals/index.php) |
| `M` | [app/Views/rentals/items.php](../app/Views/rentals/items.php) |
| `M` | [app/Views/rentals/orders.php](../app/Views/rentals/orders.php) |
| `M` | [app/Views/rentals/partials/admin-dashboard.php](../app/Views/rentals/partials/admin-dashboard.php) |
| `M` | [app/Views/rentals/partials/catalogue-categories.php](../app/Views/rentals/partials/catalogue-categories.php) |
| `M` | [app/Views/rentals/partials/equipment-catalogue.php](../app/Views/rentals/partials/equipment-catalogue.php) |
| `M` | [app/Views/rentals/partials/service-catalogue.php](../app/Views/rentals/partials/service-catalogue.php) |
| `M` | [app/Views/rentals/service-requests.php](../app/Views/rentals/service-requests.php) |
| `M` | [css/rentals-admin.css](../css/rentals-admin.css) |
| `M` | [css/rentals.css](../css/rentals.css) |
| `M` | [js/rentals-admin.js](../js/rentals-admin.js) |
| `M` | [js/rentals.js](../js/rentals.js) |

#### Tests for local execution/code review

| Status | Path |
| --- | --- |
| `M` | [tests/rentals-admin-qa.php](../tests/rentals-admin-qa.php) |
| `M` | [tests/rentals-completion-ui.mjs](../tests/rentals-completion-ui.mjs) |
| `M` | [tests/rentals-project-completion.php](../tests/rentals-project-completion.php) |
| `M` | [tests/rentals.php](../tests/rentals.php) |
| `??` | [tests/3am-inquiry-audit.php](../tests/3am-inquiry-audit.php) |
| `??` | [tests/3am-inquiry-http-audit.php](../tests/3am-inquiry-http-audit.php) |
| `??` | [tests/rentals-browser-audit.mjs](../tests/rentals-browser-audit.mjs) |
| `??` | [tests/rentals-browser-fixtures.php](../tests/rentals-browser-fixtures.php) |
| `??` | [tests/rentals-catalogue-pagination.php](../tests/rentals-catalogue-pagination.php) |
| `??` | [tests/rentals-concurrency-qa.php](../tests/rentals-concurrency-qa.php) |
| `??` | [tests/rentals-senior-view-qa.php](../tests/rentals-senior-view-qa.php) |

#### Audit documentation

| Status | Path |
| --- | --- |
| `??` | [docs/3am-final-system-mailer-database-audit-2026-10-09.md](../docs/3am-final-system-mailer-database-audit-2026-10-09.md) |
| `??` | [docs/rentals-admin-qa-reconciliation-2026-10-09.md](../docs/rentals-admin-qa-reconciliation-2026-10-09.md) |
| `??` | [docs/rentals-mobile-audit-2026-10-09.md](../docs/rentals-mobile-audit-2026-10-09.md) |
| `??` | [docs/rentals-security-compatibility-2026-10-09.md](../docs/rentals-security-compatibility-2026-10-09.md) |
| `??` | [docs/rentals-senior-system-audit-2026-10-09.md](../docs/rentals-senior-system-audit-2026-10-09.md) |

#### HOME-only: exclude from commits/deployment

| Status | Path |
| --- | --- |
| `??` | [bin/home-pc-setup.php](../bin/home-pc-setup.php) |
| `??` | [docs/HOME-PC-SETUP.md](../docs/HOME-PC-SETUP.md) |
| `??` | [docs/home-pc-fresh-schema.sql](../docs/home-pc-fresh-schema.sql) |



## J. Final readiness and senior next steps

**REQUIRES BUSINESS CLARIFICATION**, with tested portable fixes ready for review. No statement of production readiness or actual external mail delivery is made.

1. Review only the application/test/document changes; exclude HOME setup SQL/scripts and private runtime data. Address tracked inquiry-backup version control while preserving private working data; current Git index was intentionally left unchanged.
2. Confirm current recipients and whether Rentals/Services should use one customer email with company CC or separate owner notifications. Confirm Rentals Reply-To versus enquiry Reply-To. Keep the October6 screenshot scoped to that quote event.
3. Define generic enquiry replay/idempotency and contact-format requirements, plus Equipment rejection-reason requirements, before any related workflow/schema change. No new reason field is authorized here.
4. On an authorized staging copy, compare actual schema/defaults/keys/FKs to the current contract and confirm inquiry reference length≥21. Apply only reviewed, necessary additive/data-aware migrations; never the HOME fresh initializer or export DROP/INSERT statements.
5. Confirm production PHP/extensions, MariaDB/SQL compatibility, root/nested routing, Linux paths, protected external upload permissions, stable existing proof/application keys, HTTPS/session settings and current SMTP/provider configuration without copying HOME credentials.
6. Complete real-device/large-proof review and an authorized staging mail acceptance/delivery test. Provider logs are needed for uncertain delivery; do not blindly retry it.

The main-site storage architecture, senior category type, Equipment financial policy, Service separation and fixed backend-configured recipients were preserved. The LIVE server was not accessed or changed.
