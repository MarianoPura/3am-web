<?php
declare(strict_types=1);
namespace App\Services;

final class RentalSmtpTransport implements RentalMailTransport
{
    public function __construct(private readonly SmtpMailer $mailer) {}
    public function isConfigured(): bool
    {
        return $this->mailer->isConfigured() && filter_var((string) config('mail.from.address', ''), FILTER_VALIDATE_EMAIL) !== false;
    }
    public function send(string $destination, string $subject, string $text, string $html): void
    {
        $support = (string) config('app.contact_email', '');
        $this->mailer->send([$destination], $subject, $text, [],
            filter_var($support, FILTER_VALIDATE_EMAIL) ? $support : null, $html);
    }
}
