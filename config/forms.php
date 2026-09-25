<?php

declare(strict_types=1);

/**
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  INQUIRY FORMS — edit the project-type options here.                  │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * Unified project choices plus legacy POST allowlists.
 *
 * Adding or removing an option is a one-line change here. The values are what
 * get stored and emailed, so keep them readable — someone reads these in an
 * inbox, not a database console.
 */

return [

    'project' => [
        'slug' => 'project',
        'title' => 'Start a project',
        'kicker' => 'Project enquiry',
        'lede' => 'Tell us what you need and when. We will come back with questions, an approach and an estimate.',
        'types' => ['Media / Production', 'Technology / Event Systems', 'Other / General Inquiry'],
    ],

    'media' => [
        'slug'     => 'media',
        'title'    => 'Start a media project',
        'kicker'   => 'Media enquiry',
        'lede'     => 'Tell us what you are making. We will come back with questions, '
                    . 'an approach and an estimate.',
        'types'    => [
            'Video Live Feed Coverage',
            'Video Streaming Coverage',
            'Photography',
            'Post Production',
            'Video Shoot',
            'Content Creation',
            'Conferences',
            'Event Management',
            'Audio Visual System',
            'Hybrid Events',
            'Films',
            'Other',
        ],
    ],

    'technology' => [
        'slug'     => 'technology',
        'title'    => 'Start a tech project',
        'kicker'   => 'Technology enquiry',
        'lede'     => 'Tell us what you need built or operated. We will come back with '
                    . 'questions, an approach and an estimate.',
        'types'    => [
            'Web Development',
            'Digital Experiences',
            'Event Technology',
            'Streaming Infrastructure',
            'Application Development',
            'Business Solutions',
            'Other',
        ],
    ],

    /*
     * Ventures enquiries.
     *
     * Legacy ventures enquiries remain available for old links.
     */
    'ventures' => [
        'slug'     => 'ventures',
        'title'    => 'Inquire about ventures',
        'kicker'   => 'Ventures enquiry',
        'lede'     => 'Studio, stage and event management — tell us what you need '
                    . 'and when, and we will come back with availability and pricing.',
        'types'    => [
            'Studio Setup',
            'Stage Setup',
            'Event Management',
            'Production Equipment',
            'Studio / Production Space',
            'Other',
        ],
    ],

    /*
     * Ad landing page quote form (/get-a-quote).
     */
    'quote' => [
        'slug'             => 'quote',
        'path'             => '/get-a-quote',
        'title'            => 'Get a quote for your event',
        'kicker'           => 'Event quote',
        'lede'             => 'Tell us the date, venue and what you need.',
        'details_required' => false,
        'types'            => [
            'Event Production & Livestreaming',
            'Event Coverage (Video / Photo)',
            'LED / AV & Technical Production',
            'Equipment Rentals',
            'Other',
        ],
    ],

    /*
     * Anti-spam.
     *
     * A honeypot plus a minimum time-to-submit stops the overwhelming majority
     * of bots without putting a CAPTCHA in front of a paying customer. Add
     * Turnstile only if real abuse appears — every extra step costs real
     * enquiries, and a lead lost to friction is more expensive than a spam
     * message deleted.
     */
    'honeypot_field'   => 'company_website',   // hidden; bots fill it, humans cannot see it
    'min_seconds'      => 3,                   // a human cannot read and complete faster
    'rate_limit'       => 5,                   // submissions per IP...
    'rate_limit_window' => 3600,               // ...per hour

    /*
     * Field limits. Enforced server-side; the browser's maxlength is a
     * convenience, not a control.
     */
    'limits' => [
        'name'    => 120,
        'email'   => 190,
        'phone'   => 40,
        'company' => 160,
        'details' => 5000,
    ],
];
