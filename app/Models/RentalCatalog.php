<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\RentalPaymentStatus;

final class RentalCatalog
{
    public function __construct(private readonly Database $db) {}

    public function load(): array
    {
        try {
            $categories = $this->db->select(
                "
                SELECT
                    id AS db_id,
                    COALESCE(NULLIF(slug, ''), CONCAT('category-', id)) AS id,
                    name,
                    description,
                    image_path
                FROM rental_categories
                WHERE is_active = 1
                ORDER BY name ASC, id ASC
                "
            );

            $rows = $this->db->select(
                "
                SELECT
                    i.id AS db_id,
                    COALESCE(NULLIF(i.slug, ''), CONCAT('item-', i.id)) AS id,
                    i.name,
                    c.name AS category_name,
                    COALESCE(NULLIF(c.slug, ''), CONCAT('category-', c.id)) AS category,
                    i.description,
                    i.ideal_use AS ideal_for,
                    i.image_path,
                    i.is_service,
                    i.availability_status,
                    i.rental_unit,
                    i.rental_rate,
                    i.security_deposit,
                    i.available_quantity,
                    i.sku
                FROM rental_items i
                INNER JOIN rental_categories c
                    ON c.id = i.category_id
                WHERE
                    i.is_active = 1
                    AND c.is_active = 1
                ORDER BY i.name ASC, i.id ASC
                "
            );

            $items = [];
            $services = [];

            foreach ($rows as $row) {
                /*
                 * Temporary compatibility fields for the existing Rentals views.
                 * These do NOT represent separate database columns/tables.
                 */
                $row['slot'] = '';
                $row['includes'] = '';
                $row['is_sample'] = false;

                if ((int) ($row['is_service'] ?? 0) === 1) {
                    $services[] = $row;
                } else {
                    $items[] = $row;
                }
            }

            $preview = config('rentals.preview_samples', false)
                && $categories === []
                && $rows === [];

            if ($preview) {
                $samples = require BASE_PATH . '/database/seeds/rentals.php';
                $categories = $samples['categories'] ?? [];
                $items = $samples['items'] ?? [];
                $services = $samples['services'] ?? [];

                foreach ($categories as &$category) {
                    $category['is_sample'] = true;
                }
                unset($category);

                foreach ($items as &$record) {
                    $record['is_sample'] = true;
                    $record['is_service'] = 0;
                    $record['image_path'] = $record['image_path'] ?? null;
                }
                unset($record);
                foreach ($services as &$record) {
                    $record['is_sample'] = true;
                    $record['is_service'] = 1;
                    $record['image_path'] = $record['image_path'] ?? null;
                }
                unset($record);
            }

            return [
                'categories' => $categories,
                'items' => $items,
                'services' => $services,
                'catalogUnavailable' => false,
                'catalogPreview' => $preview,
            ];
        } catch (\Throwable $e) {
            error_log('Rental catalogue unavailable; check database setup.');

            // Never present demo stock as real inventory when a live DB fails.
            $samples = config('rentals.preview_samples', false)
                ? require BASE_PATH . '/database/seeds/rentals.php'
                : [];

            $items = $samples['items'] ?? [];

            foreach ($items as &$item) {
                $item['is_sample'] = true;
                $item['is_service'] = $item['is_service'] ?? 0;
                $item['image_path'] = $item['image_path'] ?? null;
            }
            unset($item);

            return [
                'categories' => $samples['categories'] ?? [],
                'items' => $items,
                'services' => $samples['services'] ?? [],
                'catalogUnavailable' => true,
                'catalogPreview' => $samples !== [],
            ];
        }
    }

    public function find(string $slug): ?array
    {
        if ($slug === '' || strlen($slug) > 190) {
            return null;
        }

        $catalog = $this->load();
        $records = array_merge(
            $catalog['items'] ?? [],
            $catalog['services'] ?? []
        );

        foreach ($records as $item) {
            if (
                (string) ($item['id'] ?? '') === $slug ||
                (string) ($item['name'] ?? '') === $slug ||
                (string) ($item['db_id'] ?? '') === $slug
            ) {
                return $item;
            }
        }

        return null;
    }

    public function isAvailable(array $item, int $quantity, ?string $startDate, ?string $endDate): bool
    {
        if (($item['is_sample'] ?? false) === true) {
            return true;
        }

        $status = strtolower(trim((string) ($item['availability_status'] ?? '')));
        if ((int) ($item['is_service'] ?? 0) === 1
            || in_array($status, ['unavailable', 'out_of_stock', 'inactive', 'reserved'], true)
            || $quantity < 1) {
            return false;
        }

        $available = (int) ($item['available_quantity'] ?? 0);
        if ($startDate === null || $endDate === null) {
            return $quantity <= $available;
        }

        $days = $this->remainingByDate($item, $startDate, $endDate);
        if ($days === []) { return false; }
        foreach ($days as $remaining) {
            if ($remaining < $quantity) { return false; }
        }
        return true;
    }

    /** Remaining units on each date, with rejected orders excluded. */
    public function remainingByDate(array $item, string $startDate, string $endDate): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if ($start === false || $end === false || $start > $end
            || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            return [];
        }
        $events = [];
        $rows = $this->db->select(
            'SELECT d.quantity, d.rental_start_date, d.rental_end_date FROM order_details d
             JOIN order_header h ON h.id = d.order_header_id
             WHERE d.rental_item_id = ? AND h.payment_status IN (?, ?)
               AND d.rental_start_date <= ? AND d.rental_end_date >= ?',
            [(int) ($item['db_id'] ?? 0), RentalPaymentStatus::PENDING, RentalPaymentStatus::APPROVED, $endDate, $startDate]
        );
        foreach ($rows as $row) {
            $first = max($startDate, (string) $row['rental_start_date']);
            $last = min($endDate, (string) $row['rental_end_date']);
            $after = (new \DateTimeImmutable($last))->modify('+1 day')->format('Y-m-d');
            $events[$first] = ($events[$first] ?? 0) + (int) $row['quantity'];
            $events[$after] = ($events[$after] ?? 0) - (int) $row['quantity'];
        }
        $blackouts = $this->db->select(
            'SELECT start_date, end_date FROM rental_item_blackouts
             WHERE rental_item_id = ? AND is_active = 1 AND start_date <= ? AND end_date >= ?',
            [(int) ($item['db_id'] ?? 0), $endDate, $startDate]
        );
        $result = [];
        $reserved = 0;
        $available = (int) ($item['available_quantity'] ?? 0);
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $reserved += $events[$date] ?? 0;
            $blocked = false;
            foreach ($blackouts as $blackout) {
                if ($date >= $blackout['start_date'] && $date <= $blackout['end_date']) {
                    $blocked = true;
                    break;
                }
            }
            $result[$date] = $blocked ? 0 : max(0, $available - $reserved);
        }
        return $result;
    }

    /** Only portable, local public image paths are accepted by Rentals. */
    public static function imagePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || !preg_match('#^(?:(?:media|uploads)/[A-Za-z0-9_./-]+|micro/rentals/products/[a-f0-9]{32})\.(?:jpe?g|png|webp|avif)$#i', $path)
            || str_contains($path, '..') || !is_file(BASE_PATH . '/' . $path)) {
            return null;
        }
        return $path;
    }
}
