<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\View;

/** Fixed post-commit notifications. SMTP failure never changes business state. */
final class RentalNotification
{
    public function __construct(
        private readonly Database $db,
        private readonly RentalMailTransport $transport,
        private readonly View $view
    ) {}

    public function rentalSubmitted(int $id): void
    {
        $this->notify(RentalMailEvents::RENTAL_SUBMITTED, $id);
    }

    // Replacement proof remains a separate actual upload event, not another
    // rental/payment approval workflow.
    public function paymentProofReceived(int $id): void
    {
        $this->notify(RentalMailEvents::PAYMENT_PROOF_RECEIVED, $id);
    }

    public function reviewed(int $id, int $status): void
    {
        if ($status === RentalPaymentStatus::APPROVED) {
            $this->notify(RentalMailEvents::RENTAL_APPROVED, $id);
        } elseif ($status === RentalPaymentStatus::REJECTED) {
            $this->notify(RentalMailEvents::RENTAL_REJECTED, $id);
        }
    }


    /** Services use the same fixed transport, configured CC and durable delivery ledger. */
    public function serviceUpdated(int $id,string $event): void
    {
        $events=[
            'approved'=>['Service request approved','Your service enquiry is approved for coordination. A quotation will appear in your request details.'],
            'rejected'=>['Service request rejected','The team could not accommodate this service request.'],
            'quoted'=>['Service quotation ready','Your service quotation is ready. Sign in to view the scope, amount and payment instructions.'],
            'payment_received'=>['Service payment proof received','Your service payment proof is awaiting verification.'],
            'payment_approved'=>['Service payment approved','Your service payment proof has been approved.'],
            'payment_rejected'=>['Service payment needs attention','Your service payment proof was rejected. Sign in to view the team message and submit a replacement if needed.'],
            'completed'=>['Service completed','The team marked your service as completed. Thank you for working with 3AM.'],
            'cancelled'=>['Service cancelled','Your service request was cancelled. If you already paid, contact the team to arrange any applicable refund.'],
        ];
        if(!isset($events[$event])) { return; }
        try {
            if($this->db->inTransaction()) { throw new \LogicException('Mail must run after commit.'); }
            if (!(new RentalServiceRequests($this->db))->workflowReady() || !(new RentalSchema($this->db))->hasColumn('rental_notification_deliveries','service_request_id')) { return; }
            $r=$this->db->selectOne('SELECT * FROM rental_service_requests WHERE id=?',[$id]);
            if(!$r) { return; }
            $matches=match($event) {
                'quoted'=>$r['status']==='approved' && $r['quote_amount']!==null,
                'payment_received'=>$r['status']==='approved' && $r['payment_status']==='pending',
                'payment_approved','payment_rejected'=>$r['status']==='approved' && $r['payment_status']===substr($event,8),
                default=>$r['status']===$event,
            };
            if(!$matches) { return; }
            $version=hash('sha256',$event==='quoted'?(string)$r['quote_version']:(str_starts_with($event,'payment_')?(string)$r['payment_proof_path']:'initial'));
            $key=hash('sha256','service|'.$id.'|'.$event.'|'.$version.'|'.strtolower($r['customer_email']));
            $eventKey='service_'.$event;
            $this->db->statement("INSERT INTO rental_notification_deliveries (dedup_key,event_key,event_version,service_request_id,audience,recipient_email,status) VALUES (?,?,?,?,'customer',?,'pending') ON DUPLICATE KEY UPDATE id=id",[$key,$eventKey,$version,$id,$r['customer_email']]);
            $delivery=$this->db->selectOne('SELECT * FROM rental_notification_deliveries WHERE dedup_key=?',[$key]);
            if(!$delivery || !$this->claim((int)$delivery['id'])) { return; }
            $amount=$r['quote_amount']!==null?'₱'.number_format((float)$r['quote_amount'],2):'Awaiting quotation';
            $values=['customer_name'=>$r['customer_name'],'customer_email'=>$r['customer_email'],'order_number'=>$r['reference'],
                'order_status'=>RentalServiceRequests::STATUSES[$r['status']]??$r['status'], 'payment_status'=>ucfirst($r['payment_status']??'unpaid'),
                'rental_start_date'=>$r['event_start_date'],'rental_end_date'=>$r['event_end_date'],
                'subtotal'=>$amount,'security_deposit'=>'Not applicable','total_amount'=>$amount,'payment_method'=>'See service request',
                'company_name'=>(string)config('app.name'),'support_email'=>(string)config('app.contact_email'),
                'order_items'=>$r['service_name'],'rejection_reason'=>'','proof_receipt_text'=>'',
                'order_status_url'=>absolute_url('rentals/service-requests/'.$id), 'admin_order_url'=>absolute_url('rentals/admin/service-requests/'.$id)];
            [$subject,$body]=$events[$event];
            $this->deliver($delivery,(array)config('rentals-mail.cc',[]),$values,['subject'=>$subject.' — {{order_number}}','body'=>$body]);
        } catch(\Throwable $e) { $this->log('service_'.$event,$id,'system','notification_unavailable'); }
    }

    private function notify(string $event, int $orderId): void
    {
        try {
            if ($this->db->inTransaction()) { throw new \LogicException('Mail must run after commit.'); }
            $snapshot = $this->snapshot($event, $orderId);
            if (!$snapshot) { return; }
            $ccList = (array) config('rentals-mail.cc', []);
            $destinations = [
                ['audience' => 'customer', 'email' => $snapshot['values']['customer_email'], 'cc' => $ccList],
            ];
            // Preserve existing delivery groups across deployment so a recorded
            // combined payment/rental review cannot be sent a second time.
            $group = match ($event) {
                RentalMailEvents::RENTAL_APPROVED => 'review_approved',
                RentalMailEvents::RENTAL_REJECTED => 'review_rejected',
                default => $event,
            };
            foreach ($destinations as $destination) {
                try {
                    $key = hash('sha256', $orderId . '|' . $group . '|' . $snapshot['version'] . '|'
                        . $destination['audience'] . '|' . strtolower($destination['email']));
                    $this->db->statement('INSERT INTO rental_notification_deliveries
                        (dedup_key,event_key,event_version,order_id,audience,recipient_email,status)
                        VALUES (?,?,?,?,?,?,\'pending\') ON DUPLICATE KEY UPDATE id=id',
                        [$key, $event, $snapshot['version'], $orderId, $destination['audience'], $destination['email']]);
                    $delivery = $this->db->selectOne('SELECT * FROM rental_notification_deliveries WHERE dedup_key=?', [$key]);
                    if ($delivery && $this->claim((int) $delivery['id'])) {
                        $this->deliver($delivery, $destination['cc'], $snapshot['values']);
                    }
                } catch (\Throwable $e) {
                    $this->log($event, $orderId, $destination['audience'], 'notification_unavailable');
                }
            }
        } catch (\Throwable $e) {
            $this->log($event, $orderId, 'system', 'notification_unavailable');
        }
    }

    private function claim(int $id): bool
    {
        return $this->db->update('UPDATE rental_notification_deliveries SET attempts=attempts+1,
            attempted_at=CURRENT_TIMESTAMP,failure_category=NULL
            WHERE id=? AND status=\'pending\' AND attempts=0', [$id]) === 1;
    }

    private function deliver(array $delivery, array $cc, array $values, ?array $templateOverride = null): void
    {
        $category = 'invalid_destination';
        try {
            $customerEmail = (string) ($delivery['recipient_email'] ?? '');
            if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid customer email');
            }
            $cleanCc = array_values(array_filter(
                $cc,
                static fn ($a): bool => is_string($a)
                    && filter_var($a, FILTER_VALIDATE_EMAIL) !== false
                    && strcasecmp($a, $customerEmail) !== 0
            ));
            $category = 'smtp_not_configured';
            if (!$this->transport->isConfigured()) { throw new \RuntimeException(); }
            $category = 'template_rendering_failed';
            $template = $templateOverride ?? RentalMailEvents::defaults($delivery['event_key'], $delivery['audience']);
            $allowed = RentalMailEvents::placeholders($delivery['audience']);
            $replace = static fn (string $text): string => preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',
                static fn ($m) => in_array($m[1], $allowed, true) ? (string) ($values[$m[1]] ?? '') : '', $text) ?? '';
            $subject = trim(str_replace(["\r", "\n", "\0"], ' ', $replace($template['subject'])));
            $message = $replace($template['body']);
            $audience = $delivery['audience'];
            $cta = $audience === 'admin' ? $values['admin_order_url'] : $values['order_status_url'];
            $html = $this->view->partial('rentals.emails.notification', compact('subject', 'message', 'values', 'cta', 'audience'));
            $category = 'smtp_failure';
            $this->transport->send($customerEmail, $subject, $message, $html, $cleanCc);
        } catch (SmtpDeliveryUncertain $e) {
            $this->recordFailure($delivery, 'uncertain', 'smtp_acceptance_uncertain'); return;
        } catch (\Throwable $e) {
            $this->recordFailure($delivery, 'failed', $category); return;
        }
        // Acceptance is final even if recording it fails. Never blindly resend.
        try {
            $this->db->update('UPDATE rental_notification_deliveries SET status=\'sent\',
                sent_at=CURRENT_TIMESTAMP,failure_category=NULL WHERE id=?', [$delivery['id']]);
        } catch (\Throwable $e) {
            $this->log($delivery['event_key'], (int) $delivery['order_id'], $delivery['audience'], 'delivery_record_uncertain');
        }
    }

    private function recordFailure(array $delivery, string $status, string $category): void
    {
        try {
            $this->db->update('UPDATE rental_notification_deliveries SET status=?,failure_category=? WHERE id=?',
                [$status, $category, $delivery['id']]);
        } catch (\Throwable $e) { /* The business transaction has already committed. */ }
        $this->log($delivery['event_key'], (int) $delivery['order_id'], $delivery['audience'], $category);
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
            'rejection_reason'=>'','proof_submitted'=>$h['payment_proof_path']?'Yes':'No',
            'proof_receipt_text'=>$h['payment_proof_path']?'Your payment proof was received.':'No payment proof is currently attached.',
            'admin_order_url'=>absolute_url('rentals/admin/orders/'.$id),
            'order_status_url'=>preg_match('/^[a-f0-9]{64}$/D',(string)$h['status_token'])?absolute_url('rentals/order-status/'.$h['status_token']):absolute_url('rentals/orders')];
        return ['values'=>$v,'version'=>hash('sha256',$event===RentalMailEvents::RENTAL_SUBMITTED?'initial':(string)$h['payment_proof_path'])];
    }
    private function log(string $event, int $id, string $audience, string $category): void
    {
        error_log('Rentals email event='.$event.' order_id='.$id.' audience='.$audience.' result='.$category);
    }
}
