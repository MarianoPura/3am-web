<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\RentalCatalog;
use RuntimeException;

final class RentalCheckout
{
    public function __construct(
        private readonly Database $db,
        private readonly RentalCart $cart,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, subtotal: float, security_deposit: float, total: float, preview: bool}
     */
    public function summary(): array
    {
        $catalog = new RentalCatalog($this->db);
        $items = [];
        $subtotal = 0.0;
        $securityDeposit = 0.0;
        $preview = false;

        foreach ($this->cart->items() as $entry) {
            $item = $catalog->find((string) ($entry['item_id'] ?? ''));
            if ($item === null) {
                continue;
            }

            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $rate = (float) ($item['rental_rate'] ?? 0);
            $deposit = (float) ($item['security_deposit'] ?? 0);
            $start = (string) ($entry['rental_start_date'] ?? '');
            $end = (string) ($entry['rental_end_date'] ?? '');
            $startDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            $endDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
            $days = ($startDay !== false && $endDay !== false && strtolower((string) ($item['rental_unit'] ?? '')) === 'day')
                ? max(1, $startDay->diff($endDay)->days + 1)
                : 1;
            $lineTotal = round($rate * $quantity * $days, 2);
            $lineDeposit = $deposit * $quantity;
            $preview = $preview || (($item['is_sample'] ?? false) === true);
            $subtotal += $lineTotal;
            $securityDeposit += $lineDeposit;

            $items[] = [
                'line_id' => (string) ($entry['line_id'] ?? ''),
                'item_id' => (string) ($entry['item_id'] ?? ''),
                'db_id' => (int) ($item['db_id'] ?? 0),
                'name' => (string) ($item['name'] ?? 'Rental item'),
                'quantity' => $quantity,
                'rental_start_date' => $entry['rental_start_date'] ?? null,
                'rental_end_date' => $entry['rental_end_date'] ?? null,
                'unit_rate' => $rate,
                'line_total' => $lineTotal,
                'security_deposit' => $lineDeposit,
                'rental_unit' => (string) ($item['rental_unit'] ?? ''),
                'is_sample' => (($item['is_sample'] ?? false) === true),
            ];
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'security_deposit' => $securityDeposit,
            'total' => $subtotal + $securityDeposit,
            'preview' => $preview,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function itemsFromDatabaseCart(): array
    {
        return $this->cart->items();
    }

    /** @return list<array<string, mixed>> */
    public function activePaymentMethods(): array
    {
        $local = in_array(config('app.env'), ['local', 'testing'], true) ? 1 : 0;
        return $this->db->select(
            "SELECT id, name, type, account_name, account_number, provider, qr_image_path
             FROM payment_methods
             WHERE is_active = 1 AND ((type = 'manual' AND NULLIF(TRIM(account_name), '') IS NOT NULL
                 AND NULLIF(TRIM(account_number), '') IS NOT NULL) OR (type = 'test' AND ? = 1))
             ORDER BY name ASC, id ASC",
            [$local]
        );
    }

    /**
     * Create an order only for an authenticated user and real database items.
     * Browser totals are deliberately ignored.
     *
     * @param array{name: string, email: string, phone: string, payment_method_id: int|null, notes: string} $customer
     * @return array{order_number: string, status_token: string, customer_email: string}
     */
    public function createOrder(int $userId, array $customer, ?array $proofUpload = null): array
    {
        $account = $userId > 0 ? $this->db->selectOne('SELECT id, email FROM users WHERE id = ?', [$userId]) : null;
        if ($account === null) {
            throw new RuntimeException('An authenticated customer account is required.');
        }
        $customer['email'] = (string) $account['email'];

        $summary = $this->summary();
        if ($summary['items'] === [] || $summary['preview']
            || count($summary['items']) !== count($this->cart->contents())) {
            throw new RuntimeException('Your cart contains unavailable or preview items.');
        }

        $payments = $this->activePaymentMethods();
        $paymentId = $customer['payment_method_id'];
        if ($paymentId === null || !array_filter($payments, static fn (array $payment): bool => (int) $payment['id'] === $paymentId)) {
            throw new RuntimeException('The selected payment method is unavailable.');
        }
        if (trim($customer['name']) === '' || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Enter your name and a valid email address.');
        }

        $catalog = new RentalCatalog($this->db);
        foreach ($summary['items'] as $item) {
            $start = $item['rental_start_date'];
            $end = $item['rental_end_date'];
            if (!is_string($start) || !is_string($end) || $start === '' || $end === '') {
                throw new RuntimeException('Rental start and end dates are required.');
            }
            $startDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            $endDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
            if ($startDay === false || $endDay === false
                || $startDay->format('Y-m-d') !== $start
                || $endDay->format('Y-m-d') !== $end
                || $startDay < new \DateTimeImmutable('today')
                || $endDay < $startDay) {
                throw new RuntimeException('Choose valid current or future rental dates.');
            }

            $record = $catalog->find((string) $item['item_id']);
            if ($record === null || !$catalog->isAvailable($record, (int) $item['quantity'], $start, $end)) {
                throw new RuntimeException('One or more rental items are no longer available for those dates.');
            }
        }

        $orderNumber = 'RNT-' . strtoupper(bin2hex(random_bytes(5)));
        $statusToken = bin2hex(random_bytes(32));
        $proofPath = RentalPaymentProof::store($proofUpload);

        try {
            $this->db->transaction(function (Database $db) use ($customer, $paymentId, $summary, $userId, $orderNumber, $statusToken, $proofPath): void {
            // Serialize requests for the same equipment before checking availability and rates.
            $cartRow = $db->selectOne('SELECT id FROM carts WHERE user_id = ? FOR UPDATE', [$userId]);
            if ($cartRow === null) {
                throw new RuntimeException('Your cart is empty.');
            }
            $db->select('SELECT id FROM cart_items WHERE cart_id = ? FOR UPDATE', [(int) $cartRow['id']]);
            $itemIds = array_unique(array_column($summary['items'], 'db_id'));
            sort($itemIds, SORT_NUMERIC);
            foreach ($itemIds as $itemId) {
                $db->selectOne('SELECT id FROM rental_items WHERE id = ? FOR UPDATE', [(int) $itemId]);
            }
            $summary = $this->summary();
            if ($summary['items'] === [] || $summary['preview']
                || count($summary['items']) !== count($this->cart->contents())) {
                throw new RuntimeException('Your cart contains unavailable or preview items.');
            }
            $local = in_array(config('app.env'), ['local', 'testing'], true) ? 1 : 0;
            if ($db->selectOne("SELECT id FROM payment_methods WHERE id = ? AND is_active = 1 AND ((type = 'manual' AND NULLIF(TRIM(account_name), '') IS NOT NULL AND NULLIF(TRIM(account_number), '') IS NOT NULL) OR (type = 'test' AND ? = 1))", [$paymentId, $local]) === null) {
                throw new RuntimeException('The selected payment method is unavailable.');
            }
            $catalog = new RentalCatalog($db);
            foreach ($summary['items'] as $line) {
                $item = $catalog->find((string) $line['item_id']);
                if ($item === null) {
                    throw new RuntimeException('A rental item is no longer available.');
                }
                $remaining = $catalog->remainingByDate($item, $line['rental_start_date'], $line['rental_end_date']);
                $available = $catalog->isAvailable($item, 1, $line['rental_start_date'], $line['rental_end_date']);
                foreach ($remaining as $date => $units) {
                    $requested = 0;
                    foreach ($summary['items'] as $other) {
                        if ($other['db_id'] === $line['db_id']
                            && $other['rental_start_date'] <= $date
                            && $other['rental_end_date'] >= $date) {
                            $requested += (int) $other['quantity'];
                        }
                    }
                    if ($requested > $units) { $available = false; break; }
                }
                if (!$available || $remaining === []) {
                    throw new RuntimeException('One or more rental items are no longer available for those dates.');
                }
            }
            $orderId = $db->insert(
                'INSERT INTO order_header
                    (order_number, user_id, customer_name, customer_email, customer_phone,
                     payment_method_id, payment_status, payment_proof_path, status_token,
                     payment_reference, subtotal, security_deposit, total_amount, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderNumber,
                    $userId,
                    $customer['name'],
                    $customer['email'],
                    $customer['phone'] !== '' ? $customer['phone'] : null,
                    $paymentId,
                    RentalPaymentStatus::PENDING,
                    $proofPath,
                    $statusToken,
                    $customer['payment_reference'] ?? null,
                    $summary['subtotal'],
                    $summary['security_deposit'],
                    $summary['total'],
                    $customer['notes'] !== '' ? $customer['notes'] : null,
                ]
            );

            foreach ($summary['items'] as $item) {
                $db->insert(
                    'INSERT INTO order_details
                        (order_header_id, rental_item_id, item_name, quantity,
                         rental_start_date, rental_end_date, unit_rate, line_total)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        $item['db_id'],
                        $item['name'],
                        $item['quantity'],
                        $item['rental_start_date'],
                        $item['rental_end_date'],
                        $item['unit_rate'],
                        $item['line_total'],
                    ]
                );
            }
            $this->cart->clear();
            });
        } catch (\Throwable $e) {
            RentalPaymentProof::remove($proofPath);
            throw $e;
        }

        return ['order_number' => $orderNumber, 'status_token' => $statusToken, 'customer_email' => $customer['email']];
    }
}
