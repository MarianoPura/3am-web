<?php

declare(strict_types=1);

/**
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  MEDIA REGISTRY — the only file you edit to add photos, video or the  │
 * │  logo to this site.                                                   │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * HOW TO ADD AN IMAGE OR VIDEO
 * ============================
 *   1. Upload the file to the  media/  folder on the server.
 *   2. Find its slot below and fill in 'src'.
 *   3. Fill in 'alt' — a short description of what is IN the picture.
 *      Leave 'src' as null and the slot keeps its designed placeholder.
 *
 *   Example:
 *       'hero.showreel' => [
 *           'src' => 'media/showreel-poster.jpg',
 *           'alt' => 'Camera operator at a live conference broadcast',
 *       ],
 *
 * PATHS
 * =====
 *   'media/file.jpg'                 → a file in the media/ folder  (usual case)
 *   'https://example.com/file.jpg'   → a full URL, used as-is
 *
 * VIDEO
 * =====
 *   'video'  — a Vimeo or YouTube URL for the play button to open.
 *   'src'    — the poster image shown before anyone presses play.
 *
 *   Always set a poster. Without one the frame is blank until the viewer
 *   clicks, and the video itself is never downloaded before then — which is
 *   deliberate: a third-party player costs 500KB-1MB before the visitor has
 *   decided to watch anything.
 *
 * WHAT NOT TO CHANGE
 * ==================
 *   'ratio' and 'label' set the layout. Changing 'ratio' will change the shape
 *   of the box and can break the grid it sits in. Supply the image at the ratio
 *   listed and it will fit without cropping surprises.
 *
 * @see app/Views/partials/frame.php — renders these
 * @see README.md "Placeholders"     — the same list as a checklist
 */

return [

    /*
     * ── BRAND ────────────────────────────────────────────────────────────
     * Until 'mark' is set, the navigation draws the triangle cluster in CSS.
     * The full lockup is artwork and is never substituted with typed text.
     */
    'brand' => [
        // Triangle cluster only — used in the navigation bar.
        'mark' => null,          // e.g. 'media/logo-mark.svg'

        // Full horizontal lockup — used in the footer. Needs a light-on-dark
        // version, since the footer sits on near-black navy.
        'lockup' => null,        // e.g. 'media/logo-lockup-white.svg'

        // Social share image. 1200x630. Shown when the site is linked in
        // Facebook, LinkedIn, Viber, Messenger or Slack.
        'og_image' => null,      // e.g. 'media/og-share.jpg'
    ],

    /*
     * ── MEDIA SLOTS ──────────────────────────────────────────────────────
     * Every image and video position on the site, in page order.
     */
    'slots' => [

        // ── Hero ──────────────────────────────────────────────────────────
        // The single highest-impact asset on the site. This is the first thing
        // a visitor sees and it is what makes the page read as a media
        // company's rather than a text document.
        'hero.showreel' => [
            'ratio' => '16x9',
            'label' => 'Showreel',
            'src' => 'media/hero.jpg',
            'alt' => 'Professional camera covering a live fashion event with stage lighting',
           'video' => 'media/showreel-1.mp4',
            'meta' => '● REC  TC 03:00:00:00',
        ],

        // ── What We Do — one representative still per channel ─────────────
        'channel.media' => [
            'ratio' => '4x3',
            'label' => 'Media',
            'src' => 'media/media.jpg',
            'alt' => 'Professional video camera filming a brightly lit corporate event stage',
        ],
        'channel.technology' => [
            'ratio' => '4x3',
            'label' => 'Technology',
            'src' => 'media/technology.jpg',
            'alt' => 'Broadcast camera and wireless video equipment at a live production',
        ],
        'channel.ventures' => [
            'ratio' => '4x3',
            'label' => 'Ventures',
            'src' => 'media/work5.jpg',
            'alt' => 'Outdoor festival stage beneath bright green, orange and yellow canopies with a broadcast camera in the foreground',
        ],

        // ── Track record ──────────────────────────────────────────────────
        'track.featured' => [
            'ratio' => '21x9',
            'label' => 'Featured project',
            'src' => 'media/featured.jpg',
            'alt' => 'Professional broadcast camera covering a large auditorium audience',
            'video' => 'media/showreel-2.mp4',
        ],
        'track.broadcast' => [
            'ratio' => '16x9',
            'label' => 'Broadcast',
            'src' => 'media/broadcast.jpg',
            'alt' => 'Live production monitors and video switcher operating during a stage event',
        ],
        'track.events' => [
            'ratio' => '16x9',
            'label' => 'Events',
            'src' => 'media/events.jpg',
            'alt' => 'Professional camera on a tripod beside a colorful corporate event stage',
        ],
        'track.digital' => [
            'ratio' => '16x9',
            'label' => 'Digital',
            'src' => 'media/digital.jpg',
            'alt' => 'Digital cinema camera recording a live event beneath decorative lighting',
        ],
        'track.stills' => [
            'ratio' => '16x9',
            'label' => 'Stills',
            'src' => 'media/stills.jpg',
            'alt' => 'Professional camera covering a corporate meeting and presentation',
        ],

        // ── Systems ───────────────────────────────────────────────────────
        'systems.control_room' => [
            'ratio' => '16x9',
            'label' => 'Control room',
            'src' => 'media/control-room.jpg',
            'alt' => 'Live production control station with multiview monitors and switching equipment',
        ],

        // ── Media section — work gallery ──────────────────────────────────
        // Six cards. Fill what you have; a slot with no src keeps its
        // placeholder, so a half-filled gallery still looks deliberate.
        'media.work1' => [
            'ratio' => '16x9',
            'label' => 'Project 01',
            'src' => 'media/work1.jpg',
            'alt' => 'Video switcher and multiview monitors covering a speaker on a red-lit ballroom stage',
            'video' => 'media/showreel-1.mp4',
        ],
        'media.work2' => [
            'ratio' => '16x9',
            'label' => 'Project 02',
            'src' => 'media/work2.jpg',
            'alt' => 'Live production monitors showing a presenter and slides above an illuminated video switcher',
            'video' => 'media/showreel-2.mp4',
        ],
        'media.work3' => [
            'ratio' => '16x9',
            'label' => 'Project 03',
            'src' => 'media/work3.jpg',
            'alt' => 'Professional video camera filming a band performing on a brightly lit ballroom stage',
            'video' => 'media/showreel-1.mp4',
        ],
        'media.work4' => [
            'ratio' => '16x9',
            'label' => 'Project 04',
            'src' => 'media/work4.jpg',
            'alt' => 'Camera operator filming two event hosts on stage beside seated banquet guests',
            'video' => 'media/showreel-2.mp4',
        ],
        'media.work5' => [
            'ratio' => '16x9',
            'label' => 'Project 05',
            'src' => 'media/work5.jpg',
            'alt' => 'Broadcast camera aimed at an outdoor festival stage beneath colorful fabric canopies',
            'video' => 'media/showreel-1.mp4',
        ],
        'media.work6' => [
            'ratio' => '16x9',
            'label' => 'Project 06',
            'src' => 'media/work6.jpg',
            'alt' => 'Camera operator using a handheld gimbal to film a woman outdoors',
            'video' => 'media/showreel-2.mp4',
        ],

        // ── Ventures ──────────────────────────────────────────────────────
        'venture.studio' => [
            'ratio' => '4x3',
            'label' => 'Studio Setup',
            'src' => 'media/control-room.jpg',
            'alt' => 'Production monitors displaying multiple camera feeds of musicians and event guests',
        ],
        'venture.stage' => [
            'ratio' => '4x3',
            'label' => 'Stage Setup',
            'src' => 'media/work5.jpg',
            'alt' => 'Outdoor festival stage with lighting trusses beneath colorful fabric canopies',
        ],
        'venture.events' => [
            'ratio' => '4x3',
            'label' => 'Event Management',
            'src' => 'media/work4.jpg',
            'alt' => 'Event hosts on a ballroom stage with a camera operator and seated banquet guests',
        ],
        'venture.rentals' => [
            'ratio' => '4x3',
            'label' => 'Rentals',
            'src' => 'media/work3.jpg',
            'alt' => 'Professional video camera with a wireless transmitter filming a live band',
        ],

        // ── Technology products — product shot or UI screenshot ────────────
        'product.justbump' => [
            'ratio' => '4x3',
            'label' => 'JustBump',
            'src' => null,
            'alt' => '',
        ],
        'product.rememberme' => [
            'ratio' => '4x3',
            'label' => 'Remember.Me',
            'src' => null,
            'alt' => '',
        ],
    ],

    /*
     * ── SIZE GUIDANCE ────────────────────────────────────────────────────
     * Export at roughly these pixel widths. Larger is wasted — nothing on the
     * page is displayed above 1600px — and costs load time on the Philippine
     * mobile connections most visitors will be on.
     *
     *   21:9   1680 × 720     4:3    1200 × 900
     *   16:9   1600 × 900     1:1    1000 × 1000
     *
     * Save as JPEG at ~80% quality, or WebP. Aim to keep each file under
     * 300KB; the hero image under 200KB.
     *
     * Automatic WebP/AVIF conversion and responsive sizes arrive with the
     * media pipeline in Phase 3 — until then, what is uploaded is what is
     * served, so export sensibly.
     */
];
