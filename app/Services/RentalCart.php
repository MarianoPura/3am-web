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
        $qty = min(999, max(1, $quantity));
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if ($this->userId() !== null) {
            $cartId = $this->cartId();
            $catalogItem = (new RentalCatalog($this->db()))->find($id);
            $databaseItemId = (int) ($catalogItem['db_id'] ?? 0);
            if ($databaseItemId < 1) {
                return [];
            }

            $existing = $this->db()->selectOne(
                'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND rental_item_id = ? AND rental_start_date <=> ? AND rental_end_date <=> ?',
                [$cartId, $databaseItemId, $start, $end]
            );
            if ($existing !== null) {
                $this->db()->update(
                    'UPDATE cart_items SET quantity = LEAST(999, quantity + ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$qty, (int) $existing['id']]
                );
                return ['line_id' => (string) $existing['id'], 'item_id' => $id, 'quantity' => min(999, (int) $existing['quantity'] + $qty)];
            }

            $lineId = $this->db()->insert(
                'INSERT INTO cart_items (cart_id, rental_item_id, quantity, rental_start_date, rental_end_date) VALUES (?, ?, ?, ?, ?)',
                [$cartId, $databaseItemId, $qty, $start, $end]
            );
            return ['line_id' => (string) $lineId, 'item_id' => $id, 'quantity' => $qty];
        }

        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        $key = $this->lineKey($id, $start, $end);

        foreach ($this->contents() as $existingKey => $existing) {
            if ((string) ($existing['item_id'] ?? '') === $id
                && $this->normalizeDate($existing['rental_start_date'] ?? null) === $start
                && $this->normalizeDate($existing['rental_end_date'] ?? null) === $end) {
                $key = (string) $existingKey;
                break;
            }
        }

        if (isset($_SESSION[$this->sessionKey][$key]) && is_array($_SESSION[$this->sessionKey][$key])) {
            $existing = $_SESSION[$this->sessionKey][$key];
            $qty = min(999, max(1, (int) ($existing['quantity'] ?? 1) + $qty));
        }

        $_SESSION[$this->sessionKey][$key] = [
            'line_id' => $key,
            'item_id' => $id,
            'quantity' => $qty,
            'rental_start_date' => $start,
            'rental_end_date' => $end,
        ];

        return $_SESSION[$this->sessionKey][$key];
    }

    public function remove(string|int $itemId): void
    {
        $id = (string) $itemId;

        if ($this->userId() !== null) {
            $cart = $this->db()->selectOne('SELECT id FROM carts WHERE user_id = ? ORDER BY id ASC LIMIT 1', [$this->userId()]);
            if ($cart !== null) {
                $this->db()->delete('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [(int) $id, (int) $cart['id']]);
            }
            return;
        }

        if (isset($_SESSION[$this->sessionKey][$id])) {
            unset($_SESSION[$this->sessionKey][$id]);
            return;
        }

        foreach ($this->contents() as $key => $entry) {
            if ((string) ($entry['item_id'] ?? '') === $id) {
                unset($_SESSION[$this->sessionKey][$key]);
            }
        }
    }

    public function update(string|int $itemId, int $quantity, ?string $startDate = null, ?string $endDate = null): void
    {
        $id = (string) $itemId;

        if ($this->userId() !== null) {
            $cartId = $this->cartId();
            $start = $this->normalizeDate($startDate);
            $end = $this->normalizeDate($endDate);
            $line = $this->db()->selectOne('SELECT rental_item_id FROM cart_items WHERE id = ? AND cart_id = ?', [(int) $id, $cartId]);
            if ($line === null) { return; }
            $match = $this->db()->selectOne('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND rental_item_id = ? AND rental_start_date <=> ? AND rental_end_date <=> ? AND id <> ?', [$cartId, (int) $line['rental_item_id'], $start, $end, (int) $id]);
            $this->db()->transaction(function (Database $db) use ($match, $cartId, $id, $quantity, $start, $end): void {
                if ($match !== null) {
                    $db->update('UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND cart_id = ?', [min(999, (int) $match['quantity'] + max(1, $quantity)), (int) $match['id'], $cartId]);
                    $db->delete('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [(int) $id, $cartId]);
                } else {
                    $db->update('UPDATE cart_items SET quantity = ?, rental_start_date = ?, rental_end_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND cart_id = ?', [min(999, max(1, $quantity)), $start, $end, (int) $id, $cartId]);
                }
            });
            return;
        }

        if (!isset($_SESSION[$this->sessionKey][$id])) {
            return;
        }

        $existing = $_SESSION[$this->sessionKey][$id];
        $itemKey = (string) ($existing['item_id'] ?? $id);
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);
        $newKey = $this->lineKey($itemKey, $start, $end);
        $updated = [
            'line_id' => $newKey,
            'item_id' => $itemKey,
            'quantity' => min(999, max(1, $quantity)),
            'rental_start_date' => $start,
            'rental_end_date' => $end,
        ];

        unset($_SESSION[$this->sessionKey][$id]);

        if (isset($_SESSION[$this->sessionKey][$newKey])) {
            $_SESSION[$this->sessionKey][$newKey]['quantity'] = min(
                999,
                max(1, (int) ($_SESSION[$this->sessionKey][$newKey]['quantity'] ?? 0) + $updated['quantity'])
            );
            return;
        }

        $_SESSION[$this->sessionKey][$newKey] = $updated;
    }

    public function quantityFor(string|int $itemId, ?string $startDate, ?string $endDate, ?string $excludeLineId = null): int
    {
        $total = 0;
        $id = (string) $itemId;
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        foreach ($this->contents() as $entryKey => $entry) {
            if ($excludeLineId !== null && (string) ($entry['line_id'] ?? $entryKey) === $excludeLineId) {
                continue;
            }

            $entryStart = $this->normalizeDate($entry['rental_start_date'] ?? null);
            $entryEnd = $this->normalizeDate($entry['rental_end_date'] ?? null);
            $overlaps = $start !== null && $end !== null && $entryStart !== null && $entryEnd !== null
                ? $entryStart <= $end && $entryEnd >= $start
                : $entryStart === $start && $entryEnd === $end;
            if ((string) ($entry['item_id'] ?? '') === $id && $overlaps) {
                $total += max(0, (int) ($entry['quantity'] ?? 0));
            }
        }

        return $total;
    }

    public function clear(): void
    {
        if ($this->userId() !== null) {
            $cart = $this->db()->selectOne(
                'SELECT id FROM carts WHERE user_id = ? ORDER BY id ASC LIMIT 1',
                [$this->userId()]
            );
            if ($cart !== null) {
                $this->db()->delete('DELETE FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]);
            }
        }

        $_SESSION[$this->sessionKey] = [];
    }

    public function migrateToDatabase(): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            return;
        }

        $guestContents = is_array($_SESSION[$this->sessionKey] ?? null)
            ? $_SESSION[$this->sessionKey]
            : [];

        $cartId = $this->cartId();
        foreach ($guestContents as $entry) {
            $itemId = (int) ($entry['item_id'] ?? 0);
            $catalogItem = (new RentalCatalog($this->db()))->find((string) ($entry['item_id'] ?? ''));
            $itemId = (int) ($catalogItem['db_id'] ?? $itemId);
            $start = $this->normalizeDate($entry['rental_start_date'] ?? null);
            $end = $this->normalizeDate($entry['rental_end_date'] ?? null);
            $quantity = min(999, max(1, (int) ($entry['quantity'] ?? 1)));
            if ($itemId < 1) {
                continue;
            }

            $existing = $this->db()->selectOne(
                'SELECT id, quantity FROM cart_items WHERE cart_id = ? AND rental_item_id = ? AND rental_start_date <=> ? AND rental_end_date <=> ?',
                [$cartId, $itemId, $start, $end]
            );

            if ($existing !== null) {
                $this->db()->update(
                    'UPDATE cart_items SET quantity = LEAST(999, quantity + ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$quantity, (int) $existing['id']]
                );
            } else {
                $this->db()->insert(
                    'INSERT INTO cart_items (cart_id, rental_item_id, quantity, rental_start_date, rental_end_date) VALUES (?, ?, ?, ?, ?)',
                    [$cartId, $itemId, $quantity, $start, $end]
                );
            }
        }

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

        foreach ($this->contents() as $entryKey => $entry) {
            $itemId = (string) ($entry['item_id'] ?? '');
            if ($itemId === '') {
                continue;
            }

            $item = $catalog[$itemId] ?? null;
            if ($item === null) {
                // Keep deactivated equipment visible so a customer can remove it.
                $row = $this->db()->selectOne(
                    'SELECT i.*, c.name AS category_name, COALESCE(NULLIF(c.slug, \'\'), CONCAT(\'category-\', c.id)) AS category_slug
                     FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
                     WHERE i.slug = ? OR i.id = ? LIMIT 1',
                    [$itemId, ctype_digit($itemId) ? (int) $itemId : 0]
                );
                if ($row === null) { continue; }
                $item = $row;
                $item['category'] = $row['category_slug'];
                $item['is_unavailable'] = true;
            }

            $records[] = [
                'item_id' => $itemId,
                'line_id' => (string) ($entry['line_id'] ?? $entryKey),
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
                'is_sample' => ($item['is_sample'] ?? false) === true,
                'is_unavailable' => (bool) ($item['is_unavailable'] ?? false),
            ];
        }

        return $records;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function contents(): array
    {
        if ($this->userId() !== null) {
            $cart = $this->db()->selectOne(
                'SELECT id FROM carts WHERE user_id = ? ORDER BY id ASC LIMIT 1',
                [$this->userId()]
            );
            if ($cart === null) {
                return [];
            }

            $rows = $this->db()->select(
                'SELECT ci.id, ci.rental_item_id, ci.quantity, ci.rental_start_date, ci.rental_end_date,
                        COALESCE(NULLIF(i.slug, \'\'), CAST(i.id AS CHAR)) AS item_key
                 FROM cart_items ci
                 INNER JOIN rental_items i ON i.id = ci.rental_item_id
                 WHERE ci.cart_id = ?
                 ORDER BY ci.id ASC',
                [(int) $cart['id']]
            );
            $contents = [];
            foreach ($rows as $row) {
                $lineId = (string) $row['id'];
                $contents[$lineId] = [
                    'line_id' => $lineId,
                    'item_id' => (string) $row['item_key'],
                    'quantity' => (int) $row['quantity'],
                    'rental_start_date' => $row['rental_start_date'],
                    'rental_end_date' => $row['rental_end_date'],
                ];
            }
            return $contents;
        }

        return is_array($_SESSION[$this->sessionKey] ?? null)
            ? $_SESSION[$this->sessionKey]
            : [];
    }

    private function userId(): ?int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        if ($this->db()->selectOne('SELECT id FROM users WHERE id = ?', [$userId]) === null) {
            unset($_SESSION['user_id']);
            return null;
        }

        return $userId;
    }

    private function db(): Database
    {
        return app(Database::class);
    }

    private function cartId(): int
    {
        $userId = $this->userId();
        if ($userId === null) {
            throw new \RuntimeException('An authenticated customer account is required.');
        }

        $cart = $this->db()->selectOne('SELECT id FROM carts WHERE user_id = ? ORDER BY id ASC LIMIT 1', [$userId]);
        if ($cart !== null) {
            return (int) $cart['id'];
        }

        return $this->db()->insert('INSERT INTO carts (user_id) VALUES (?)', [$userId]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        $db = app(Database::class);
        $catalog = new RentalCatalog($db);
        $items = [];

        $data = $catalog->load();
        foreach (array_merge($data['items'] ?? [], $data['services'] ?? []) as $item) {
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

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year) ? $date : null;
    }

    private function lineKey(string $itemId, ?string $startDate, ?string $endDate): string
    {
        return $itemId . '|' . ($startDate ?? '') . '|' . ($endDate ?? '');
    }
}
