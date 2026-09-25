<?php

declare(strict_types=1);

/**
 * Inquiry storage, tracking tables, and email diagnostic tool.
 *
 *   php bin/inquiries.php --check        database connection + row counts + SMTP status
 *   php bin/inquiries.php --mail-test    send a test email to MAIL_CC over SMTP
 *
 * Reads .env. Prints no credentials.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap.php';
$command   = $argv[1] ?? '--check';

if (!in_array($command, ['--check', '--mail-test'], true)) {
    fwrite(STDERR, "Usage: php bin/inquiries.php --check|--mail-test\n");
    exit(1);
}

if ($command === '--mail-test') {
    $mailer = $container->get(App\Services\SmtpMailer::class);
    $cc     = (array) config('mail.inquiry.cc', []);

    if ($cc === []) {
        fwrite(STDERR, "MAIL_CC is empty in .env — nothing to send the test to.\n");
        exit(1);
    }

    try {
        $mailer->send(
            to: $cc,
            subject: 'SMTP test — ' . config('app.brand'),
            body: "This is a test message from bin/inquiries.php.\n\nIf you are reading this, SMTP configuration is working correctly.",
        );
        echo 'Test email sent successfully to ' . implode(', ', $cc) . " via " . config('mail.host') . ':' . config('mail.port') . ".\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'SMTP test failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

// ── Check Database & Tables ──────────────────────────────────
$db = $container->get(App\Core\Database::class);

try {
    $db->selectValue('SELECT 1');
    echo "✓ Database connected to [" . config('database.connections.mysql.database') . "] as [" . config('database.connections.mysql.username') . "].\n";

    $inquiriesCount = $db->selectValue('SELECT COUNT(*) FROM inquiries');
    $visitsCount    = $db->selectValue('SELECT COUNT(*) FROM landing_page_visits');
    $eventsCount    = $db->selectValue('SELECT COUNT(*) FROM tracking_events');

    echo "  - inquiries:           {$inquiriesCount} rows\n";
    echo "  - landing_page_visits: {$visitsCount} rows\n";
    echo "  - tracking_events:     {$eventsCount} rows\n";

    $mailer = $container->get(App\Services\SmtpMailer::class);
    echo "✓ SMTP status: " . ($mailer->isConfigured() ? 'Configured (' . config('mail.host') . ':' . config('mail.port') . ')' : 'Not fully configured (MAIL_USERNAME/MAIL_HOST empty)') . "\n";
    echo "✓ Meta Pixel ID: " . (config('landing.meta_pixel_id') ?: '(None)') . "\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Database check failed: " . $e->getMessage() . "\n");
    exit(1);
}
