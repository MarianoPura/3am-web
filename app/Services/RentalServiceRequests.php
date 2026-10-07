<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Service enquiries/quotations; never equipment stock or payment orders. */
final class RentalServiceRequests
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed'=>'Completed', 'cancelled'=>'Cancelled'];

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
        $row=$this->db->selectOne('SELECT * FROM rental_service_requests WHERE id = ?', [$id]);
        if($row && !empty($row['payment_method_id'])) { $row['payment_method_name']=$this->db->selectValue('SELECT name FROM payment_methods WHERE id=?',[$row['payment_method_id']]); }
        return $row;
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
    public function workflowReady(): bool
    {
        $schema=new RentalSchema($this->db);
        foreach(['quote_amount','quote_notes','quote_version','payment_method_id','payment_reference','payment_proof_path','payment_status','payment_reviewed_by','payment_reviewed_at','payment_message','completed_at','cancelled_at'] as $field) {
            if(!$schema->hasColumn('rental_service_requests',$field)) { return false; }
        }
        return true;
    }

    public function historyPage(int $userId,string $status,int $page): array
    {
        return RentalPagination::fetch($this->db,"SELECT * FROM rental_service_requests WHERE user_id=? AND (?='all' OR status=?) ORDER BY created_at DESC,id DESC",[$userId,$status,$status],$page);
    }

    public function adminPage(string $status,string $term,int $page): array
    {
        $term=mb_substr(trim($term),0,100);
        return RentalPagination::fetch($this->db,"SELECT * FROM rental_service_requests WHERE (?='all' OR status=?) AND (?='' OR reference LIKE ? OR customer_name LIKE ? OR service_name LIKE ?) ORDER BY created_at DESC,id DESC",[$status,$status,$term,'%'.$term.'%','%'.$term.'%','%'.$term.'%'],$page);
    }

    private function assertAdmin(int $adminId): void
    {
        $role=$this->db->selectValue('SELECT role FROM users WHERE id=?',[$adminId]);
        if(!in_array(strtolower((string)$role),['admin','superadmin'],true)) { throw new RuntimeException('Administrator access is required.'); }
    }

    public function quote(int $id,int $adminId,string $amount,string $notes): bool
    {
        if(!$this->workflowReady()) { throw new RuntimeException('The Services deployment update is required.'); }
        if(!preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D',$amount) || mb_strlen($notes)>2000) { throw new RuntimeException('Enter a valid quotation amount and notes within 2,000 characters.'); }
        return $this->db->transaction(function(Database $db) use($id,$adminId,$amount,$notes):bool {
            $this->assertAdmin($adminId);
            $row=$db->selectOne('SELECT * FROM rental_service_requests WHERE id=? FOR UPDATE',[$id]);
            if(!$row || $row['status']!=='approved' || $row['payment_status']!=='unpaid' || $row['payment_proof_path']) { throw new RuntimeException('Only an approved service without a submitted payment can receive a quotation.'); }
            if($row['quote_amount']!==null && self::amount($row['quote_amount'])===self::amount($amount) && (string)$row['quote_notes']===trim($notes)) { return false; }
            $db->update('UPDATE rental_service_requests SET quote_amount=?,quote_notes=?,quote_version=quote_version+1 WHERE id=?',[$amount,trim($notes)?:null,$id]);
            return true;
        });
    }

    public function submitPayment(int $id,int $userId,int $method,string $reference,?array $upload,?int $quoteVersion=null): bool
    {
        if(!$this->workflowReady()) { throw new RuntimeException('Service payments are unavailable until the deployment update is applied.'); }
        if(mb_strlen($reference)>190) { throw new RuntimeException('Payment reference must be at most 190 characters.'); }
        $saved=null; $old=null;
        try {
            $changed=$this->db->transaction(function(Database $db) use($id,$userId,$method,$reference,$upload,$quoteVersion,&$saved,&$old):bool {
                $row=$db->selectOne('SELECT * FROM rental_service_requests WHERE id=? AND user_id=? FOR UPDATE',[$id,$userId]);
                if(!$row || $row['status']!=='approved' || $row['quote_amount']===null || (float)$row['quote_amount']<=0) { throw new RuntimeException('Payment is accepted only for your approved, quoted service request.'); }
                // A replay while proof is pending/approved never saves another file or sends another email.
                if(in_array($row['payment_status'],['pending','approved'],true)) { return false; }
                // Compare under the quotation's row lock, before creating any proof file.
                if($quoteVersion===null || $quoteVersion<0 || $quoteVersion!==(int)$row['quote_version']) {
                    throw new RuntimeException('The quotation has changed or this payment page has expired. Review the latest quotation before submitting proof. If you already paid a different amount, contact the team before paying again.');
                }
                $payment=$db->selectOne("SELECT id FROM payment_methods WHERE id=? AND is_active=1 AND type='manual' AND NULLIF(TRIM(account_name),'') IS NOT NULL AND NULLIF(TRIM(account_number),'') IS NOT NULL",[$method]);
                if(!$payment) { throw new RuntimeException('Choose an available payment method.'); }
                $old=$row['payment_proof_path'];
                $saved=RentalPaymentProof::store($upload);
                if($saved===null) { throw new RuntimeException('Upload your payment proof.'); }
                $db->update("UPDATE rental_service_requests SET payment_method_id=?,payment_reference=?,payment_proof_path=?,payment_status='pending',payment_reviewed_by=NULL,payment_reviewed_at=NULL,payment_message=NULL WHERE id=?",[$method,trim($reference)?:null,$saved,$id]);
                return true;
            });
            try { if($changed && $old && $this->db->selectValue('SELECT id FROM order_header WHERE payment_proof_path=? LIMIT 1',[$old])===null && $this->db->selectValue('SELECT id FROM rental_service_requests WHERE payment_proof_path=? LIMIT 1',[$old])===null) { RentalPaymentProof::remove($old); } } catch(\Throwable $e) { error_log('Rentals replaced service proof cleanup unavailable'); }
            return $changed;
        } catch(\Throwable $e) { RentalPaymentProof::remove($saved); throw $e; }
    }

    public function reviewPayment(int $id,int $adminId,string $decision,string $message): bool
    {
        if(!in_array($decision,['approved','rejected'],true) || mb_strlen($message)>1000) { throw new RuntimeException('Choose a payment decision and use at most 1,000 characters.'); }
        return $this->db->transaction(function(Database $db) use($id,$adminId,$decision,$message):bool {
            $this->assertAdmin($adminId);
            $row=$db->selectOne('SELECT * FROM rental_service_requests WHERE id=? FOR UPDATE',[$id]);
            if(!$row || $row['status']!=='approved' || $row['payment_status']!=='pending' || !$row['payment_proof_path']) { return false; }
            return $db->update('UPDATE rental_service_requests SET payment_status=?,payment_reviewed_by=?,payment_reviewed_at=CURRENT_TIMESTAMP,payment_message=? WHERE id=?',[$decision,$adminId,trim($message)?:null,$id])===1;
        });
    }

    public function close(int $id,int $adminId,string $decision,string $message): bool
    {
        if(!in_array($decision,['completed','cancelled'],true) || mb_strlen($message)>1000) { throw new RuntimeException('Choose Completed or Cancelled and use at most 1,000 characters.'); }
        return $this->db->transaction(function(Database $db) use($id,$adminId,$decision,$message):bool {
            $this->assertAdmin($adminId);
            $row=$db->selectOne('SELECT * FROM rental_service_requests WHERE id=? FOR UPDATE',[$id]);
            if(!$row || !in_array($row['status'],['pending','approved'],true)) { return false; }
            if($decision==='completed' && ($row['status']!=='approved' || $row['quote_amount']===null || ((float)$row['quote_amount']>0 && $row['payment_status']!=='approved') || $row['event_end_date']>date('Y-m-d'))) {
                throw new RuntimeException('Complete the service after its end date and payment approval (or a zero-amount quote).');
            }
            $field=$decision==='completed'?'completed_at':'cancelled_at';
            return $db->update("UPDATE rental_service_requests SET status=?,customer_message=?,$field=CURRENT_TIMESTAMP WHERE id=?",[$decision,trim($message)?:null,$id])===1;
        });
    }

    private static function amount(string $value): string
    {
        $parts=explode('.',$value,2);
        return (ltrim($parts[0],'0')?:'0').'.'.str_pad($parts[1]??'',2,'0');
    }

}
