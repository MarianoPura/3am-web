<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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

            return [
                'categories' => $categories,
                'items' => $items,
                'services' => $services,
                'catalogUnavailable' => false,
            ];
        } catch (\Throwable $e) {
            error_log('Rental catalogue unavailable; check database setup.');

            $samples = require BASE_PATH . '/database/seeds/rentals.php';

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
                (string) ($item['name'] ?? '') === $slug
            ) {
                return $item;
            }
        }

        return null;
    }
}