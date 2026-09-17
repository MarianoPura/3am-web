<?php

declare(strict_types=1);

/**
 * Development catalogue for the Rentals page.
 *
 * These are representative sample options for testing the catalogue UI. They
 * do not promise stock, pricing, or availability. Replace the entries with
 * verified packages when the live rental list is ready.
 */

return [
    'categories' => [
        [
            'id' => 'camera-video',
            'name' => 'Camera / video',
            'slot' => 'channel.media',
            'description' => 'Capture packages for interviews, events, and content production.',
        ],
        [
            'id' => 'audio',
            'name' => 'Audio',
            'slot' => 'venture.events',
            'description' => 'Practical audio support for presenters, rooms, and live programs.',
        ],
        [
            'id' => 'lighting',
            'name' => 'Lighting',
            'slot' => 'venture.stage',
            'description' => 'Flexible lighting options for sets, stages, and event coverage.',
        ],
        [
            'id' => 'display',
            'name' => 'Display / projection',
            'slot' => 'channel.technology',
            'description' => 'Presentation and viewing options for rooms, talks, and events.',
        ],
        [
            'id' => 'broadcast',
            'name' => 'Livestream / broadcast',
            'slot' => 'systems.control_room',
            'description' => 'Signal and production packages for live or hybrid delivery.',
        ],
        [
            'id' => 'studio-space',
            'name' => 'Studio / space',
            'slot' => 'venture.studio',
            'description' => 'Production space and support for planned shoots and sessions.',
        ],
    ],

    // Sample inventory only. Each item uses an existing representative image
    // slot; the photograph does not imply that a particular item is in stock.
    'items' => [
        [
            'id' => 'mirrorless-camera-kit',
            'name' => 'Mirrorless Camera Kit',
            'category' => 'camera-video',
            'slot' => 'channel.media',
            'description' => 'A compact sample camera package for interviews, events, and content production.',
            'ideal_for' => 'Interviews · Events · Social content',
            'includes' => 'Camera body · lens · battery set · support accessories',
        ],
        [
            'id' => 'cinema-camera-package',
            'name' => 'Cinema Camera Package',
            'category' => 'camera-video',
            'slot' => 'venture.rentals',
            'description' => 'A sample production package for controlled shoots and higher-touch visual work.',
            'ideal_for' => 'Films · Branded content · Interviews',
            'includes' => 'Camera package · lens option · media · support accessories',
        ],
        [
            'id' => 'wireless-microphone-kit',
            'name' => 'Wireless Microphone Kit',
            'category' => 'audio',
            'slot' => 'venture.events',
            'description' => 'Flexible sample wireless audio support for presenters, interviews, and event programs.',
            'ideal_for' => 'Presentations · Interviews · Events',
            'includes' => 'Transmitters · receivers · lavalier options · connecting cables',
        ],
        [
            'id' => 'audio-mixer-package',
            'name' => 'Audio Mixer Package',
            'category' => 'audio',
            'slot' => 'systems.control_room',
            'description' => 'A sample mixer and connection package for managed room or production audio.',
            'ideal_for' => 'Panels · Hybrid events · Small productions',
            'includes' => 'Mixer · input connections · monitoring · setup accessories',
        ],
        [
            'id' => 'led-panel-lighting-kit',
            'name' => 'LED Panel Lighting Kit',
            'category' => 'lighting',
            'slot' => 'venture.stage',
            'description' => 'A sample compact lighting option for interviews, sets, and small event spaces.',
            'ideal_for' => 'Interviews · Product content · Sets',
            'includes' => 'LED panels · stands · power accessories · diffusion options',
        ],
        [
            'id' => 'projector-package',
            'name' => 'Projector Package',
            'category' => 'display',
            'slot' => 'channel.technology',
            'description' => 'A sample projection package for presentations, screenings, and event programs.',
            'ideal_for' => 'Talks · Screenings · Presentations',
            'includes' => 'Projector package · signal cables · connection support',
        ],
        [
            'id' => 'livestream-encoder-kit',
            'name' => 'Livestream Encoder Kit',
            'category' => 'broadcast',
            'slot' => 'systems.control_room',
            'description' => 'A sample encoding package for sending a managed program to an online audience.',
            'ideal_for' => 'Livestreams · Hybrid events · Web programs',
            'includes' => 'Encoder · signal connections · monitoring · setup guidance',
        ],
        [
            'id' => 'switcher-production-kit',
            'name' => 'Switcher / Production Kit',
            'category' => 'broadcast',
            'slot' => 'track.featured',
            'description' => 'A sample switching package for multi-source live production and event coverage.',
            'ideal_for' => 'Conferences · Live shows · Multi-camera events',
            'includes' => 'Switcher package · monitoring · signal cabling · production accessories',
        ],
        [
            'id' => 'studio-space',
            'name' => 'Studio Space',
            'category' => 'studio-space',
            'slot' => 'venture.studio',
            'description' => 'A sample studio access option for planned shoots, recordings, and production sessions.',
            'ideal_for' => 'Content shoots · Recordings · Small productions',
            'includes' => 'Studio access · basic production coordination · session planning',
        ],
        [
            'id' => 'production-support-package',
            'name' => 'Production Support Package',
            'category' => 'studio-space',
            'slot' => 'venture.events',
            'description' => 'A sample support option for coordinating a rental setup around a production brief.',
            'ideal_for' => 'Events · Shoots · Technical setups',
            'includes' => 'Pre-production check · setup support · handoff coordination',
        ],
    ],
];
