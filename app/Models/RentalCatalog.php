<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\RentalPaymentStatus;
use App\Services\RentalPagination;
use App\Services\RentalSchema;

final class RentalCatalog
{
    public function __construct(private readonly Database $db) {}
    private ?bool $hasGallery = null;

    public function load(): array
    {
        try {
            // The homepage is a preview, not an inventory export. Cart/checkout
            // use keyed lookups below and never depend on this preview's limits.
            $categories = $this->db->select($this->categorySql().' LIMIT 6', [0]);
            $items = $this->records(0, 12);
            $services = $this->records(1, 12);
            $rows = array_merge($items, $services);

            $preview = config('rentals.preview_samples', false)
                && $categories === []
                && $rows === []
                && is_readable(BASE_PATH . '/database/seeds/rentals.php');

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
                && is_readable(BASE_PATH . '/database/seeds/rentals.php')
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

        return $this->findMany([$slug])[$slug] ?? null;
    }

    /** Database-filtered public listing; classification belongs to the category. */
    public function page(bool $services = false, string $search = '', string $category = '', mixed $page = 1, mixed $perPage = 12): array
    {
        $type = $services ? 1 : 0;
        $search = mb_substr(trim($search), 0, 190);
        $category = mb_substr(trim($category), 0, 190);
        $bindings = [$type];
        $where = 'i.is_active = 1 AND c.is_active = 1 AND c.is_service = ?';
        if ($search !== '') {
            // Treat wildcard characters as text. '!' avoids sql_mode-dependent
            // backslash escaping; all browser values remain bound parameters.
            $term = '%'.strtr($search, ['!'=>'!!', '%'=>'!%', '_'=>'!_']).'%';
            $where .= " AND (i.name LIKE ? ESCAPE '!' OR i.description LIKE ? ESCAPE '!' OR i.sku LIKE ? ESCAPE '!')";
            array_push($bindings, $term, $term, $term);
        }
        if ($category !== '' && $category !== 'all') {
            $where .= " AND COALESCE(NULLIF(c.slug, ''), CONCAT('category-', c.id)) = ?";
            $bindings[] = $category;
        }
        $from = ' FROM rental_items i INNER JOIN rental_categories c ON c.id = i.category_id WHERE '.$where;
        try {
            $pagination = RentalPagination::fetch($this->db, $this->itemSelect().$from.' ORDER BY i.name ASC, i.id ASC', $bindings,
                RentalPagination::pageNumber($page), RentalPagination::catalogueSize($perPage), 'SELECT COUNT(*)'.$from);
            $records = array_map($this->normalize(...), $pagination['rows']);
            unset($pagination['rows']);
            $totals = [0=>0, 1=>0];
            foreach ($this->db->select('SELECT c.is_service, COUNT(*) AS total FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE i.is_active=1 AND c.is_active=1 AND c.is_service IN (0,1) GROUP BY c.is_service') as $row) {
                $totals[(int)$row['is_service']] = (int)$row['total'];
            }
            return ['categories'=>$this->db->select($this->categorySql(), [$type]),
                'items'=>$services?[]:$records, 'services'=>$services?$records:[],
                'catalogueTotals'=>$totals, 'pagination'=>$pagination, 'search'=>$search,
                'selectedCategory'=>$category, 'catalogUnavailable'=>false, 'catalogPreview'=>false];
        } catch (\Throwable $e) {
            error_log('Rental catalogue unavailable; check database setup.');
            return ['categories'=>[], 'items'=>[], 'services'=>[], 'catalogueTotals'=>[0=>0,1=>0],
                'pagination'=>null, 'search'=>$search, 'selectedCategory'=>$category,
                'catalogUnavailable'=>true, 'catalogPreview'=>false];
        }
    }

    public function categoryPage(mixed $page = 1, mixed $perPage = 12): array
    {
        try {
            $pagination = RentalPagination::fetch($this->db, $this->categorySql(), [0], RentalPagination::pageNumber($page), RentalPagination::catalogueSize($perPage));
            $categories = $pagination['rows'];
            unset($pagination['rows']);
            return ['categories'=>$categories, 'items'=>[], 'services'=>[], 'pagination'=>$pagination, 'catalogUnavailable'=>false];
        } catch (\Throwable $e) {
            error_log('Rental catalogue unavailable; check database setup.');
            return ['categories'=>[], 'items'=>[], 'services'=>[], 'pagination'=>null, 'catalogUnavailable'=>true];
        }
    }

    /** Fetch only cart/checkout keys. Include inactive records only for removal UI. */
    public function findMany(array $keys, bool $includeInactive = false): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn($key):bool => is_string($key) && $key!=='' && strlen($key)<=190)));
        $records = [];
        foreach (array_chunk($keys, 100) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $ids = array_map(static function(string $key):int {
                if (preg_match('/^(?:item-)?([1-9][0-9]*)$/D', $key, $match)) { return (int)$match[1]; }
                return 0;
            }, $chunk);
            $where = "(i.slug IN ($marks) OR i.id IN ($marks)) AND c.is_service IN (0,1)";
            if (!$includeInactive) { $where .= ' AND i.is_active=1 AND c.is_active=1'; }
            try {
                $rows = $this->db->select($this->itemSelect().' FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE '.$where.' ORDER BY i.name ASC, i.id ASC', [...$chunk, ...$ids]);
                foreach ($rows as $row) {
                    $row = $this->normalize($row);
                    $row['is_unavailable'] = !(bool)$row['is_active'] || !(bool)$row['category_active'];
                    foreach ([(string)$row['id'], (string)$row['db_id'], (string)$row['name']] as $key) { $records[$key] ??= $row; }
                }
                // Preserve older name-based links without making every keyed
                // lookup scan the name column or retrieve duplicate names.
                foreach ($chunk as $key) {
                    if (isset($records[$key])) { continue; }
                    $fallback = $this->db->selectOne($this->itemSelect().' FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE i.name=? AND c.is_service IN (0,1)'.($includeInactive?'':' AND i.is_active=1 AND c.is_active=1').' ORDER BY i.id ASC LIMIT 1', [$key]);
                    if ($fallback !== null) {
                        $fallback = $this->normalize($fallback);
                        $fallback['is_unavailable'] = !(bool)$fallback['is_active'] || !(bool)$fallback['category_active'];
                        $records[$key] = $fallback;
                    }
                }
            } catch (\Throwable $e) { error_log('Rental item unavailable; check database setup.'); }
        }
        return $records;
    }

    private function categorySql(): string
    {
        return "SELECT c.id AS db_id, COALESCE(NULLIF(c.slug, ''), CONCAT('category-', c.id)) AS id,
            c.name, c.description, c.image_path, COUNT(i.id) AS item_count
            FROM rental_categories c JOIN rental_items i ON i.category_id=c.id AND i.is_active=1
            WHERE c.is_active=1 AND c.is_service=?
            GROUP BY c.id, c.slug, c.name, c.description, c.image_path ORDER BY c.name ASC, c.id ASC";
    }

    private function itemSelect(): string
    {
        $this->hasGallery ??= (new RentalSchema($this->db))->hasColumn('rental_items', 'additional_image_paths');
        $gallery = $this->hasGallery ? 'i.additional_image_paths' : 'NULL AS additional_image_paths';
        return "SELECT i.id AS db_id, COALESCE(NULLIF(i.slug, ''), CONCAT('item-', i.id)) AS id,
            i.category_id, i.name, i.slug, c.name AS category_name,
            COALESCE(NULLIF(c.slug, ''), CONCAT('category-', c.id)) AS category,
            i.description, i.ideal_use AS ideal_for, i.image_path, $gallery, c.is_service,
            i.availability_status, i.rental_unit, i.rental_rate, i.security_deposit,
            i.available_quantity, i.sku, i.is_active, c.is_active AS category_active";
    }

    private function records(int $type, int $limit): array
    {
        return array_map($this->normalize(...), $this->db->select($this->itemSelect().' FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE i.is_active=1 AND c.is_active=1 AND c.is_service=? ORDER BY i.name ASC, i.id ASC LIMIT '.max(1,min(12,$limit)), [$type]));
    }

    private function normalize(array $row): array
    {
        return $row + ['slot'=>'', 'includes'=>'', 'is_sample'=>false];
    }

    public function isAvailable(array $item, int $quantity, ?string $startDate, ?string $endDate): bool
    {
        if (($item['is_sample'] ?? false) === true) {
            return true;
        }

        $status = strtolower(trim((string) ($item['availability_status'] ?? '')));
        if ((int) ($item['is_service'] ?? 0) === 1
            || $status !== 'available'
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

    /** Check the peak requested quantity on each day, not the sum of disjoint ranges. */
    public function isAvailableForLines(array $item, array $lines, string $start, string $end): bool
    {
        if (($item['is_sample'] ?? false) || !$this->isAvailable($item, 1, null, null)) { return false; }
        $remaining = $this->remainingByDate($item, $start, $end);
        if ($remaining === []) { return false; }
        foreach ($remaining as $date => $units) {
            $requested = 0;
            foreach ($lines as $line) {
                if ((int) ($line['db_id'] ?? $line['rental_item_id'] ?? 0) === (int) $item['db_id']
                    && (string) $line['rental_start_date'] <= $date && (string) $line['rental_end_date'] >= $date) {
                    $requested += (int) $line['quantity'];
                }
            }
            if ($requested < 1 || $requested > $units) { return false; }
        }
        return true;
    }

    /** Remaining units on each date, with rejected orders excluded. */
    public function remainingByDate(array $item, string $startDate, string $endDate): array
    {
        return array_map(static fn (array $day): int => $day['remaining'], $this->availabilityByDate($item, $startDate, $endDate));
    }

    /** Stock and its causes, without customer identities, for both calendars. */
    public function availabilityByDate(array $item, string $startDate, string $endDate): array
    {
        $range = \App\Services\RentalDateRange::parse($startDate, $endDate);
        if ($range === null) { return []; }
        [$start, $end] = $range;
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
            $result[$date] = ['remaining' => $blocked ? 0 : max(0, $available - $reserved),
                'reserved' => $reserved, 'admin_blocked' => $blocked];
        }
        return $result;
    }

    /** Only portable, local public image paths are accepted by Rentals. */
    public static function imagePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if (str_starts_with($path, 'micro/')) {
            return \App\Services\RentalManagedImage::publicPath($path, 'product');
        }
        if ($path === '' || !preg_match('#^(?:(?:media|uploads)/[A-Za-z0-9_./-]+|micro/rentals/products/[a-f0-9]{32})\.(?:jpe?g|png|webp|avif)$#i', $path)
            || str_contains($path, '..') || !is_file(BASE_PATH . '/' . $path)) {
            return null;
        }
        return $path;
    }

    /** Serve managed product images through Rentals, never the reserved /micro URL. */
    public static function imageUrl(string $validPath): string
    {
        if (str_starts_with($validPath, 'micro/rentals/products/')) {
            return url('rentals/product-image/' . basename($validPath));
        }
        return site_media($validPath) ?? '';
    }
}
