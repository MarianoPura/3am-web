<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Only config/*.php reads env(). Application code reads config(), so that a
 * missing environment variable fails at boot rather than randomly at the point
 * of first use — and so config can later be cached without behaviour changing.
 */

/*
 * Mount point.
 *
 * The site runs at a subdirectory during development (/web) before it takes
 * over the apex at cutover. Routes are written once against the apex; Request
 * strips this prefix so they match in both places, and url() adds it back.
 *
 * Auto-detected from the front controller's location, so moving the mount is a
 * web-server change and nothing else. Set APP_BASE_PATH to override.
 */
$basePath = env('APP_BASE_PATH');

if ($basePath === null) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $basePath  = $scriptDir === '' ? '' : $scriptDir;
}

$basePath = $basePath === '/' ? '' : rtrim((string) $basePath, '/');

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [
    'name'     => env('APP_NAME', '3AM Media, Technology and Ventures Inc.'),
    'brand'    => '3AM Digital Media',
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'locale'   => 'en_PH',

    'key' => env('APP_KEY', ''),

    'base_path' => $basePath,
    'url'       => $appUrl . $basePath,

    'force_https' => (bool) env('FORCE_HTTPS', true),
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    'vite_dev_server' => (bool) env('VITE_DEV_SERVER', false),

    /*
     * Company details.
     *
     * Kept in config rather than hard-coded in templates because they appear in
     * the footer, the contact page, the Organization/LocalBusiness JSON-LD, and
     * transactional email — four places that must never disagree.
     */
    'company' => [
        'legal_name' => '3AM Media, Technology and Ventures Inc.',
        'brand'      => '3AM Digital Media',
        'founded'    => 2021,
        'address'    => [
            'street'   => '4th Floor, No. 22 Bristol St., Greater Lagro',
            'locality' => 'Quezon City',
            'region'   => 'Metro Manila',
            'country'  => 'PH',
        ],
        // Approximate — replace with surveyed coordinates before the
        // LocalBusiness schema goes live. Wrong geo data is worse than none.
        'geo' => ['lat' => 14.7169, 'lng' => 121.1244],
    ],

    /*
     * Where inquiries go until the contact form exists (Phase 5).
     *
     * NOTE: notifications are sent FROM a domain address (noreply@…) with this
     * as Reply-To. Sending as a gmail.com address would be a forged header that
     * SPF rejects outright, so the two are deliberately different.
     */
    'contact_email' => env('MAIL_INQUIRY_TO', '3ammediatech@gmail.com'),

    /*
     * The three channels. Drives the homepage "THE THREE" section, the primary
     * navigation, and the service taxonomy — one definition, so the logo's
     * three triangles and the information architecture cannot drift apart.
     */
    'channels' => [
        'media' => [
            'label' => 'MEDIA',
            'line'  => 'What we capture.',
            'body'  => 'Video production, photography, post-production, and content built to be seen.',
            'slug'  => 'media',
        ],
        'technology' => [
            'label' => 'TECHNOLOGY',
            'line'  => 'What we build.',
            'body'  => 'Platforms, infrastructure, event systems — and our own shipped products.',
            'slug'  => 'technology',
        ],
        'ventures' => [
            'label' => 'VENTURES',
            'line'  => 'What we launch.',
            'body'  => 'New business lines beyond production and technology. Emerging pillar.',
            'slug'  => 'ventures',
        ],
    ],

    /*
     * Track record.
     *
     * Facts stated in words, deliberately not counters.
     *
     * The previous version rendered "0 Years operating / 0 Channels /
     * 0 Capabilities" — the markup carried 0 as its resting state and relied
     * on JavaScript to correct it, so any script failure produced a page that
     * looked broken rather than merely un-animated. A claim that cannot render
     * itself correctly without JS does not belong in the markup at all.
     *
     * Every line here is verifiable. Add counted figures only once someone has
     * actually counted them.
     */
    'proof' => [
        [
            'label' => 'Operating since 2021',
            'body'  => 'Four years building and running production and technology for live work.',
        ],
        [
            'label' => 'In-house teams',
            'body'  => 'Media, technology and events staffed internally.',
        ],
        [
            'label' => 'Full signal path',
            'body'  => 'Camera through switcher, encoder and CDN to the audience, owned end to end.',
        ],
        [
            'label' => 'We ship our own software',
            'body'  => 'We create the technology behind your events and your business — one in-house team, web platforms or streaming infrastructure.',
        ],
    ],

    /*
     * Ventures — business lines beyond production and technology.
     *
     * No longer empty: these are real offerings. Note they are engagements of
     * very different shapes — a day's equipment rental and full event
     * management are not the same sale — which is why this section's CTA is
     * softer than "Start a project".
     */
    'ventures_section' => [
        'headline' => 'Beyond media and technology.',
        'subhead'  => 'Ventures is where 3AM builds new business lines beyond production '
                    . 'and technology — from physical spaces to event operations.',
        'cta'      => 'Inquire about ventures',
    ],

    'ventures' => [
        [
            'name' => 'Studio Setup',
            'slot' => 'venture.studio',
            'body' => 'Fully equipped studio space for shoots, broadcasts and recordings.',
        ],
        [
            'name' => 'Stage Setup',
            'slot' => 'venture.stage',
            'body' => 'Staging and platform builds for events, conferences and productions.',
        ],
        [
            'name' => 'Event Management',
            'slot' => 'venture.events',
            'body' => 'End-to-end planning and on-the-ground management for corporate and private events.',
        ],
        [
            'name' => 'Rentals',
            'slot' => 'venture.rentals',
            'body' => 'Equipment and space rentals — AV gear, staging and studio access, available on their own.',
        ],
    ],

    /*
     * Homepage MEDIA section.
     *
     * "Event Coverage" rather than "Event Production" here: coverage is the
     * creative/camera side, which is what belongs under Media. The operational
     * side of an event sits under Ventures now.
     */
    'section_media' => [
        'headline' => 'What we capture.',
        'subhead'  => 'From a single camera to a full broadcast package.',
        'tags'     => [
            'Video Production',
            'Photography',
            'Post-Production',
            'Content Creation',
            'Social Media',
            'Livestreaming',
            'Event Coverage',
        ],
    ],

    /*
     * Homepage TECHNOLOGY section. Carries the signal-path diagram and the
     * two shipped products as proof.
     */
    'section_technology' => [
        'headline' => 'Technology, built end to end.',
        'subhead'  => 'We create the technology behind your events and your business — '
                    . 'one in-house team, from web platforms to streaming infrastructure.',
        'tags'     => [
            'Web Development',
            'Digital Experiences',
            'Event Technology',
            'AV / Technical Systems',
            'Streaming Infrastructure',
            'Application Development',
            'Business Solutions',
        ],
    ],

    /*
     * Capabilities — three pillars: Media, Technology, Events.
     *
     * Events draws on both other pillars (crews from Media, LED and AV from
     * Technology) rather than being a separate discipline. It gets its own
     * column anyway because that is how buyers shop: an event organiser
     * searches for "event production", budgets for it as a line item, and
     * needs to see it named before they will believe it is covered. Making
     * them assemble it themselves from two other columns loses the enquiry.
     *
     * The top-level channels stay Media / Technology / Ventures — that is the
     * company structure, and it is what the logo's three triangles encode.
     * Capabilities are how the work is sold; channels are how it is organised.
     */
    'capabilities' => [
        'media' => [
            'label'   => 'Media',
            'summary' => 'Capture, cut and finish — from a single camera to a full broadcast package.',
            'why'     => 'One team from brief to master, so the look holds from the first frame to the final export.',
            'items'   => [
                'Video Production',
                'Photography',
                'Post-Production',
                'Content Creation',
                'Social Media',
                'Livestreaming',
                'Broadcast Production',
            ],
        ],
        'technology' => [
            'label'   => 'Technology',
            'summary' => 'The systems behind the picture — platforms, infrastructure and signal.',
            'why'     => 'Built by the people who run the broadcast, so the platform and the signal are designed together.',
            'items'   => [
                'Web Development',
                'Digital Platforms',
                'Event Technology',
                'LED / Video Systems',
                'AV Technology',
                'Streaming Infrastructure',
                'Digital Experiences',
            ],
        ],
        'events' => [
            'label'   => 'Events',
            'summary' => 'Rooms, stages and the technical production that holds them together.',
            'why'     => 'One technical supplier for the room — staging, screens, sound and stream on the same call sheet.',
            'items'   => [
                'Event Production',
                'Technical Production',
                'Conferences',
                'Hybrid Events',
                'Online Events',
                'AV Systems',
                'LED / Video',
                'Production Management',
            ],
        ],
    ],

    /*
     * Technology products — shipped in-house by the technology arm.
     *
     * These belong under TECHNOLOGY. A technology department that ships its own
     * software is demonstrably a real one, and that is the credibility signal —
     * so these sit inside the Technology story rather than in a separate
     * "Ventures" section where they read as side projects.
     *
     * TODO(client): confirm status and add 'url' for either product that is
     * publicly live — a shipped product is far stronger proof than one in
     * development. Also confirm the memorial platform's name: the brief refers
     * to it as both "Remember.Me" and "JustRemember".
     */
    'tech_products' => [
        [
            'name'    => 'JustBump',
            'slot'    => 'product.justbump',
            'tagline' => 'NFC digital calling cards',
            'body'    => 'Tap to share contact details, links and profiles instantly — no app required.',
            'status'  => 'In development',
            'url'     => null,
        ],
        [
            'name'    => 'Remember.Me',
            'slot'    => 'product.rememberme',
            'tagline' => 'QR memorial tribute platform',
            'body'    => 'A permanent, scannable way to share a life story, photos and memories.',
            'status'  => 'In development',
            'url'     => null,
        ],
    ],

    /*
     * Sectors the company is equipped for.
     *
     * Deliberately framed as capability, not as a client list. Naming a client
     * — or implying one — without written clearance is not a risk worth taking
     * for a row of logos. Real client logos arrive from the `clients` table in
     * Phase 3, once clearances exist.
     */
    'sectors' => [
        'Government', 'Corporate', 'Broadcast', 'Education',
        'Events & Conferences', 'Non-profit', 'Retail', 'Hospitality',
    ],

];
