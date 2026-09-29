<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\RentalCatalog;
use RuntimeException;

/** Admin-created customer orders use the same inventory and payment review as checkout. */
final class RentalAdminReservation
{
    public function __construct(private readonly Database $db) {}

    public function create(int $itemId, array $data, ?array $proof): int
    {
        $start = (string) ($data['start_date'] ?? '');
        $end = (string) ($data['end_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $end)) {
            throw new RuntimeException('Choose valid rental dates.');
        }
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$first || !$last || $first->format('Y-m-d') !== $start || $last->format('Y-m-d') !== $end
            || $first < new \DateTimeImmutable('today') || $last < $first || $first->diff($last)->days > 365) {
            throw new RuntimeException('Choose current or future dates within a one-year rental period.');
        }
        $quantity = filter_var($data['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $email = trim((string) ($data['customer_email'] ?? ''));
        if (!$quantity || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid quantity and customer email.'); }
        foreach (['customer_phone' => 40, 'payment_reference' => 190, 'notes' => 4000] as $field => $limit) {
            if (strlen((string) ($data[$field] ?? '')) > $limit) { throw new RuntimeException('The ' . str_replace('_', ' ', $field) . ' is too long.'); }
        }
        $customer = $this->db->selectOne("SELECT id, name, email FROM users WHERE email = ? AND role = 'customer'", [$email]);
        if (!$customer) { throw new RuntimeException('Use the email of an existing customer account. Ask the customer to register first.'); }
        $method = (int) ($data['payment_method_id'] ?? 0);
        $allowed = (new RentalCheckout($this->db, new RentalCart()))->activePaymentMethods();
        if (!in_array($method, array_column($allowed, 'id'), true)) { throw new RuntimeException('Choose an available payment method.'); }
        $path = RentalPaymentProof::store($proof);
        try {
            return $this->db->transaction(function (Database $db) use ($itemId, $data, $customer, $method, $path, $first, $last, $start, $end, $quantity): int {
                $item = $db->selectOne('SELECT * FROM rental_items WHERE id = ? FOR UPDATE', [$itemId]);
                if (!$item || !(int) $item['is_active']) { throw new RuntimeException('That equipment is unavailable.'); }
                $item['db_id'] = $itemId;
                if (!(new RentalCatalog($db))->isAvailable($item, $quantity, $start, $end)) {
                    throw new RuntimeException('Not enough equipment is available for those dates. Check reservations and manual blocks.');
                }
                $allowed = (new RentalCheckout($db, new RentalCart()))->activePaymentMethods();
                if (!in_array($method, array_column($allowed, 'id'), true)) { throw new RuntimeException('That payment method is no longer available.'); }
                $days = strtolower($item['rental_unit']) === 'day' ? $first->diff($last)->days + 1 : 1;
                $subtotal = round((float) $item['rental_rate'] * $quantity * $days, 2);
                $deposit = round((float) $item['security_deposit'] * $quantity, 2);
                if ($subtotal + $deposit > 9999999999.99) { throw new RuntimeException('The order total exceeds the supported amount. Reduce the quantity or dates.'); }
                $order = $db->insert('INSERT INTO order_header (order_number,user_id,customer_name,customer_email,customer_phone,payment_method_id,payment_status,payment_proof_path,status_token,payment_reference,subtotal,security_deposit,total_amount,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    ['RNT-' . strtoupper(bin2hex(random_bytes(5))), $customer['id'], $customer['name'], $customer['email'], $data['customer_phone'] ?: null, $method, RentalPaymentStatus::PENDING, $path, bin2hex(random_bytes(32)), $data['payment_reference'] ?: null, $subtotal, $deposit, $subtotal + $deposit, $data['notes'] ?: null]);
                $db->insert('INSERT INTO order_details (order_header_id,rental_item_id,item_name,quantity,rental_start_date,rental_end_date,unit_rate,line_total) VALUES (?,?,?,?,?,?,?,?)',
                    [$order, $itemId, $item['name'], $quantity, $start, $end, $item['rental_rate'], $subtotal]);
                return $order;
            });
        } catch (\Throwable $e) { RentalPaymentProof::remove($path); throw $e; }
    }
}
