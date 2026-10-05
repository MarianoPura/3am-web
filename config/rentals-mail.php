<?php

declare(strict_types=1);

/**
 * Rentals mail routing configuration.
 *
 * All recipients are loaded dynamically from environment variables (.env).
 * CC recipients are copied on customer rental notifications (order confirmation,
 * payment proof received, review approved/rejected).
 *
 * SMTP credentials and connection options reside in config/mail.php.
 */

$addresses = static fn (mixed $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
    static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
));

return [
    // Owner / internal contact email (from .env, fallback to MAIL_INQUIRY_TO / MAIL_FROM_ADDRESS)
    'owner' => (string) env('MAIL_RENTALS_TO', env('MAIL_INQUIRY_TO', env('MAIL_FROM_ADDRESS', ''))),

    // CC recipients copied on transactional customer emails, pulled from .env MAIL_CC
    'cc' => $addresses(env('MAIL_RENTALS_CC', env('MAIL_CC', ''))),
];
