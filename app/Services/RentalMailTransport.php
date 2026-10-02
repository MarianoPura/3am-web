<?php
declare(strict_types=1);
namespace App\Services;

/** Test seam; the production implementation delegates to the existing SMTP client. */
interface RentalMailTransport
{
    public function isConfigured(): bool;
    public function send(string $destination, string $subject, string $text, string $html, array $cc = []): void;
}
