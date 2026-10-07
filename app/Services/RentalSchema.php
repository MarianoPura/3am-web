<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/** Read-only contract for the senior-refactored Rentals schema. No DDL or seeds. */
final class RentalSchema
{
    public const COLUMNS = [
        'users' => 'id name email password role last_login created_at updated_at',
        'rental_categories' => 'id name slug description image_path is_active created_at updated_at',
        'rental_items' => 'id category_id name slug sku description ideal_use image_path additional_image_paths is_service availability_status rental_unit rental_rate security_deposit available_quantity is_active created_at updated_at',
        'rental_item_blackouts' => 'id rental_item_id start_date end_date note is_active created_at updated_at',
        'carts' => 'id user_id created_at updated_at',
        'cart_items' => 'id cart_id rental_item_id quantity rental_start_date rental_end_date created_at updated_at',
        'payment_methods' => 'id name type account_name account_number provider is_active qr_image_path created_at updated_at',
        'order_header' => 'id order_number user_id customer_name customer_email customer_phone payment_method_id payment_reference paid_at subtotal security_deposit total_amount notes payment_proof_path payment_reviewed_at payment_reviewed_by status_token payment_status created_at updated_at',
        'order_details' => 'id order_header_id rental_item_id item_name quantity rental_start_date rental_end_date unit_rate line_total created_at',
        'rental_notification_deliveries' => 'id dedup_key event_key event_version order_id service_request_id audience recipient_email status attempts failure_category attempted_at sent_at created_at',
        'rental_service_requests' => 'id reference submission_key user_id rental_item_id service_name customer_name customer_email customer_phone event_start_date event_end_date location details status customer_message reviewed_by reviewed_at notification_attempted_at quote_amount quote_notes quote_version payment_method_id payment_reference payment_proof_path payment_status payment_reviewed_by payment_reviewed_at payment_message completed_at cancelled_at created_at updated_at',
        'rental_password_resets' => 'user_id token_hash expires_at used_at reset_at created_at',
    ];

    public function __construct(private readonly Database $db) {}

    public function inspect(?array $tables = null): array
    {
        $issues = []; $counts = [];
        foreach (self::COLUMNS as $table => $fields) {
            if ($tables !== null && !in_array($table,$tables,true)) { continue; }
            if (!$this->hasTable($table)) { $issues[] = "$table missing"; continue; }
            $columns = array_column($this->db->select('SHOW COLUMNS FROM `' . $table . '`'), null, 'Field');
            foreach (explode(' ', $fields) as $field) {
                if (!isset($columns[$field])) { $issues[] = "$table.$field missing"; }
            }
            $issues = [...$issues, ...self::columnIssues($table, $columns)];
            $indexes = $this->db->select('SHOW INDEX FROM `' . $table . '`');
            if (!in_array('PRIMARY', array_column($indexes, 'Key_name'), true)) { $issues[] = "$table missing primary key"; }
            foreach (match ($table) {
                'users' => ['email'], 'rental_categories' => ['slug'], 'rental_items' => ['slug', 'sku'],
                'order_header' => ['order_number', 'status_token'], 'rental_notification_deliveries' => ['dedup_key'],
                'rental_service_requests' => ['reference', 'submission_key'], 'rental_password_resets' => ['token_hash'], default => [],
            } as $field) {
                $keys = [];
                foreach ($indexes as $index) {
                    if ((int)$index['Non_unique'] === 0) { $keys[$index['Key_name']][] = $index['Column_name']; }
                }
                if (!in_array([$field], $keys, true)) { $issues[] = "$table.$field missing single-column unique key"; }
            }
            foreach (match ($table) {
                'rental_items' => ['category_id' => 'rental_categories'],
                'rental_item_blackouts' => ['rental_item_id' => 'rental_items'],
                'carts' => ['user_id' => 'users'], 'cart_items' => ['cart_id' => 'carts', 'rental_item_id' => 'rental_items'],
                'order_header' => ['user_id' => 'users', 'payment_method_id' => 'payment_methods'],
                'order_details' => ['order_header_id' => 'order_header', 'rental_item_id' => 'rental_items'],
                'rental_service_requests' => ['user_id' => 'users', 'rental_item_id' => 'rental_items', 'reviewed_by' => 'users', 'payment_method_id'=>'payment_methods','payment_reviewed_by'=>'users'],
                'rental_notification_deliveries'=>['order_id'=>'order_header','service_request_id'=>'rental_service_requests'],
                'rental_password_resets' => ['user_id' => 'users'], default => [],
            } as $field => $parent) {
                if (!(int)$this->db->selectValue('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=\'id\'', [$table,$field,$parent])) { $issues[] = "$table.$field missing foreign key to $parent"; }
            }
            if ($table === 'order_header') {
                if (isset($columns['payment_status']) && !str_contains(strtolower($columns['payment_status']['Type']), 'tinyint')) { $issues[] = 'order_header.payment_status must be TINYINT'; }
                foreach (['status','order_status','payment_review_status'] as $redundant) { if (isset($columns[$redundant])) { $issues[] = "$table.$redundant is a redundant status column"; } }
            }
            $counts[$table] = (int)$this->db->selectValue('SELECT COUNT(*) FROM `' . $table . '`');
        }
        return ['issues'=>$issues, 'counts'=>$counts];
    }

    /** Preserve validation guarantees without depending on deleted legacy DDL. */
    public static function columnIssues(string $table, array $columns): array
    {
        $issues=[];
        if (isset($columns['id']) && !str_contains(strtolower($columns['id']['Extra'] ?? ''),'auto_increment')) {
            $issues[]="$table.id must auto-increment";
        }
        $optional=match($table) {
            'users'=>['last_login'],
            'rental_items'=>['slug','sku','rental_unit','image_path','additional_image_paths'],
            'rental_categories'=>['slug'],
            'rental_password_resets'=>['used_at','reset_at'],
            default=>[],
        };
        foreach($optional as $field) {
            if(isset($columns[$field]) && $columns[$field]['Null']!=='YES') { $issues[]="$table.$field must allow NULL"; }
        }
        if($table==='order_header' && isset($columns['payment_status']) && !str_contains(strtolower($columns['payment_status']['Type']),'unsigned')) { $issues[]='order_header.payment_status must be unsigned'; }
        $lengths=match($table) {
            'rental_items'=>['name'=>190,'slug'=>190,'sku'=>80,'ideal_use'=>500,'rental_unit'=>30,'image_path'=>500],
            'rental_service_requests'=>['quote_notes'=>2000,'payment_reference'=>190,'payment_proof_path'=>255,'payment_message'=>1000],
            'rental_password_resets'=>['token_hash'=>64],
            default=>[],
        };
        foreach($lengths as $field=>$minimum) {
            if(isset($columns[$field]) && preg_match('/^(?:var)?char\((\d+)\)/i',$columns[$field]['Type'],$m) && (int)$m[1]<$minimum) { $issues[]="$table.$field must support at least $minimum characters"; }
        }
        $amounts=match($table) { 'rental_items'=>['rental_rate','security_deposit'], 'rental_service_requests'=>['quote_amount'], default=>[] };
        foreach($amounts as $field) {
            if(isset($columns[$field]) && (!preg_match('/^decimal\((\d+),(\d+)\)/i',$columns[$field]['Type'],$m) || (int)$m[1]-(int)$m[2]<10 || (int)$m[2]<2)) { $issues[]="$table.$field must support DECIMAL(12,2) amounts"; }
        }
        return $issues;
    }

    public function hasTable(string $table): bool
    {
        return (int)$this->db->selectValue('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]) === 1;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return (int)$this->db->selectValue('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$table,$column]) === 1;
    }
}
