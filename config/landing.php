<?php

declare(strict_types=1);

/**
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  AD LANDING PAGE — /get-a-quote                                       │
 * │  Tracking, video and copy for the Facebook / Meta Ads landing page.   │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * One offer, one action. This page sells event production and livestreaming
 * and asks for exactly one thing: the lead form. Every CTA on it points at
 * the form; there is no site navigation.
 *
 * Copy lives here, like config/app.php, so wording can change without
 * touching the template. Only claims the site can stand behind — no counts,
 * clients or guarantees that nobody has verified.
 */

return [

    // Also the only path where the Meta hosts are added to the CSP — see
    // App\Middleware\SecureHeaders.
    'path' => '/get-a-quote',

    /*
     * META PIXEL
     * ==========
     * Set META_PIXEL_ID in .env. Empty means the Pixel is not loaded at all:
     * no request to Facebook, no CSP exception.
     *
     * Events (js/landing.js): PageView on load, ViewContent once when the
     * visitor scrolls into the page content, Lead + Contact only after the
     * server has confirmed the inquiry was recorded.
     */
    'meta_pixel_id' => (string) env('META_PIXEL_ID', ''),

    /*
     * VIDEO
     * =====
     * LANDING_VIDEO_URL accepts either:
     *   'media/file.mp4'                      → a file in media/, plays in page
     *   'https://www.youtube.com/watch?v=…'   → embedded on click
     *   'https://vimeo.com/123456789'         → embedded on click
     *
     * Defaults to the existing showreel until a dedicated ad video exists.
     * Empty string shows the poster frame on its own.
     */
    'video_url'    => (string) env('LANDING_VIDEO_URL', 'media/showreel-1.mp4'),
    'video_poster' => 'media/hero.jpg',
    'video_alt'    => 'Professional camera covering a live event with stage lighting',

    'meta' => [
        'title'       => 'Event Production & Livestreaming Quote — 3AM Digital Media',
        'description' => 'Event coverage, livestreaming, LED/AV and technical production from one in-house team in Quezon City. Tell us about your event and get a quote.',
        'og_image'    => 'media/hero.jpg',
    ],

    'hero' => [
        'kicker'   => 'Event production & livestreaming',
        'headline' => 'Your event, live on every screen — handled by one team.',
        'lede'     => 'Event coverage, livestreaming, LED/AV and technical production. '
                    . 'One crew runs the camera, the switcher, the stream and the screens, '
                    . 'so there are no handoffs and no gaps between suppliers.',
        'cta'      => 'Get a quote',
    ],

    'form' => [
        'heading'     => 'Get a quote for your event',
        'lede'        => 'Tell us the date, venue and what you need.',
        'button'      => 'Submit my inquiry',
        'reassurance' => 'Your information is kept confidential and will only be used to contact you regarding your inquiry.',
        'next'        => 'We usually respond within one business day.',
    ],

    'video' => [
        'heading' => 'See how we work',
        'lede'    => 'A quick look at our crew and equipment on real productions.',
    ],

    'problems' => [
        'heading' => 'Planning an event? You may have dealt with…',
        'items'   => [
            'A livestream that stutters or drops in the middle of the program.',
            'Camera, AV, LED and streaming from separate suppliers — and no one who owns the problem when something fails.',
            'Coverage that does not look as professional as the event itself.',
            'Technical issues found on the day instead of before the doors open.',
        ],
        'answer_heading' => 'One team. One call sheet.',
        'answer'         => 'One team handles everything — from the camera to the screen your audience is watching on. No handoffs, no gaps where the stream can drop.',
    ],

    // Keys from config('app.capabilities'), in display order, each paired
    // with an image slot from config/assets.php.
    'services' => [
        'heading' => 'What we handle for your event',
        'items'   => [
            'events'     => 'venture.events',
            'media'      => 'channel.media',
            'technology' => 'systems.control_room',
        ],
    ],

    'why' => [
        'heading' => 'Why event organisers work with 3AM',
    ],

    'steps' => [
        'heading' => 'How it works',
        'items'   => [
            ['title' => 'Tell us about your event', 'body' => 'Send the form with your date, venue and what you need.'],
            ['title' => 'We get in touch',          'body' => 'We usually respond within one business day to go through your requirements.'],
            ['title' => 'Get your proposal',        'body' => 'We come back with an approach and an estimate for your event.'],
        ],
    ],

    'work' => [
        'heading' => 'Recent event work',
        'lede'    => 'Stills from productions our crew has covered.',
        'slots'   => ['media.work1', 'media.work3', 'media.work2', 'media.work4', 'media.work5', 'track.broadcast'],
    ],

    'final' => [
        'heading' => 'Ready to plan your event?',
        'body'    => 'Tell us what you need and our team will get in touch with you.',
        'cta'     => 'Submit your inquiry',
    ],
];
