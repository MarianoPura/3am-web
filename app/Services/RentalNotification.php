<?php

declare(strict_types=1);

namespace App\Services;

/** Best-effort transactional email; order commits never depend on mail delivery. */
final class RentalNotification
{
    public static function send(string $email, string $orderNumber, string $token, int $status): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            error_log('Rentals email skipped: invalid destination or status token.');
            return;
        }
        $statusLabel = RentalPaymentStatus::label($status);
        $message = match ($status) {
            RentalPaymentStatus::APPROVED => 'Your payment proof was approved.',
            RentalPaymentStatus::REJECTED => 'Your payment proof was rejected. Please contact Rentals support for the next step.',
            default => 'Your payment proof was received. Please wait for the 3AM team to review it.',
        };
        $link = absolute_url('rentals/order-status/' . $token);
        $body = "3AM Rentals\n\nOrder: {$orderNumber}\nPayment status: {$statusLabel}\n{$message}\n\nView status: {$link}\n";
        $sender = (string) config('app.contact_email');
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            error_log('Rentals email skipped: sender is not configured.');
            return;
        }
        $headers = 'From: 3AM Rentals <' . str_replace(["\r", "\n"], '', $sender) . ">\r\nContent-Type: text/plain; charset=UTF-8";
        try {
            if (!@mail($email, '3AM Rentals — ' . $statusLabel . ' · ' . $orderNumber, $body, $headers)) {
                error_log('Rentals email delivery failed for order ' . $orderNumber . '.');
            }
        } catch (\Throwable $e) {
            error_log('Rentals email delivery failed for order ' . $orderNumber . ': ' . $e->getMessage());
        }
    }
}
