<?php

declare(strict_types=1);

/**
 * Rentals mail routing configuration.
 *
 * All recipients are loaded dynamically from environment variables (.env).
 * Owner and CC recipients are copied on customer rental notifications (order confirmation,
 * payment proof received, review approved/rejected).
 *
 * SMTP credentials and connection options reside in config/mail.php.
 */

$addresses = static fn (mixed $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
    static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
));

$owner = (string) env('MAIL_RENTALS_TO', env('MAIL_INQUIRY_TO', env('MAIL_FROM_ADDRESS', '')));
$companyCopies = [];
foreach ($addresses($owner . ',' . (string) env('MAIL_RENTALS_CC', env('MAIL_CC', ''))) as $address) {
    // One combined notification; never copy the same mailbox twice.
    $companyCopies[strtolower($address)] ??= $address;
}

return [
    // Owner / internal contact email (from .env, fallback to MAIL_INQUIRY_TO / MAIL_FROM_ADDRESS)
    'owner' => $owner,

    // Effective CC routing: configured Owner plus configured CC, no address overrides.
    // Delivery excludes the customer mailbox if it is also in this list.
    'cc' => array_values($companyCopies),
];
