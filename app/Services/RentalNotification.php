<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\View;
use App\Models\RentalMailSettings;

/** Post-commit notifications. Delivery errors never propagate into business actions. */
final class RentalNotification
{
    private readonly RentalMailSettings $settings;
    public function __construct(private readonly Database $db, private readonly RentalMailTransport $transport, private readonly View $view)
    {
        $this->settings = new RentalMailSettings($db);
    }
    public function rentalSubmitted(int $id): void
    {
        $this->notify(RentalMailEvents::RENTAL_SUBMITTED, $id);
        // Initial proof and request are one customer confirmation. Internal
        // proof-only subscribers still receive the event without duplicate mail.
        $this->notify(RentalMailEvents::PAYMENT_PROOF_RECEIVED, $id, false, RentalMailEvents::RENTAL_SUBMITTED, hash('sha256', 'initial'));
    }
    public function rentalApproved(int $id): void { $this->notify(RentalMailEvents::RENTAL_APPROVED, $id); }
    public function rentalRejected(int $id): void { $this->notify(RentalMailEvents::RENTAL_REJECTED, $id); }
    public function paymentProofReceived(int $id): void { $this->notify(RentalMailEvents::PAYMENT_PROOF_RECEIVED, $id); }
    public function paymentApproved(int $id): void { $this->notify(RentalMailEvents::PAYMENT_APPROVED, $id); }
    public function paymentRejected(int $id): void { $this->notify(RentalMailEvents::PAYMENT_REJECTED, $id); }

    /** Current review changes rental/payment together: one combined customer email. */
    public function reviewed(int $id, int $status): void
    {
        if ($status === RentalPaymentStatus::APPROVED) {
            $this->notify(RentalMailEvents::PAYMENT_APPROVED, $id);
            $this->notify(RentalMailEvents::RENTAL_APPROVED, $id, false);
        } elseif ($status === RentalPaymentStatus::REJECTED) {
            $this->notify(RentalMailEvents::PAYMENT_REJECTED, $id);
            $this->notify(RentalMailEvents::RENTAL_REJECTED, $id, false);
        }
    }
    public function configurationStatus(): array
    {
        return ['smtp' => $this->transport->isConfigured(), 'sender' => filter_var((string) config('mail.from.address', ''), FILTER_VALIDATE_EMAIL) !== false];
    }
    public function notify(string $event, int $orderId, bool $customer = true, ?string $groupOverride = null, ?string $versionOverride = null): void
    {
        try {
            if ($this->db->inTransaction()) { throw new \LogicException('Mail must run after commit.'); }
            $snapshot = $this->snapshot($event, $orderId);
            if (!$snapshot) { return; }
            $destinations = [];
            if ($customer) { $destinations[] = ['audience' => 'customer', 'email' => $snapshot['values']['customer_email'], 'id' => null]; }
            foreach ($this->settings->subscribed($event) as $r) { $destinations[] = ['audience' => 'admin', 'email' => $r['email'], 'id' => $r['id']]; }
            foreach ($destinations as $destination) {
                $template = $this->settings->template($event, $destination['audience']);
                if (!(int) $template['is_active']) { continue; }
                $group = match ($event) {
                    RentalMailEvents::RENTAL_APPROVED, RentalMailEvents::PAYMENT_APPROVED => 'review_approved',
                    RentalMailEvents::RENTAL_REJECTED, RentalMailEvents::PAYMENT_REJECTED => 'review_rejected',
                    default => $event,
                };
                $key = hash('sha256', $orderId . '|' . ($groupOverride ?? $group) . '|' . ($versionOverride ?? $snapshot['version']) . '|' . $destination['audience'] . '|' . strtolower($destination['email']));
                // A unique event/destination key registers delivery atomically. Failed
                // deliveries require explicit Admin retry; refresh never resends them.
                $this->db->statement('INSERT INTO rental_notification_deliveries (dedup_key,event_key,event_version,order_id,audience,recipient_id,recipient_email,status)
                    VALUES (?,?,?,?,?,?,?,\'pending\') ON DUPLICATE KEY UPDATE id=id',
                    [$key, $event, $snapshot['version'], $orderId, $destination['audience'], $destination['id'], $destination['email']]);
                $delivery = $this->db->selectOne('SELECT * FROM rental_notification_deliveries WHERE dedup_key=?', [$key]);
                if ($delivery && $this->claim((int) $delivery['id'], 'pending')) { $this->deliver($delivery, $template, $snapshot['values']); }
            }
        } catch (\Throwable $e) {
            $this->log($event, $orderId, 'system', 'notification_unavailable');
            error_log('Rentals email failure type=' . get_class($e) . ' source=' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
    public function retry(int $id): void
    {
        if ($this->db->inTransaction()) { throw new \LogicException('Mail must run after commit.'); }
        $d = $this->db->selectOne('SELECT * FROM rental_notification_deliveries WHERE id=?', [$id]);
        if (!$d || $d['status'] !== 'failed') { throw new \InvalidArgumentException('Only failed emails can be retried. Sent or uncertain deliveries cannot be resent.'); }
        if ($d['event_key'] === 'test') { throw new \InvalidArgumentException('Use Send test email to test again.'); }
        $snapshot = $this->snapshot($d['event_key'], (int) $d['order_id']);
        if (!$snapshot || !hash_equals($d['event_version'], $snapshot['version'])) { throw new \InvalidArgumentException('This event is no longer current. Retry was not sent.'); }
        $template = $this->settings->template($d['event_key'], $d['audience']);
        if (!(int) $template['is_active']) { throw new \InvalidArgumentException('This template is disabled.'); }
        if ($d['audience'] === 'admin') {
            $active = $this->settings->subscribed($d['event_key']);
            if (!array_filter($active, static fn ($r) => (int) $r['id'] === (int) $d['recipient_id'] && strtolower($r['email']) === strtolower($d['recipient_email']))) {
                throw new \InvalidArgumentException('The recipient is no longer active or subscribed at that address.');
            }
        } elseif (strtolower($d['recipient_email']) !== strtolower($snapshot['values']['customer_email'])) {
            throw new \InvalidArgumentException('The saved customer destination does not match the order.');
        }
        if (!$this->claim($id, 'failed')) { throw new \InvalidArgumentException('This email is already being processed.'); }
        $this->deliver($d, $template, $snapshot['values']);
    }
    private function claim(int $id, string $status): bool
    {
        return $this->db->update('UPDATE rental_notification_deliveries SET status=\'pending\',attempts=attempts+1,
            attempted_at=CURRENT_TIMESTAMP,failure_category=NULL WHERE id=? AND status=?' . ($status === 'pending' ? ' AND attempts=0' : ''), [$id, $status]) === 1;
    }
    private function deliver(array $d, array $template, array $values): void
    {
        $category = 'smtp_failure';
        try {
            if (!filter_var($d['recipient_email'], FILTER_VALIDATE_EMAIL)) { $category = 'invalid_destination'; throw new \RuntimeException(); }
            if (!$this->transport->isConfigured()) { $category = 'smtp_not_configured'; throw new \RuntimeException(); }
            $category = 'template_rendering_failed';
            $message = $this->preview($template, $values);
            if ($d['event_key'] === 'test') { $message['subject'] = '[TEST] ' . $message['subject']; }
            $category = 'smtp_failure';
            $this->transport->send($d['recipient_email'], $message['subject'], $message['text'], $message['html']);
        } catch (SmtpDeliveryUncertain $e) { $this->recordFailure($d, 'uncertain', 'smtp_acceptance_uncertain'); return; }
        catch (\Throwable $e) { $this->recordFailure($d, 'failed', $category); return; }
        // If acceptance succeeded but recording failed, leave pending: never
        // automatically retry ambiguous delivery and risk duplicate mail.
        try { $this->db->update('UPDATE rental_notification_deliveries SET status=\'sent\',sent_at=CURRENT_TIMESTAMP,failure_category=NULL WHERE id=?', [$d['id']]); }
        catch (\Throwable $e) { $this->log($d['event_key'], (int) $d['order_id'], $d['audience'], 'delivery_record_uncertain'); }
    }
    private function recordFailure(array $d, string $status, string $category): void
    {
        try { $this->db->update('UPDATE rental_notification_deliveries SET status=?,failure_category=? WHERE id=?', [$status, $category, $d['id']]); }
        catch (\Throwable $e) { /* The business transaction has already committed. */ }
        $this->log($d['event_key'], (int) $d['order_id'], $d['audience'], $category);
    }
    public function sendTest(array $template, string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { throw new \InvalidArgumentException('Enter a valid test email address.'); }
        RentalMailSettings::validateTemplate($template);
        $id = $this->db->insert('INSERT INTO rental_notification_deliveries (dedup_key,event_key,audience,recipient_email,status,attempts,attempted_at)
            VALUES (?,\'test\',?,?,\'pending\',1,CURRENT_TIMESTAMP)', [bin2hex(random_bytes(32)), $template['audience'], $email]);
        $this->deliver(['id'=>$id,'event_key'=>'test','order_id'=>0,'audience'=>$template['audience'],'recipient_email'=>$email], $template, $this->sample($template['event_key']));
        if ($this->db->selectValue('SELECT status FROM rental_notification_deliveries WHERE id=?', [$id]) !== 'sent') { throw new \InvalidArgumentException('Test email was not confirmed sent. See Delivery history and check server SMTP configuration.'); }
    }
    public function preview(array $template, ?array $values = null): array
    {
        RentalMailSettings::validateTemplate($template);
        $values ??= $this->sample($template['event_key']);
        $allowed = RentalMailEvents::placeholders($template['audience']);
        $replace = static fn (string $text): string => preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',
            static fn ($m) => in_array($m[1], $allowed, true) ? (string) ($values[$m[1]] ?? '') : '', $text) ?? '';
        $subject = trim(str_replace(["\r", "\n", "\0"], ' ', $replace($template['subject'])));
        $text = $replace($template['body']);
        $cta = $template['audience'] === 'admin' ? $values['admin_order_url'] : $values['order_status_url'];
        $data = ['subject'=>$subject,'message'=>$text,'values'=>$values,'cta'=>$cta,'audience'=>$template['audience']];
        return ['subject'=>$subject,'text'=>$text,'html'=>$this->view->partial('rentals.emails.notification',$data),
            'fragment'=>$this->view->partial('rentals.emails.content',$data)];
    }
    public function sample(string $event): array
    {
        $status = RentalPaymentStatus::label(RentalMailEvents::status($event));
        return ['customer_name'=>'Juan Dela Cruz','customer_email'=>'customer@example.test','customer_phone'=>'Sample phone',
            'order_number'=>'RENT-000123','order_status'=>$status,'payment_status'=>$status,'rental_start_date'=>'2026-11-10','rental_end_date'=>'2026-11-12',
            'subtotal'=>'₱10,000.00','security_deposit'=>'₱2,500.00','total_amount'=>'₱12,500.00','payment_method'=>'Sample bank transfer',
            'company_name'=>(string)config('app.name'),'support_email'=>(string)config('app.contact_email'),
            'order_items'=>'Sample camera × 1 · 2026-11-10 to 2026-11-12 · ₱10,000.00','rejection_reason'=>'','proof_submitted'=>'Yes',
            'admin_order_url'=>absolute_url('rentals/admin/orders'),'order_status_url'=>absolute_url('rentals/orders')];
    }
    private function snapshot(string $event, int $id): ?array
    {
        $expected = RentalMailEvents::status($event);
        $h = $this->db->selectOne('SELECT h.id,h.order_number,h.customer_name,h.customer_email,h.customer_phone,h.payment_status,
            h.payment_proof_path,h.payment_reviewed_at,h.status_token,h.subtotal,h.security_deposit,h.total_amount,p.name AS payment_method
            FROM order_header h LEFT JOIN payment_methods p ON p.id=h.payment_method_id WHERE h.id=?', [$id]);
        if (!$h || (int)$h['payment_status'] !== $expected) { return null; }
        if ($event === RentalMailEvents::PAYMENT_PROOF_RECEIVED && !$h['payment_proof_path']) { return null; }
        if ($expected !== RentalPaymentStatus::PENDING && !$h['payment_reviewed_at']) { return null; }
        $lines = $this->db->select('SELECT item_name,quantity,rental_start_date,rental_end_date,line_total FROM order_details WHERE order_header_id=? ORDER BY id', [$id]);
        $money = static fn ($amount): string => '₱' . number_format((float)$amount, 2);
        $items = array_map(static fn ($l): string => $l['item_name'].' × '.$l['quantity'].' · '.($l['rental_start_date']??'To be arranged').' to '.($l['rental_end_date']??'To be arranged').' · '.$money($l['line_total']), $lines);
        $starts = array_filter(array_column($lines,'rental_start_date')); $ends = array_filter(array_column($lines,'rental_end_date'));
        $v = ['customer_name'=>$h['customer_name'],'customer_email'=>$h['customer_email'],'customer_phone'=>$h['customer_phone']?:'Not provided',
            'order_number'=>$h['order_number'],'order_status'=>RentalPaymentStatus::label($expected),'payment_status'=>RentalPaymentStatus::label($expected),
            'rental_start_date'=>$starts?min($starts):'To be arranged','rental_end_date'=>$ends?max($ends):'To be arranged',
            'subtotal'=>$money($h['subtotal']),'security_deposit'=>$money($h['security_deposit']),'total_amount'=>$money($h['total_amount']),
            'payment_method'=>$h['payment_method']?:'Not specified','company_name'=>(string)config('app.name'),'support_email'=>(string)config('app.contact_email'),
            'order_items'=>implode("\n",$items),
            // No dedicated customer-visible reason exists. Never expose internal order notes.
            'rejection_reason'=>'','proof_submitted'=>$h['payment_proof_path']?'Yes':'No','admin_order_url'=>absolute_url('rentals/admin/orders/'.$id),
            'order_status_url'=>preg_match('/^[a-f0-9]{64}$/D',(string)$h['status_token'])?absolute_url('rentals/order-status/'.$h['status_token']):absolute_url('rentals/orders')];
        return ['values'=>$v,'version'=>hash('sha256',$event===RentalMailEvents::RENTAL_SUBMITTED?'initial':(string)$h['payment_proof_path'])];
    }
    private function log(string $event, int $id, string $audience, string $category): void
    {
        error_log('Rentals email event='.$event.' order_id='.$id.' audience='.$audience.' result='.$category);
    }
}
