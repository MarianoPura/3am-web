<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Read-only schema inspection; proposed SQL is never executed here. */
final class RentalPaymentReportSchema
{
    public const REVIEW_COLUMNS = [
        'paid_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'payment_reviewed_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'payment_reviewed_by' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
    ];

    public function __construct(private readonly Database $db) {}

    public function inspect(): array
    {
        $required = [
            'order_header' => ['id', 'order_number', 'user_id', 'customer_name', 'customer_email',
                'payment_method_id', 'payment_status', 'payment_reference', 'payment_proof_path',
                'payment_reviewed_at', 'payment_reviewed_by', 'paid_at', 'subtotal',
                'security_deposit', 'total_amount', 'created_at', 'updated_at'],
            'payment_methods' => ['id', 'name'],
            'users' => ['id', 'name'],
            'order_details' => ['order_header_id'],
        ];
        $tables = array_map(static fn (array $row): string => (string) reset($row), $this->db->select('SHOW TABLES'));
        $issues = $additions = $columns = [];
        $canGenerate = true;
        foreach ($required as $table => $fields) {
            if (!in_array($table, $tables, true)) {
                $issues[] = "$table table missing";
                $canGenerate = false;
                continue;
            }
            $columns[$table] = array_column($this->db->select('SHOW COLUMNS FROM '
                . $this->db->identifier($table, array_keys($required))), null, 'Field');
            foreach ($fields as $field) {
                if (isset($columns[$table][$field])) { continue; }
                $issues[] = "$table.$field missing";
                if ($table === 'order_header' && isset(self::REVIEW_COLUMNS[$field])) {
                    $additions[] = 'ADD COLUMN `' . $field . '` ' . self::REVIEW_COLUMNS[$field];
                } else {
                    $canGenerate = false;
                }
            }
        }
        foreach (self::REVIEW_COLUMNS as $field => $definition) {
            $column = $columns['order_header'][$field] ?? null;
            if ($column === null) { continue; }
            $validType = $field === 'payment_reviewed_by'
                ? (bool) preg_match('/^bigint(?:\(\d+\))? unsigned$/i', (string) $column['Type'])
                : strtolower((string) $column['Type']) === 'timestamp';
            if (!$validType || $column['Null'] !== 'YES') {
                $issues[] = "order_header.$field must be " . $definition;
                $canGenerate = false;
            }
        }
        $status = $columns['order_header']['payment_status'] ?? null;
        if ($status !== null && (!preg_match('/^tinyint(?:\(\d+\))? unsigned$/i', (string) $status['Type'])
            || $status['Null'] !== 'NO')) {
            $issues[] = 'order_header.payment_status must be TINYINT UNSIGNED NOT NULL (0/1/2)';
            $canGenerate = false;
        }
        return ['issues' => $issues, 'columns' => $columns,
            'migration' => $canGenerate && $additions !== []
                ? "ALTER TABLE `order_header`\n    " . implode(",\n    ", $additions) . ";\n" : null];
    }
}
