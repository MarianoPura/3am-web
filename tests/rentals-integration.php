<?php

declare(strict_types=1);

// Read-only checks against whichever local Rentals records already exist.
$container = require dirname(__DIR__) . '/bootstrap.php';
$db = $container->get(App\Core\Database::class);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$tables = array_map(static fn (array $row): string => (string) reset($row), $db->select('SHOW TABLES'));
foreach (['users', 'rental_categories', 'rental_items', 'carts', 'cart_items', 'payment_methods', 'order_header', 'order_details'] as $table) {
    $assert(in_array($table, $tables, true), 'Missing core table: ' . $table);
}
$columns = array_column($db->select('SHOW COLUMNS FROM order_header'), null, 'Field');
$assert(isset($columns['payment_status'], $columns['status_token'], $columns['payment_proof_path'])
    && !isset($columns['status'], $columns['payment_review_status'], $columns['order_status']),
    'Order header has missing or redundant status columns.');
$assert(str_contains(strtolower((string) $columns['payment_status']['Type']), 'tinyint'), 'Payment status is not an integer.');
$assert((int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE payment_status NOT IN (0, 1, 2)') === 0,
    'Stored payment status is outside the mapping.');
$assert((int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE status_token IS NULL OR CHAR_LENGTH(status_token) <> 64') === 0,
    'An order is missing a secure status token.');
$assert((int) $db->selectValue('SELECT COUNT(*) FROM order_details d LEFT JOIN order_header h ON h.id = d.order_header_id WHERE h.id IS NULL') === 0,
    'An order detail has no header.');
echo "PASS: eight core tables, one integer status source, secure tokens and order-detail relationships.\n";
