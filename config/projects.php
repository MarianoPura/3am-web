<?php

declare(strict_types=1);

/**
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  PROJECTS — the /projects showcase page.                              │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * Add a project by copying the block below the 'projects' key and filling it
 * in. The page builds its category filter automatically from whatever
 * categories appear here, so nothing else needs editing.
 *
 * Starts empty on purpose. An empty array renders a designed "in production"
 * state; it does NOT render fake projects. Naming a client you have not cleared
 * is the one unrecoverable mistake on a portfolio site.
 *
 *   ── HOW TO ADD ONE ─────────────────────────────────────────────────────
 *   [
 *       'title'    => 'National science summit livestream',
 *       'client'   => 'Government agency',        // or the real name, once cleared
 *       'category' => 'Broadcast',                // Broadcast | Events | Digital | Stills
 *       'year'     => 2025,
 *       'scope'    => '3-camera multicam, 2-hour livestream, 4-country audience',
 *       'image'    => 'media/projects/science-summit.jpg',
 *       'alt'      => 'Wide shot of a conference stage with LED backdrop',
 *       'video'    => null,                        // optional Vimeo/YouTube URL
 *       'featured' => true,                        // one featured project runs full width
 *   ],
 *
 *   ── UNDER NDA? ─────────────────────────────────────────────────────────
 *   Use the client TYPE rather than the name, and describe the work:
 *       'client' => 'Corporate hybrid conference, 500+ attendees'
 *   That is honest, still persuasive, and needs no sign-off.
 *
 *   ── ALT TEXT IS REQUIRED ───────────────────────────────────────────────
 *   A project with an 'image' but no 'alt' falls back to a placeholder frame.
 *   Same rule as config/assets.php, for the same reason.
 *
 * @see config/assets.php — homepage media slots
 */

return [

    /*
     * Category order on the filter bar. Categories not listed here still
     * appear, appended after these.
     */
    'categories' => ['Broadcast', 'Events', 'Digital', 'Stills'],

    /*
     * TODO(client): add 5-10 of the strongest completed projects, each with at
     * least one visual — a still frame or an event photo is enough.
     *
     * Candidates mentioned so far, pending confirmation and sign-off:
     *   - SMV Incorporation corporate video (executive / leadership feature)
     *   - Real estate travel video content
     *   - Live event and hybrid conference AV work
     */
    'projects' => [],
];
