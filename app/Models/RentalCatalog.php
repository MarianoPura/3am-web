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
            $categories = $this->db->select('SELECT slug AS id, name FROM rental_categories WHERE is_active = 1 ORDER BY display_order, id');
            $items = $this->db->select('SELECT i.slug AS id, i.name, c.slug AS category, i.description, i.ideal_use AS ideal_for, i.media_reference AS slot, i.availability_status, i.is_sample FROM rental_items i JOIN rental_categories c ON c.id = i.category_id WHERE i.is_active = 1 AND c.is_active = 1 ORDER BY i.display_order, i.id');
            $inclusions = $this->db->select('SELECT i.slug, n.inclusion_text FROM rental_inclusions n JOIN rental_items i ON i.id = n.rental_item_id ORDER BY n.display_order, n.id');
            $byItem = [];
            foreach ($inclusions as $row) { $byItem[$row['slug']][] = $row['inclusion_text']; }
            foreach ($items as &$item) { $item['includes'] = implode(' · ', $byItem[$item['id']] ?? []); }
            unset($item);
            return compact('categories', 'items') + ['catalogUnavailable' => false];
        } catch (\Throwable $e) {
            error_log('Rental catalogue unavailable; check database setup.');
            $samples = require BASE_PATH . '/database/seeds/rentals.php';
            foreach ($samples['items'] as &$item) { $item['is_sample'] = true; }
            unset($item);
            return $samples + ['catalogUnavailable' => true];
        }
    }

    public function find(string $slug): ?array
    {
        if ($slug === '' || strlen($slug) > 190) { return null; }
        foreach ($this->load()['items'] as $item) {
            if ($item['id'] === $slug || $item['name'] === $slug) { return $item; }
        }
        return null;
    }
}
