<?php

declare(strict_types=1);

/**
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  INQUIRY FORMS — edit the project-type options here.                  │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * Two forms, one template. "Start a media project" and "Start a tech project"
 * are the same fields with a different type list, which is why the split CTA
 * costs nothing to maintain: it qualifies the enquiry before anyone reads it.
 *
 * Adding or removing an option is a one-line change here. The values are what
 * get stored and emailed, so keep them readable — someone reads these in an
 * inbox, not a database console.
 */

return [

    'media' => [
        'slug'     => 'media',
        'title'    => 'Start a media project',
        'kicker'   => 'Media enquiry',
        'lede'     => 'Tell us what you are making. We will come back with questions, '
                    . 'a approach and an estimate.',
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
     * Softer framing than the other two on purpose: a rental request and a
     * full event-management brief are very different sales, and "Start a
     * project" fits neither.
     */
    'ventures' => [
        'slug'     => 'ventures',
        'title'    => 'Inquire about ventures',
        'kicker'   => 'Ventures enquiry',
        'lede'     => 'Studio, stage, event management or rentals — tell us what you need '
                    . 'and when, and we will come back with availability and pricing.',
        'types'    => [
            'Studio Setup',
            'Stage Setup',
            'Event Management',
            'Equipment Rental',
            'Studio / Space Rental',
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
