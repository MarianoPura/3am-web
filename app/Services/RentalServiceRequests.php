<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Service enquiries/quotations; never equipment stock or payment orders. */
final class RentalServiceRequests
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public function __construct(private readonly Database $db) {}

    public function ready(): bool
    {
        return (int) $this->db->selectValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['rental_service_requests']
        ) === 1;
    }

    public function service(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT i.id, i.name, i.description, i.ideal_use, i.rental_rate, i.rental_unit
             FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
             WHERE i.id = ? AND i.is_service = 1 AND i.is_active = 1 AND c.is_active = 1
               AND i.availability_status IN ('available', 'inquire')", [$id]
        );
    }

    /** Only the authenticated owner's records are returned to the customer. */
    public function history(int $userId, string $status = 'all'): array
    {
        $status = isset(self::STATUSES[$status]) ? $status : 'all';
        return $this->db->select(
            "SELECT * FROM rental_service_requests WHERE user_id = ? AND (? = 'all' OR status = ?)
             ORDER BY created_at DESC, id DESC LIMIT 200", [$userId, $status, $status]
        );
    }

    public function adminList(string $status, string $term): array
    {
        $status = isset(self::STATUSES[$status]) ? $status : 'all';
        $term = mb_substr(trim($term), 0, 100);
        return $this->db->select(
            "SELECT * FROM rental_service_requests WHERE (? = 'all' OR status = ?)
             AND (? = '' OR reference LIKE ? OR customer_name LIKE ? OR service_name LIKE ?)
             ORDER BY created_at DESC, id DESC LIMIT 200",
            [$status, $status, $term, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%']
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM rental_service_requests WHERE id = ?', [$id]);
    }

    /** Validation errors never discard the visitor's entered requirements. */
    public static function errors(array $fields): array
    {
        $errors = [];
        foreach (['phone' => [40, 'Contact number'], 'location' => [255, 'Event location'], 'details' => [5000, 'Service requirements']] as $key => [$max, $label]) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value === '') { $errors[$key] = $label . ' is required.'; }
            elseif (mb_strlen($value) > $max) { $errors[$key] = $label . ' is too long.'; }
        }
        foreach (['start_date', 'end_date'] as $key) {
            $value = (string) ($fields[$key] ?? '');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) { $errors[$key] = 'Choose a valid event date.'; }
            elseif ($value < (new \DateTimeImmutable('today'))->format('Y-m-d')) { $errors[$key] = 'Choose today or a future date.'; }
        }
        if (!isset($errors['start_date']) && !isset($errors['end_date']) && ($fields['end_date'] ?? '') < ($fields['start_date'] ?? '')) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        }
        return $errors;
    }

    public function submit(int $userId, int $serviceId, string $key, array $fields): array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) { throw new RuntimeException('Refresh the request form and try again.'); }
        if (self::errors($fields) !== []) { throw new RuntimeException('Check the highlighted request fields.'); }
        return $this->db->transaction(function (Database $db) use ($userId, $serviceId, $key, $fields): array {
            // Serialize a customer's repeated/concurrent submissions before checking the unique key.
            $user = $db->selectOne('SELECT id, name, email FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if (!$user) { throw new RuntimeException('Sign in to submit a service request.'); }
            $existing = $db->selectOne('SELECT * FROM rental_service_requests WHERE submission_key = ?', [$key]);
            if ($existing) {
                if ((int) $existing['user_id'] !== $userId || (int) $existing['rental_item_id'] !== $serviceId) {
                    throw new RuntimeException('Refresh the request form and try again.');
                }
                return $existing;
            }
            $db->selectOne('SELECT id FROM rental_items WHERE id = ? FOR UPDATE', [$serviceId]);
            $service = $this->service($serviceId);
            if (!$service) { throw new RuntimeException('This service is no longer accepting requests. Please contact support.'); }
            $id = $db->insert(
                'INSERT INTO rental_service_requests
                 (reference, submission_key, user_id, rental_item_id, service_name, customer_name, customer_email,
                  customer_phone, event_start_date, event_end_date, location, details)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                ['SRV-' . strtoupper(bin2hex(random_bytes(12))), $key, $userId, $serviceId, $service['name'],
                 $user['name'], $user['email'], trim($fields['phone']), $fields['start_date'], $fields['end_date'],
                 trim($fields['location']), trim($fields['details'])]
            );
            return $this->find($id) ?? throw new RuntimeException('Service request could not be loaded.');
        });
    }

    /** At most one existing inquiry confirmation attempt, always after commit. */
    public function notify(array $record, InquiryStore $inquiries): void
    {
        try {
            if ($this->db->inTransaction()) { throw new \LogicException('Notification must run after commit.'); }
            if ($this->db->update('UPDATE rental_service_requests SET notification_attempted_at = CURRENT_TIMESTAMP
                WHERE id = ? AND notification_attempted_at IS NULL', [(int) $record['id']]) !== 1) { return; }
            $inquiries->capture([
                'form' => 'project', 'type' => 'Media / Production',
                'name' => $record['customer_name'], 'email' => $record['customer_email'], 'phone' => $record['customer_phone'],
                'details' => 'Service request: ' . $record['reference'] . "\nService: " . $record['service_name']
                    . "\nEvent dates: " . $record['event_start_date'] . ' to ' . $record['event_end_date']
                    . "\nLocation: " . $record['location'] . "\n\n" . $record['details'],
            ]);
        } catch (\Throwable $e) {
            // The saved request remains in history; never expose SMTP/config/customer data.
            error_log('Rentals service request notification unavailable request_id=' . (int) $record['id']);
        }
    }

    public function review(int $id, int $adminId, string $decision, string $message): bool
    {
        if (!in_array($decision, ['approved', 'rejected'], true) || mb_strlen($message) > 1000) {
            throw new RuntimeException('Choose a valid decision and use at most 1,000 characters for the customer message.');
        }
        return $this->db->transaction(function (Database $db) use ($id, $adminId, $decision, $message): bool {
            $admin = $db->selectOne('SELECT role FROM users WHERE id = ?', [$adminId]);
            if (!$admin || !in_array(strtolower($admin['role']), ['admin', 'superadmin'], true)) {
                throw new RuntimeException('Administrator access is required.');
            }
            return $db->update("UPDATE rental_service_requests SET status = ?, customer_message = ?,
                reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'",
                [$decision, trim($message) !== '' ? trim($message) : null, $adminId, $id]) === 1;
        });
    }
}
