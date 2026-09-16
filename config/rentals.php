<?php
declare(strict_types=1);

return [
    // Categories are supported by app.ventures: AV gear, staging, studio access.
    // These existing photographs illustrate the services, not item availability.
    'categories' => [
        ['id' => 'av', 'name' => 'AV gear', 'slot' => 'venture.rentals'],
        ['id' => 'staging', 'name' => 'Staging', 'slot' => 'venture.stage'],
        ['id' => 'studio', 'name' => 'Studio access', 'slot' => 'venture.studio'],
    ],
    // Add only verified inventory. Each entry requires name, category (ID above),
    // slot (an assets.php image slot), and description. No availability is implied.
    // Empty intentionally: the repository contains no item-level rental inventory.
    'items' => [],
];
