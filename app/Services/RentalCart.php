<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\RentalCatalog;

final class RentalCart
{
    private const SESSION_KEY = 'rentals_cart';

    private string $sessionKey;

    public function __construct(?string $sessionKey = self::SESSION_KEY)
    {
        $this->sessionKey = $sessionKey ?? self::SESSION_KEY;

        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function add(string|int $itemId, int $quantity = 1, ?string $startDate = null, ?string $endDate = null): array
    {
        $id = (string) $itemId;
        $qty = max(1, $quantity);

        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        $key = $id;

        if (isset($_SESSION[$this->sessionKey][$key]) && is_array($_SESSION[$this->sessionKey][$key])) {
            $existing = $_SESSION[$this->sessionKey][$key];
            $qty = max(1, (int) ($existing['quantity'] ?? 1) + $qty);
        }

        $_SESSION[$this->sessionKey][$key] = [
            'item_id' => $id,
            'quantity' => $qty,
            'rental_start_date' => $this->normalizeDate($startDate),
            'rental_end_date' => $this->normalizeDate($endDate),
        ];

        return $_SESSION[$this->sessionKey][$key];
    }

    public function remove(string|int $itemId): void
    {
        $id = (string) $itemId;

        if (isset($_SESSION[$this->sessionKey][$id])) {
            unset($_SESSION[$this->sessionKey][$id]);
        }
    }

    public function clear(): void
    {
        $_SESSION[$this->sessionKey] = [];
    }

    public function count(): int
    {
        $total = 0;

        foreach ($this->contents() as $row) {
            $total += max(0, (int) ($row['quantity'] ?? 0));
        }

        return $total;
    }

    public function empty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $catalog = $this->catalog();
        $records = [];

        foreach ($this->contents() as $entry) {
            $itemId = (string) ($entry['item_id'] ?? '');
            if ($itemId === '') {
                continue;
            }

            $item = $catalog[$itemId] ?? null;

            if ($item === null) {
                continue;
            }

            $records[] = [
                'item_id' => $itemId,
                'quantity' => max(1, (int) ($entry['quantity'] ?? 1)),
                'rental_start_date' => $entry['rental_start_date'] ?? null,
                'rental_end_date' => $entry['rental_end_date'] ?? null,
                'name' => $item['name'] ?? 'Rental item',
                'description' => $item['description'] ?? '',
                'category' => $item['category'] ?? '',
                'image_path' => $item['image_path'] ?? '',
                'rental_rate' => (float) ($item['rental_rate'] ?? 0),
                'security_deposit' => (float) ($item['security_deposit'] ?? 0),
                'rental_unit' => $item['rental_unit'] ?? '',
                'availability_status' => $item['availability_status'] ?? '',
            ];
        }

        return $records;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function contents(): array
    {
        return is_array($_SESSION[$this->sessionKey] ?? null)
            ? $_SESSION[$this->sessionKey]
            : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        $db = app(Database::class);
        $catalog = new RentalCatalog($db);
        $items = [];

        foreach (array_merge($catalog->load()['items'] ?? [], $catalog->load()['services'] ?? []) as $item) {
            $items[(string) ($item['id'] ?? '')] = $item;
        }

        return $items;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return $date;
    }
}