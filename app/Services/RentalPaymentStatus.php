<?php

declare(strict_types=1);

namespace App\Services;

/** The single order/payment workflow state used throughout Rentals. */
final class RentalPaymentStatus
{
    public const PENDING = 0;
    public const APPROVED = 1;
    public const REJECTED = 2;

    public static function label(int|string|null $status): string
    {
        return match ((int) $status) {
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            default => 'Pending',
        };
    }

    public static function valid(int $status): bool
    {
        return in_array($status, [self::PENDING, self::APPROVED, self::REJECTED], true);
    }
}
