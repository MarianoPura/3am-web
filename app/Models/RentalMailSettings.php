<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use App\Services\RentalMailEvents;
use InvalidArgumentException;

final class RentalMailSettings
{
    public function __construct(private readonly Database $db) {}

    public function template(string $event, string $audience): array
    {
        $defaults = RentalMailEvents::defaults($event, $audience);
        return $this->db->selectOne('SELECT * FROM rental_email_templates WHERE event_key = ? AND audience = ?', [$event, $audience]) ?? $defaults;
    }

    public function templates(): array
    {
        $rows = $this->db->select('SELECT * FROM rental_email_templates');
        $saved = [];
        foreach ($rows as $row) { $saved[$row['event_key'] . ':' . $row['audience']] = $row; }
        $result = [];
        foreach (RentalMailEvents::labels() as $event => $label) {
            foreach (['customer', 'admin'] as $audience) { $result[] = $saved[$event . ':' . $audience] ?? RentalMailEvents::defaults($event, $audience); }
        }
        return $result;
    }

    public static function validateTemplate(array $template): void
    {
        RentalMailEvents::defaults((string) $template['event_key'], (string) $template['audience']);
        if (trim((string) $template['subject']) === '' || mb_strlen($template['subject']) > 200
            || preg_match('/[\r\n\x00]/', $template['subject'])) { throw new InvalidArgumentException('Enter a subject of up to 200 characters without line breaks.'); }
        if (trim((string) $template['body']) === '' || mb_strlen($template['body']) > 20000 || strlen($template['body']) > 65000
            || str_contains($template['body'], "\0")) { throw new InvalidArgumentException('Enter a message of up to 20,000 characters.'); }
        if (trim((string) $template['display_name']) === '' || mb_strlen($template['display_name']) > 150) { throw new InvalidArgumentException('Enter a template name of up to 150 characters.'); }
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $template['subject'] . $template['body'], $matches);
        foreach ($matches[1] as $variable) {
            if (!in_array(trim($variable), RentalMailEvents::placeholders($template['audience']), true)) {
                throw new InvalidArgumentException('Unsupported variable: ' . mb_substr(trim($variable), 0, 60));
            }
        }
    }

    public function saveTemplate(array $template): void
    {
        self::validateTemplate($template);
        $this->db->statement('INSERT INTO rental_email_templates (event_key,audience,display_name,subject,body,is_active)
            VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), subject=VALUES(subject), body=VALUES(body), is_active=VALUES(is_active)',
            [$template['event_key'], $template['audience'], $template['display_name'], $template['subject'], $template['body'], (int) $template['is_active']]);
    }

    public function recipients(): array
    {
        $rows = $this->db->select('SELECT * FROM rental_notification_recipients ORDER BY name,id');
        foreach ($rows as &$row) {
            $row['events'] = array_column($this->db->select('SELECT event_key FROM rental_notification_recipient_events WHERE recipient_id = ?', [$row['id']]), 'event_key');
        }
        return $rows;
    }

    public function subscribed(string $event): array
    {
        return $this->db->select('SELECT r.id,r.email FROM rental_notification_recipients r
            JOIN rental_notification_recipient_events e ON e.recipient_id=r.id WHERE r.is_active=1 AND e.event_key=?', [$event]);
    }

    public function saveRecipient(int $id, string $name, string $email, bool $active, array $events): int
    {
        $email = strtolower(trim($email));
        if ($name === '' || mb_strlen($name) > 150) { throw new InvalidArgumentException('Enter a recipient name of up to 150 characters.'); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { throw new InvalidArgumentException('Enter a valid recipient email address.'); }
        foreach ($events as $event) {
            if (!is_string($event) || !isset(RentalMailEvents::labels()[$event])) { throw new InvalidArgumentException('Choose valid email events.'); }
        }
        return $this->db->transaction(function (Database $db) use ($id, $name, $email, $active, $events): int {
            if ($id > 0 && !$db->selectOne('SELECT id FROM rental_notification_recipients WHERE id=? FOR UPDATE', [$id])) { throw new InvalidArgumentException('That recipient no longer exists.'); }
            if ($db->selectOne('SELECT id FROM rental_notification_recipients WHERE email=? AND id<>?', [$email, $id])) { throw new InvalidArgumentException('That recipient email already exists.'); }
            if ($id > 0) { $db->update('UPDATE rental_notification_recipients SET name=?,email=?,is_active=? WHERE id=?', [$name, $email, (int) $active, $id]); }
            else { $id = $db->insert('INSERT INTO rental_notification_recipients (name,email,is_active) VALUES (?,?,?)', [$name, $email, (int) $active]); }
            $db->delete('DELETE FROM rental_notification_recipient_events WHERE recipient_id=?', [$id]);
            foreach (array_unique($events) as $event) { $db->insert('INSERT INTO rental_notification_recipient_events (recipient_id,event_key) VALUES (?,?)', [$id, $event]); }
            return $id;
        });
    }

    public function removeRecipient(int $id): void
    {
        $this->db->delete('DELETE FROM rental_notification_recipients WHERE id=?', [$id]);
    }

    public function history(int $page): array
    {
        $offset = (max(1, min(10000, $page)) - 1) * 50;
        return $this->db->select('SELECT d.id,d.event_key,d.audience,d.recipient_email,d.status,d.attempts,d.failure_category,
            d.attempted_at,d.sent_at,d.created_at,d.order_id,h.order_number FROM rental_notification_deliveries d
            LEFT JOIN order_header h ON h.id=d.order_id ORDER BY d.id DESC LIMIT 51 OFFSET ' . $offset);
    }
}
