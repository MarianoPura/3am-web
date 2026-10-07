<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/** Bound daily availability work, including ranges restored from older carts. */
final class RentalDateRange
{
    // Covers every selection in the existing current-month + 12-month calendar.
    public const MAX_DAYS = 400;

    /** @return array{DateTimeImmutable, DateTimeImmutable}|null */
    public static function parse(string $start, string $end): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $end)) {
            return null;
        }
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $last = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($first === false || $last === false || $first->format('Y-m-d') !== $start
            || $last->format('Y-m-d') !== $end || $last < $first
            || $first->diff($last)->days >= self::MAX_DAYS) {
            return null;
        }
        return [$first, $last];
    }

    public static function isBookable(string $start, string $end): bool
    {
        $range = self::parse($start, $end);
        return $range !== null && $range[0] >= new DateTimeImmutable('today');
    }
}
