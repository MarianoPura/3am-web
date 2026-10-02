<?php

declare(strict_types=1);

namespace App\Services;

/** Best-effort transactional email; order commits never depend on mail delivery. */
final class RentalNotification
{
    public static function send(string $email, string $orderNumber, string $token, int $status): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            RentalDiagnostic::failure('notification', 'validation', ['validation' => 'failed']);
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
            RentalDiagnostic::failure('notification', 'configuration', ['error' => 'configuration']);
            return;
        }
        $headers = 'From: 3AM Rentals <' . str_replace(["\r", "\n"], '', $sender) . ">\r\nContent-Type: text/plain; charset=UTF-8";
        try {
            if (!@mail($email, '3AM Rentals — ' . $statusLabel . ' · ' . $orderNumber, $body, $headers)) {
                RentalDiagnostic::failure('notification', 'delivery', ['error' => 'unexpected']);
            }
        } catch (\Throwable $e) {
            RentalDiagnostic::exception('notification', 'delivery', $e);
        }
    }
}
