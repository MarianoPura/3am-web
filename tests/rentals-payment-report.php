<?php

declare(strict_types=1);

// Temporary tables shadow the real tables only on this connection. No persistent
// schema or customer records are altered, including for missing-column tests.
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$tables = ['order_header', 'order_details', 'payment_methods', 'users'];
$created = [];
$savedQuery = $_GET;
$schema = new App\Services\RentalPaymentReportSchema($db);
$insights = new App\Services\RentalAdminInsights($db);
$report = static function (array $filters = []) use ($insights): array {
    $_GET = $filters;
    return $insights->paymentReport(App\Core\Request::capture());
};
$render = static fn (array $data): string => $container->get(App\Core\View::class)
    ->render('rentals.partials.admin-payment-report', ['report' => $data]);
try {
    foreach ($tables as $table) {
        $identifier = $db->identifier($table, $tables);
        $definition = $db->selectOne('SHOW CREATE TABLE ' . $identifier)['Create Table'];
        // Temporary InnoDB tables cannot have foreign keys. Preserve all other
        // current column definitions/indexes, with no rows copied from the source.
        $definition = preg_replace('/^\s*CONSTRAINT [^\n]+\n?/m', '', $definition);
        $definition = preg_replace('/,\s*\) ENGINE=/', "\n) ENGINE=", $definition);
        $definition = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $definition);
        $db->statement($definition);
        $created[] = $table;
    }
    $assert($schema->inspect()['issues'] === [], 'Current canonical report schema is incompatible.');
    $empty = $report();
    $assert($empty['rows'] === [] && str_contains($render($empty), 'No payments match these filters.'), 'Empty report cannot render.');
    foreach ($empty['totals'] as $amount) { $assert((float) $amount === 0.0, 'Empty report totals are not zero.'); }
    $db->insert('INSERT INTO users (id,name,email,password,role) VALUES (11,?,?,?,?)',
        ['QA reviewer', 'report-qa@example.test', 'unused-test-hash', 'admin']);
    $db->insert('INSERT INTO payment_methods (id,name,type,is_active) VALUES (21,?,?,1)', ['QA method', 'manual']);
    $db->insert('INSERT INTO payment_methods (id,name,type,is_active) VALUES (22,?,?,0)', ['Inactive historical method', 'manual']);
    foreach ([0, 1, 2] as $state) {
        $id = $db->insert('INSERT INTO order_header (order_number,user_id,customer_name,customer_email,
            payment_method_id,payment_status,subtotal,security_deposit,total_amount,
            payment_reviewed_at,payment_reviewed_by,paid_at) VALUES (?,11,?,?,21,?,300,9000,9300,?,?,?)',
            ['QA-STATUS-' . $state, 'QA customer', 'report-qa@example.test', $state,
                $state === 0 ? null : '2026-01-02 12:00:00', $state === 0 ? null : 11,
                $state === 1 ? '2026-01-02 12:00:00' : null]);
        foreach ([1, 2] as $line) {
            $db->insert('INSERT INTO order_details (order_header_id,rental_item_id,item_name,quantity,unit_rate,line_total)
                VALUES (?,1,?,1,150,150)', [$id, '[TEST] Legitimate snapshot ' . $line]);
        }
    }
    foreach ([['QA-NULL', null, 0, 10, 5, 15], ['QA-INACTIVE', 22, 1, 45, 15, 60]] as [$number, $method, $state, $rental, $deposit, $total]) {
        $id = $db->insert('INSERT INTO order_header (order_number,user_id,customer_name,customer_email,
            payment_method_id,payment_status,subtotal,security_deposit,total_amount,payment_reviewed_by)
            VALUES (?,11,?,?,?,?,?,?,?,?)', [$number, 'QA customer', 'report-qa@example.test', $method,
                $state, $rental, $deposit, $total, $state === 1 ? 999 : null]);
        $db->insert('INSERT INTO order_details (order_header_id,rental_item_id,item_name,quantity) VALUES (?,1,?,1)',
            [$id, '[TEST] Valid NULL/history snapshot']);
    }
    $all = $report();
    $assert(count($all['rows']) === 5, 'NULL methods/reviewers or [TEST] names hid valid orders.');
    foreach (['orders' => 5, 'rental_amount' => 345, 'security_deposits' => 9015,
        'total_collected' => 9360, 'pending_amount' => 9315] as $field => $amount) {
        $assert((float) $all['totals'][$field] === (float) $amount, 'Incorrect report total: ' . $field);
    }
    $html = $render($all);
    foreach (['Unassigned', 'Not reviewed', 'Inactive historical method', 'Pending', 'Approved', 'Rejected'] as $label) {
        $assert(str_contains($html, $label), 'Missing readable report state: ' . $label);
    }
    foreach ([0 => 2, 1 => 2, 2 => 1] as $state => $count) {
        $filtered = $report(['payment_status' => (string) $state]);
        $assert(count($filtered['rows']) === $count && $render($filtered) !== '', 'Status filter failed: ' . $state);
        if ($state !== 1) {
            $assert((float) $filtered['totals']['rental_amount'] === 0.0
                && (float) $filtered['totals']['total_collected'] === 0.0, 'Pending/Rejected counted as revenue.');
        }
    }
    $historical = $report(['method' => 22]);
    $assert(count($historical['rows']) === 1 && (float) $historical['totals']['rental_amount'] === 45.0,
        'Inactive method filter lost its historical payment.');
    $assert($report(['from' => '2099-01-01'])['rows'] === [], 'Date no-match filter failed.');

    // Reproduce SQLSTATE 42S22 / MySQL 1054 without touching the real schema.
    foreach (['payment_reviewed_at', 'payment_reviewed_by', 'paid_at'] as $field) {
        $identifier = $db->identifier($field, array_keys(App\Services\RentalPaymentReportSchema::REVIEW_COLUMNS));
        $db->statement('ALTER TABLE order_header DROP COLUMN ' . $identifier);
        $inspection = $schema->inspect();
        $assert(in_array('order_header.' . $field . ' missing', $inspection['issues'], true), 'Missing column was not diagnosed: ' . $field);
        $assert($inspection['migration'] === 'ALTER TABLE `order_header`' . "\n    ADD COLUMN " . $identifier . ' '
            . App\Services\RentalPaymentReportSchema::REVIEW_COLUMNS[$field] . ";\n", 'Proposed migration adds duplicate/unrelated columns.');
        try {
            $report();
            throw new RuntimeException('Missing column unexpectedly allowed the report query.');
        } catch (RuntimeException $e) {
            $cause = $e->getPrevious();
            $assert($cause instanceof PDOException && (int) ($cause->errorInfo[1] ?? 0) === 1054
                && str_contains($cause->getMessage(), $field), 'Unexpected failing SQL/field: ' . $field);
            echo "Reproduced missing $field: " . $cause->getMessage() . "\n";
        }
        // Execute the inspected proposal against this temporary table only.
        $db->statement($inspection['migration']);
        $assert($schema->inspect()['migration'] === null && count($report()['rows']) === 5, 'Additive repair did not restore report query.');
    }
    $db->statement('ALTER TABLE order_header DROP COLUMN payment_reviewed_at, DROP COLUMN payment_reviewed_by');
    $bothMissing = $schema->inspect();
    $assert(count($bothMissing['issues']) === 2 && substr_count($bothMissing['migration'] ?? '', 'ADD COLUMN') === 2
        && !str_contains($bothMissing['migration'] ?? '', '`paid_at`'), 'Combined repair includes existing columns.');
    $db->statement($bothMissing['migration']);
    $assert(count($report()['rows']) === 5, 'Combined additive repair lost orders.');
    $db->statement('ALTER TABLE order_header DROP COLUMN customer_name');
    $assert($schema->inspect()['migration'] === null, 'Unrelated missing field received a misleading review-only repair.');
    $db->statement('ALTER TABLE order_header ADD COLUMN customer_name VARCHAR(190) NOT NULL DEFAULT \'QA customer\'');
    $db->statement('ALTER TABLE order_header MODIFY COLUMN payment_reviewed_by VARCHAR(40) NULL');
    $invalid = $schema->inspect();
    $assert($invalid['issues'] !== [] && $invalid['migration'] === null, 'Wrong existing type received an unsafe migration.');
    echo "PASS: empty/NULL/history/status filters, header-only totals, multi-line [TEST] orders, exact missing-column diagnosis and inspected additive repairs.\n";
} finally {
    $_GET = $savedQuery;
    foreach (array_reverse($created) as $table) {
        $db->statement('DROP TEMPORARY TABLE ' . $db->identifier($table, $tables));
    }
}
