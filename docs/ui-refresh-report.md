# 3AM website UI refresh — implementation and QA report

Completed locally on 16 September 2026. No commit, push, merge, pull request, or deployment was performed.

## Branch and starting state

- Branch used: `feature/website-ui-refresh`, newly created for this work.
- Base: `feature/add-media-assets` at `c19343a`, matching its recorded origin branch.
- Starting working tree: clean.
- Available branches: `master`, `feature/add-media-assets`, and their recorded origin references. No `development` or `dev` integration branch was available. No remote fetch was performed.
- The previous design remains recoverable at the base commit. Neither master nor the media branch was edited directly.

## Existing work preserved

The initial implementation already included the official triangle logo via `brand.mark`, local MP4 rendering, hero and featured showreels, six still-image work slots with null video values, removal of Products and Sectors from the homepage, mobile swipe rows and Media pills, a grouped mobile menu with an email contact, two-column inquiry forms, visible input boundaries, a shorter mobile textarea, and a 44px Back link.

The hero opacity bug was already fixed: the original GSAP parallax moved the hero shell without changing opacity. That behavior is preserved. The frame partial, form processing, routing, URL helpers, PHP MVC structure, fonts, logo, and existing showreel files remain unchanged.

## Design and homepage

The final direction incorporates the follow-up request for a light 3AM palette with visible dark-blue character, rather than a predominantly white website.

| Role | Token / color | Use |
| --- | --- | --- |
| Deep navy | existing `--c-void`, `#0B1622` | Hero, navigation, track record, footer |
| Logo navy | existing `--c-ink`, `#152B45` | Light-section headings, card accents, Technology photo surround |
| Cloud | `--c-cloud`, `#F0F4F8` | Media background, elevated light cards |
| Mist | `--c-mist`, `#E5EDF5` | Channels and Technology backgrounds |
| Blue haze | `--c-blue-haze`, `#D7E3EE` | Capabilities and Ventures backgrounds |
| Blue-gray text | `--c-blue-text`, `#4B5F73` | Readable secondary text on light surfaces |
| Tally yellow | existing `--c-tally`, `#FFB300` | CTAs, triangles, small rules and accents |

Changes include shorter section spacing, larger desktop gutters, a better-balanced hero split, a smaller maximum hero headline size, larger mono labels, navy card accents, stronger Ventures card structure, and consistent image/text spacing. The yellow channel triangles now sit in their own grid cells beside headings, clear of the photographs.

All eight homepage priorities remain: hero, channels, capabilities, visual proof, Media, Technology, Ventures and contact. The duplicated four-card proof gallery is retained on the existing `/projects` page instead of being repeated on the homepage. No new routes or pages were necessary.

The six Media cards use descriptive labels drawn from visible image content, replacing the repeated “Project slot” and “Case study in preparation” treatment. Configured real project titles, categories and scopes still take precedence when provided.

## Technology and supplied photographs

The second supplied photo, `566472057_819344534289650_533629560821443700_n(2).jpg`, was used as `media/technology-operations.jpg` (284,259 bytes). Its central laptop, projection and demonstration scene works well in the 4:3 crop. The first supplied photo was not copied.

The photo is registered in `config/assets.php` under `channel.technology` and reused through the frame partial in the Technology section. It is not hard-coded into image markup. Alt text describes the presenters, laptop, projected interface, seated attendees and projector. CSS uses cover cropping with an adjusted object position; the source image was not stretched, generated or retouched. The original `media/technology.jpg` remains available.

Technology now leads with three groups assembled from existing project content: platforms/applications, event/technical systems, and streaming infrastructure. The existing full livestream scenario and control-room photo remain inside a native expandable explanation. Camera → Switcher → Encoder → CDN → Audience is retained as an accessible ordered sequence. Its former tall animated SVG and unused pulse code were removed.

The scenario uses a normal grid, zero blockquote margin, a connected yellow border and 16px padding. It stacks naturally on narrow screens, without positional offsets or an empty left strip.

## Inquiry, navigation, mobile and accessibility

All three inquiry variants retain the shared template and processing. A framed navy form panel improves hierarchy, and tighter page spacing places the submit action within the first desktop screen. Existing visible labels, autocomplete, validation associations, CSRF, honeypot and field limits are preserved.

The mobile menu now fixes the body in place and restores its scroll position on close. It remains opaque, contains overscroll, closes on X, Escape or a link, wraps keyboard focus, and releases inert/scroll state when switching to desktop. Link handling closes the drawer before initiating anchor scrolling. The skip link is excluded while the drawer is open. Desktop resize returns focus to a visible navigation control when necessary.

All six homepage anchors preserve modifier-click handling and URL helpers. Cross-page anchors now also focus the destination section. Same-page navigation records distinct hashes in browser history. Section destinations clear the 81px header with an 84px scroll offset.

Mobile Capabilities, Media cards and Ventures retain horizontal scrolling and visible next-card cues. Their reveal styles keep offscreen cards visible when scrolled. Media pills remain in one horizontal row. Scrollbar tracks use the light-section border color instead of a heavy navy stripe.

The bottom CTA and footer retain the brand and address. The address has one visible instance at the bottom of each page. The configured email was added to the footer; no phone number or social account was invented.

Focus styles and reduced-motion branches remain in place. The signal explanation uses native keyboard-operable details/summary. Representative secondary text styles on the new palette measured at least 5.06:1 contrast; this is a targeted contrast check, not a formal accessibility certification.

## Concise reviewable diff, grouped by file

| Changed file | Review summary |
| --- | --- |
| `css/tokens.css` | New blue-gray palette and explicit light theme; missing spacing tokens supplied; hero/mono scale and section/gutter rhythm adjusted. Existing fonts and brand colors retained. |
| `css/app.css` | Hero balance; light-section/card styling; Technology service/photo/detail layout; triangle spacing; form panel; opaque navigation and scroll locking; readable metadata; mobile refinements. Removed unused product/marquee styles and obsolete duplicate Ventures/triangle/scenario rules; consolidated field/work-card rules. |
| `app/Views/pages/home.php` | Light theme assignments; repeated proof thumbnails delegated to existing Projects page; truthful work-image labels; service-led Technology with registered photo and expandable signal example. |
| `app/Views/pages/projects.php` | Existing project sections use the coordinated light palette. Existing honest case-study preparation state and supporting gallery remain. |
| `app/Views/layouts/base.php` | Added configured footer email alongside the existing address. |
| `config/assets.php` | Technology image path/alt updated; six descriptive image labels. All six work video entries stay null. |
| `js/app.js` | Robust mobile scroll locking/focus/resize cleanup; menu links close before scrolling; cross-page focus and hash history; reveal positions refreshed after details toggles; obsolete signal animation removed. |

Added files: `media/technology-operations.jpg` and this report, `docs/ui-refresh-report.md`.

Removed files: none. No media originals, backend files, dependencies or framework files were removed or introduced.

## Validation results

- PHP 8.4.25 syntax checks: all four changed PHP files passed (`home.php`, `projects.php`, `base.php`, `assets.php`).
- `node --check js/app.js`: passed.
- `git diff --check`: passed. Git prints only its existing LF/CRLF normalization notices.
- HTTP checks: `/`, `/start/media`, `/start/technology`, `/start/ventures` and `/projects` all returned 200 using the local PHP server.
- Runtime media-registry check: no missing configured local images/videos; all six work video values are null.
- Browser console check: no warnings or errors in the inspected preview log.

The homepage, all three inquiry pages and Projects were checked at all nine sizes below (45 page/viewport combinations). Layout metrics were checked across the matrix, with representative desktop/mobile screenshots inspected visually.

| Viewport | Homepage | All three inquiry pages | Projects |
| --- | --- | --- | --- |
| 1920 × 1080 | Pass | Pass | Pass |
| 1440 × 900 | Pass | Pass | Pass |
| 1366 × 768 | Pass | Pass | Pass |
| 1024 × 768 | Pass | Pass | Pass |
| 768 × 1024 | Pass | Pass | Pass |
| 430 × 932 | Pass | Pass | Pass |
| 390 × 844 | Pass | Pass | Pass |
| 375 × 812 | Pass | Pass | Pass |
| 360 × 800 | Pass | Pass | Pass |

No page-level horizontal overflow was detected. Homepage headline and navigation widths fit; triangles clear the images at every tested size. The final palette/layout was rechecked across all nine homepage widths after the follow-up request.

Desktop inquiry columns are balanced; Submit ends around 678px from the page top. Mobile textareas measure 112px and Back targets measure 44px. The mobile Technology scenario has 16px padding and no overflow.

Interaction checks passed: X close, Escape close, Tab/Shift+Tab wrapping, desktop resize cleanup, mobile Media gallery keyboard scrolling, six visible gallery cards, all six settled homepage anchor destinations, and cross-page navigation from `/start/media` to `/#media` with destination focus. Both showreels were observed playing; both retain autoplay, muted, loop, playsinline and no native controls. Returning to the hero restores the shell to opacity 1 and its original transform. Products/Sectors are absent.

## Assumptions and verification limits

- Existing config remains the source of corporate claims. The pre-existing “Four years” proof copy and shipped-product wording were preserved as requested; their current business accuracy was not independently verified.
- `/projects` currently has no published case-study records. Its honest preparation message remains. Populated-project filtering was not exercised; no fake projects were inserted to populate it.
- Responsive testing used the connected in-app browser, not physical iOS/Android devices or a cross-browser lab. Reduced-motion/fallback code was reviewed and preserved; OS reduced-motion switching was not separately exercised.
- No completed inquiry was submitted and no email was sent. Backend processing was unchanged.
- All requested implementation work is complete within the existing architecture. Public deployment and git publication were deliberately not performed.

## Final git diff --stat

Tracked changes only; Git does not include the untracked photo/report below in this count.

```text
 app/Views/layouts/base.php   |   1 +
 app/Views/pages/home.php     | 100 ++++++--------
 app/Views/pages/projects.php |  10 +-
 config/assets.php            |  16 +--
 css/app.css                  | 302 +++++++++++++++++--------------------------
 css/tokens.css               |  29 ++++-
 js/app.js                    |  60 ++++-----
 7 files changed, 232 insertions(+), 286 deletions(-)
```

## Final git status --short

```text
 M app/Views/layouts/base.php
 M app/Views/pages/home.php
 M app/Views/pages/projects.php
 M config/assets.php
 M css/app.css
 M css/tokens.css
 M js/app.js
?? docs/ui-refresh-report.md
?? media/technology-operations.jpg
```

Local preview: http://127.0.0.1:8000/