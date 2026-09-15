# 3AM Media, Technology and Ventures Inc. — Website

Corporate website for 3AM Digital Media. Server-rendered PHP, no framework.

**Plan:** `C:\Users\SDCG\.claude\plans\project-3am-media-robust-leaf.md`

---

## ⚠ Read this before writing any code

### 1. `.htaccess` is the only thing protecting the source

The application is installed inside the document root so it can serve at
`/web` without vhost changes. That means `app/`, `config/`, `routes/`,
`storage/` and `.env` are all addressable by URL, and are blocked only by the
deny rules in `./.htaccess` plus a per-directory `.htaccess` in each.

If `AllowOverride` is ever `None` for `/var/www/html`, none of it applies and
`/web/.env` serves your database password as plain text.

**After any server change, re-run:**

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://3ammediatech.com/web/.env
# 403 — anything else means take the site down and fix AllowOverride
```

Full checklist in [docs/DEPLOY.md](docs/DEPLOY.md) §3.

### 2. Legacy applications must not break

`3ammediatech.com` also hosts `/armonyx`, `/trebl` and others still to be
inventoried. Today the site is confined to `/web` and cannot affect them. That
changes at the apex cutover, when this application starts catching every
unmatched path — at which point a mistake takes down live client software
rather than merely showing the wrong page.

Three layers will protect them, and the redundancy is deliberate:

| Layer | Where |
|---|---|
| Web-server rules match legacy paths first, so they never reach PHP | vhost config (cutover) |
| `ReservedPath` middleware 404s reserved segments before any DB query | `app/Middleware/ReservedPath.php` |
| Admin slug validation refuses colliding slugs | `config/reserved.php` |

**Before and after any web-server change:**

```bash
bin/legacy-smoketest.sh baseline   # BEFORE — capture the fingerprint
# ... make the change ...
bin/legacy-smoketest.sh verify     # AFTER — exits non-zero on any regression
```

`config/reserved.php` currently lists only `armonyx` and `trebl`. Everything
else must come from the Phase 0 audit before cutover.

### 2. Local PHP is 7.4 — this codebase requires 8.2+

The machine currently has **PHP 7.4.30** (XAMPP). This project uses constructor
property promotion, `readonly`, `match`, enums and typed properties, none of
which parse on 7.4. Running `php -l` against these files with the XAMPP binary
produces parse errors that are **artefacts of the old interpreter, not defects
in the code** — but it also means nothing here can be run or verified locally
until PHP is upgraded.

Install PHP 8.2 to match the server (8.2.31) before starting Phase 1 development.

### 3. Security is structural, not a discipline

There is no framework default to fall back on, so the defences are built into
the architecture rather than into developer habits:

- **`Database`** exposes no method that places a value into SQL. Every method
  takes a SQL string plus a separate bindings array. There is no `raw()` escape
  hatch, and `ATTR_EMULATE_PREPARES` is off. Dynamic `ORDER BY` identifiers go
  through `identifier()`, which requires an explicit allowlist.
- **CSRF** is verified in the middleware pipeline, not per-handler — a new POST
  route is protected whether or not anyone remembered.
- **Escaping** is manual and enforced in review: never echo a variable without
  `e()` / `e_attr()` / `e_url()` / `e_js()`. Pick by destination — `e()` is only
  correct for HTML text.
- **`media.alt_text` is `NOT NULL`.** Accessibility enforced by the schema is
  accessibility that actually ships.

---

## Setup

```bash
cp .env.example .env && chmod 600 .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"   # → APP_KEY
```

That is the whole setup. **Composer is not required to run the site.** The
bootstrap ships its own PSR-4 autoloader and `.env` parser, and falls back to
`vendor/autoload.php` automatically once it exists. The packages in
`composer.json` (AWS SDK, PHPMailer, TOTP) are for features not yet built —
`composer install` becomes necessary at the media pipeline in Phase 3.

Likewise no Node: `css/` is served directly. Vite arrives when the asset
pipeline needs hashing and minification, and it runs locally, never on the
server.

Requires PHP 8.2+ with `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `openssl`,
and — from Phase 3 — `imagick` (strongly preferred over `gd`: AVIF support and
reliable EXIF stripping). MariaDB 11.6.

---

## Layout

Deployed as a drop-in at `/var/www/html/web/`, serving at
`3ammediatech.com/web` with no vhost changes — the same way `/armonyx` is
served. See [docs/DEPLOY.md](docs/DEPLOY.md).

```
index.php         Front controller — the only entry point
.htaccess         ★ routing + the deny rules that protect everything below
bootstrap.php     Autoload, env, error handling, service registration
css/              Stylesheets (tokens.css, app.css)
favicon.svg       Triangle cluster from the mark
uploads/          + .htaccess: served, never executed
app/              + .htaccess: denied
  Core/           Router, Request, Response, Database, View, Container, Csrf
  Middleware/     SecureHeaders, ReservedPath, (Auth, Throttle to come)
  Controllers/    Web/ and Admin/
  Models/         One class per table, explicit SQL, no ORM
  Services/       MediaProcessor, Mailer, SitemapGenerator, InquiryHandler
  Views/          Native PHP templates
  Helpers/        Escapers and global helpers
config/           + .htaccess: denied — app, database, media, reserved
routes/           + .htaccess: denied — the complete URL table
storage/          + .htaccess: denied — logs, cache, sessions, tmp
bin/              + .htaccess: denied — audit and smoke-test scripts
docs/             + .htaccess: denied
```

**The source sits inside the document root**, so `app/`, `config/` and `.env`
are addressable by URL and are protected by `.htaccess` alone — a root deny
ruleset plus a per-directory `.htaccess` in each. This is a deliberate trade to
avoid vhost changes, and it holds only while Apache honours `.htaccess`. Verify
with the curl checks in [docs/DEPLOY.md](docs/DEPLOY.md) §3 after any server
change; a `200` on `/web/.env` means the site must come down until it is fixed.

The stronger arrangement — `public/` as the only served directory, source
outside the docroot entirely — is planned for the apex cutover, where the vhost
is being edited anyway.

---

## Adding photos, video and the logo

**Everything lives in one file: `config/assets.php`.** No template edits.

1. Upload the file to `media/`
2. Open `config/assets.php`, find the slot, fill in `src` and `alt`

```php
'hero.showreel' => [
    'src'   => 'media/showreel-poster.jpg',
    'alt'   => 'Camera operator at a live conference broadcast',
    'video' => 'https://vimeo.com/…',   // optional — play button target
],
```

An image with a `src` but no `alt` **will not display**. The slot keeps its
placeholder and an explanatory HTML comment appears in the page source. That is
deliberate: an unlabelled image is unusable on a screen reader, and shipping one
silently is worse than showing the placeholder.

### Slots awaiting real assets

| Slot | Where | Ratio |
|---|---|---|
| `hero.showreel` | Hero — **highest impact on the site** | 16:9 |
| `channel.media` / `.technology` / `.ventures` | What We Do | 4:3 |
| `track.featured` | Track Record, featured | 21:9 |
| `track.broadcast` / `.events` / `.digital` / `.stills` | Track Record grid | 16:9 |
| `systems.control_room` | Systems | 16:9 |
| `product.justbump` / `.rememberme` | Technology products | 4:3 |

Sizes and export guidance are at the bottom of `config/assets.php`.

## Taxonomy — read before adding content

Two different structures, doing two different jobs. Keep them straight.

**Channels** — how the company is organised. Drives the hero, "What We Do", and
the logo's three triangles.

| Channel | Meaning |
|---|---|
| **MEDIA** | What we capture |
| **TECHNOLOGY** | What we build — also home to JustBump and Remember.Me |
| **VENTURES** | What we launch. Deliberately empty; reserved for business lines outside media and technology |

**Capabilities** — how the work is sold. Three columns in "The full chain, in
house".

| Pillar | Contains |
|---|---|
| **Media** | Video production, photography, post, content, social, livestreaming, broadcast production |
| **Technology** | Web development, digital platforms, event technology, LED/video systems, AV technology, streaming infrastructure, digital experiences |
| **Events** | Event production, technical production, conferences, hybrid events, online events, AV systems, LED/video, production management |

Events draws crews from Media and hardware from Technology rather than being a
separate discipline — but it keeps its own column because that is how buyers
shop. An event organiser searches for "event production", budgets for it as a
line item, and needs to see it named before believing it is covered. Making them
assemble it from two other columns loses the enquiry.

**JustBump and Remember.Me are technology products, not ventures.** They render
in the Systems section. A technology arm that ships its own software is proof it
is a real engineering team, and that proof belongs inside the Technology story
rather than filed where it reads as a side project.

## Inquiry forms

Two forms, one template. `/start/media` and `/start/technology` differ only in
their project-type list.

**Edit the options in `config/forms.php`** — one line per option, nothing else
to touch.

### Where submissions go

`storage/inquiries/inquiries.jsonl` — one JSON object per line, append-only,
behind a deny-all `.htaccess`.

**Every submission is written to disk before any attempt to send mail, and the
visitor only sees the success page once that write succeeds.** There is no
verified SES sender yet, so `mail()` from this box will often be dropped by the
recipient's spam filter — and a form that appears to work while quietly losing
enquiries is worse than no form. Mail is a best-effort notification on top; the
file is the record.

To read them:

```bash
tail -20 storage/inquiries/inquiries.jsonl
# or, formatted:
cat storage/inquiries/inquiries.jsonl | while read -r l; do echo "$l" | python -m json.tool; done
```

Imports cleanly into `contact_inquiries` in Phase 5.

**TODO before launch:** verify `hello@3ammediatech.com` in SES and wire real
SMTP, then check that notifications actually arrive. Until then, **check the
JSONL file** — do not assume email is working.

Anti-spam is a honeypot plus a minimum time-to-submit plus a per-IP rate limit
(5/hour). No CAPTCHA: every extra step costs real enquiries, and a lead lost to
friction is more expensive than a spam message deleted.

## Projects page

`/projects` renders from `config/projects.php`, which starts **empty** and shows
a designed "in production" state rather than invented work.

Add a project by copying the commented example in that file. The category filter
builds itself from whatever categories are present, and works with JavaScript
disabled (radios plus `:has()`).

Under NDA? Use the client *type* — `'client' => 'Corporate hybrid conference,
500+ attendees'`. Honest, still persuasive, no sign-off needed.

## Still needed from the client

- **Real case studies.** Track Record states verifiable facts in words. Two to
  four cleared projects — sector, scope, one outcome line — would be stronger.
  Named clients need written clearance; use client *type* where there is an NDA
  ("Corporate hybrid conference, 500+ attendees").
- **Product status.** Both products are marked *In development* in
  `config/app.php`. If either is live, say so and add its `url`.
- **Product name.** The brief calls the memorial platform both "Remember.Me" and
  "JustRemember". Currently using Remember.Me.
- **Logo vector** in `/brand/`, then set `brand.mark` and `brand.lockup` in
  `config/assets.php`. Colour tokens are sampled from a raster render and are
  provisional until taken from source artwork.
- **Counted figures.** Events produced, hours streamed. Only add numbers that
  can be stood behind — see the note in `js/app.js` about why the previous
  counters shipped as "0".
- **`hello@3ammediatech.com`** must exist and be SES-verified before the Phase 5
  contact form can send.

## Status

**Phase 0 — server audit & safety net.** Scripts written, awaiting a run on the
server. `bin/audit-server.sh` is strictly read-only.

**Phase 1 — core & design system.** In progress. Done: `Database`,
`ReservedPath`, escaping helpers, design tokens, config skeleton. Next: Router,
Request/Response, View, Container, middleware pipeline, migrations.

**Blocked on:**
- Phase 0 audit output — needed for the legacy app inventory, vhost rules,
  session-name collision check, and `imagick` availability
- Source logo vector in `/brand/` — every colour token is provisional until
  sampled from the original artwork
- Local PHP 8.2 upgrade
