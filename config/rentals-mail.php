<?php
declare(strict_types=1);

// Fixed company routing. Change only through a reviewed code/config deployment.
// SMTP credentials remain in the existing mail.php environment configuration.
return [
    'owner' => 'lilbeemail88@gmail.com',
    'cc' => [
        'rentals@example.com',
        'accounting@example.com',
        'operations@example.com',
    ],
];
