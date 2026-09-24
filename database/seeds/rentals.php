<?php

declare(strict_types=1);

return [
    'categories' => [
        ['id' => 'camera', 'name' => 'Camera'],
        ['id' => 'audio', 'name' => 'Audio'],
        ['id' => 'lighting', 'name' => 'Lighting'],
    ],
    'items' => [
        [
            'id' => 'sony-a7s-iii',
            'name' => 'Sony A7S III',
            'category' => 'camera',
            'description' => 'Full-frame cinema body built for low-light capture and flexible production work.',
            'ideal_for' => 'Documentary, live events and multi-camera production.',
            'slot' => 'media.work1',
            'availability_status' => 'Available',
            'includes' => 'Body · Standard battery · Media card · Cable kit',
            'is_sample' => true,
        ],
        [
            'id' => 'sennheiser-k6-mix',
            'name' => 'Sennheiser Wireless Kit',
            'category' => 'audio',
            'description' => 'Portable wireless audio system for interviews, presenters and event coverage.',
            'ideal_for' => 'Interviews, presenter runs and live capture.',
            'slot' => 'channel.technology',
            'availability_status' => 'Available',
            'includes' => 'Transmitters · Receivers · Boom · Monitoring kit',
            'is_sample' => true,
        ],
        [
            'id' => 'arri-650w',
            'name' => 'ARRI 650W Light',
            'category' => 'lighting',
            'description' => 'High-output lighting package for controlled setups and event environments.',
            'ideal_for' => 'Set builds, interviews and event production.',
            'slot' => 'venture.events',
            'availability_status' => 'Available',
            'includes' => 'Light · Stand · Diffusion · Power cable',
            'is_sample' => true,
        ],
    ],
];
