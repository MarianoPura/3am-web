<?php

declare(strict_types=1);

/**
 * Mail configuration — SMTP.
 *
 * Every value comes from .env, alongside the DB_* credentials, so all secrets
 * live in one file that is never committed.
 *
 * Sent by App\Services\SmtpMailer (no Composer dependency). Port 587 with
 * MAIL_ENCRYPTION=tls (STARTTLS) is the normal choice; 465 with ssl works too.
 */

$addresses = static fn (mixed $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
    static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
));

return [
    // MAIL_ENABLED=false turns sending off entirely (local development).
    // Inquiries are still stored; only the email is skipped.
    'enabled'    => (bool) env('MAIL_ENABLED', true),

    'host'       => (string) env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => strtolower((string) env('MAIL_ENCRYPTION', 'tls')),   // tls | ssl | none
    'username'   => (string) env('MAIL_USERNAME', ''),
    'password'   => (string) env('MAIL_PASSWORD', ''),
    'timeout'    => (int) env('MAIL_TIMEOUT', 15),

    'from' => [
        'address' => (string) env('MAIL_FROM_ADDRESS', 'noreply@3ammediatech.com'),
        'name'    => (string) env('MAIL_FROM_NAME', '3AM Digital Media'),
    ],

    /*
     * Inquiry notification & confirmation.
     *
     * Every inquiry emails the customer a confirmation, with MAIL_CC copied.
     * Replies go to MAIL_REPLY_TO (the team), not to the no-reply sender.
     */
    'inquiry' => [
        'to'       => (string) env('MAIL_INQUIRY_TO', '3ammediatech@gmail.com'),
        'cc'       => $addresses(env('MAIL_CC', '')),
        'reply_to' => (string) env('MAIL_REPLY_TO', env('MAIL_INQUIRY_TO', '3ammediatech@gmail.com')),
    ],
];
