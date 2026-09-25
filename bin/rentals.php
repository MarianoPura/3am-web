<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Rentals requires PHP 8.1 or newer. No database connection attempted.\n"); exit(1);
}
$container = require dirname(__DIR__) . '/bootstrap.php';
$command = $argv[1] ?? '--check';
if (!in_array($command, ['--check', '--migrate', '--seed-test', '--cleanup-test'], true)) {
    fwrite(STDERR, "Usage: php bin/rentals.php --check|--migrate|--seed-test|--cleanup-test.\n"); exit(1);
}
if (in_array($command, ['--migrate', '--seed-test', '--cleanup-test'], true)
    && !in_array(config('app.env'), ['local', 'testing'], true)) {
    fwrite(STDERR, "This command requires an explicit local/testing environment. No SQL executed.\n"); exit(1);
}
try {
    $db = $container->get(App\Core\Database::class);

    if ($command === '--seed-test') {
        $category = $db->selectOne('SELECT id FROM rental_categories WHERE slug = ?', ['test-rentals-category']);
        $categoryId = $category !== null
            ? (int) $category['id']
            : $db->insert(
                'INSERT INTO rental_categories (name, slug, description, image_path, is_active) VALUES (?, ?, ?, ?, 1)',
                ['[TEST] Rental Category', 'test-rentals-category', 'Temporary local Rentals workflow test.', 'media/rentals-camera-lineup.jpg']
            );
        if ($category !== null) {
            $db->update('UPDATE rental_categories SET image_path = ?, is_active = 1 WHERE id = ?', ['media/rentals-camera-lineup.jpg', $categoryId]);
        }

        $item = $db->selectOne('SELECT id FROM rental_items WHERE slug = ?', ['test-rentals-camera']);
        $itemId = $item !== null
            ? (int) $item['id']
            : $db->insert(
                'INSERT INTO rental_items
                    (category_id, name, slug, sku, description, ideal_use, image_path,
                     is_service, availability_status, rental_unit, rental_rate,
                     security_deposit, available_quantity, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 1)',
                [
                    $categoryId,
                    '[TEST] Camera Item',
                    'test-rentals-camera',
                    'TEST-RENTALS-CAMERA',
                    'Temporary local Rentals workflow test item.',
                    'Local checkout testing',
                    'media/rentals-sony-camera.jpg',
                    'available',
                    'day',
                    250.00,
                    80.00,
                    1,
                ]
            );
        if ($item !== null) { $db->update('UPDATE rental_items SET is_active = 1 WHERE id = ?', [$itemId]); }

        $lighting = $db->selectOne('SELECT id FROM rental_items WHERE slug = ? AND name = ?', ['test-rentals-lighting', '[TEST] Lighting Item']);
        if ($lighting === null) {
            $db->insert(
                'INSERT INTO rental_items
                    (category_id, name, slug, sku, description, ideal_use, image_path,
                     is_service, availability_status, rental_unit, rental_rate,
                     security_deposit, available_quantity, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 1)',
                [
                    $categoryId,
                    '[TEST] Lighting Item',
                    'test-rentals-lighting',
                    'TEST-RENTALS-LIGHTING',
                    'Temporary local Rentals workflow test item.',
                    'Local cart and checkout testing',
                    'media/rentals-led-panels.jpg',
                    'available',
                    'day',
                    150.00,
                    50.00,
                    1,
                ]
            );
        } else {
            $db->update('UPDATE rental_items SET image_path = ?, is_active = 1 WHERE id = ?', ['media/rentals-led-panels.jpg', (int) $lighting['id']]);
        }

        $payment = $db->selectOne('SELECT id FROM payment_methods WHERE name = ?', ['[TEST] Local Payment']);
        $paymentId = $payment !== null
            ? (int) $payment['id']
            : $db->insert(
                'INSERT INTO payment_methods (name, type, provider, is_active) VALUES (?, ?, ?, 1)',
                ['[TEST] Local Payment', 'test', 'local']
            );
        if ($payment !== null) { $db->update('UPDATE payment_methods SET is_active = 1 WHERE id = ?', [$paymentId]); }

        $testPassword = bin2hex(random_bytes(12));
        $customer = $db->selectOne('SELECT id FROM users WHERE email = ? AND name = ?', ['test-rentals@example.test', '[TEST] Customer']);
        $customerId = $customer !== null
            ? (int) $customer['id']
            : $db->insert(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
                ['[TEST] Customer', 'test-rentals@example.test', password_hash($testPassword, PASSWORD_DEFAULT), 'customer']
            );
        if ($customer !== null) {
            $db->update('UPDATE users SET password = ? WHERE id = ?', [password_hash($testPassword, PASSWORD_DEFAULT), $customerId]);
        }

        echo "Test data ready (local/testing only).\n";
        echo "Customer email: test-rentals@example.test\n";
        echo "Temporary customer password: $testPassword\n";
        echo "Test item: [TEST] Camera Item (250.00/day, 80.00 deposit, quantity 1)\n";
        echo "Test item: [TEST] Lighting Item (150.00/day, 50.00 deposit, quantity 1)\n";
        echo "Payment method: [TEST] Local Payment\n";
        exit(0);
    }

    if ($command === '--cleanup-test') {
        foreach ([
            ['test-rentals@example.test', '[TEST] Customer'],
            ['test-rentals-register@example.test', '[TEST] Registration'],
        ] as [$testEmail, $testName]) {
            $customer = $db->selectOne('SELECT id FROM users WHERE email = ? AND name = ?', [$testEmail, $testName]);
            if ($customer === null) {
                continue;
            }
            $customerId = (int) $customer['id'];
            $orderIds = array_map(
                static fn (array $row): int => (int) $row['id'],
                $db->select('SELECT id FROM order_header WHERE user_id = ? AND customer_name = ?', [$customerId, $testName])
            );
            foreach ($orderIds as $orderId) {
                $db->delete('DELETE FROM order_details WHERE order_header_id = ?', [$orderId]);
            }
            if ($orderIds !== []) {
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $db->delete('DELETE FROM order_header WHERE id IN (' . $placeholders . ')', $orderIds);
            }
            $carts = $db->select('SELECT id FROM carts WHERE user_id = ?', [$customerId]);
            foreach ($carts as $cart) {
                $db->delete('DELETE FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]);
            }
            $db->delete('DELETE FROM carts WHERE user_id = ?', [$customerId]);
            $db->delete('DELETE FROM users WHERE id = ?', [$customerId]);
        }
        $testItems = $db->select(
            'SELECT id FROM rental_items WHERE (slug = ? AND name = ?) OR (slug = ? AND name = ?)',
            ['test-rentals-camera', '[TEST] Camera Item', 'test-rentals-lighting', '[TEST] Lighting Item']
        );
        foreach ($testItems as $item) {
            $references = (int) $db->selectValue('SELECT COUNT(*) FROM order_details WHERE rental_item_id = ?', [(int) $item['id']]);
            if ($references === 0) {
                $db->delete('DELETE FROM rental_items WHERE id = ?', [(int) $item['id']]);
            } else {
                $db->update('UPDATE rental_items SET is_active = 0 WHERE id = ?', [(int) $item['id']]);
                echo "Test item archived because an existing order references it.\n";
            }
        }
        $db->delete('DELETE FROM payment_methods WHERE name = ? AND type = ? AND NOT EXISTS (SELECT 1 FROM order_header WHERE payment_method_id = payment_methods.id)', ['[TEST] Local Payment', 'test']);
        $db->update('UPDATE payment_methods SET is_active = 0 WHERE name = ? AND type = ?', ['[TEST] Local Payment', 'test']);
        $db->delete('DELETE FROM rental_categories WHERE slug = ? AND name = ? AND NOT EXISTS (SELECT 1 FROM rental_items WHERE category_id = rental_categories.id)', ['test-rentals-category', '[TEST] Rental Category']);
        $db->update('UPDATE rental_categories SET is_active = 0 WHERE slug = ? AND name = ?', ['test-rentals-category', '[TEST] Rental Category']);
        echo "Temporary [TEST] Rentals records removed.\n";
        exit(0);
    }

    $existing = array_map(static fn ($row) => (string) reset($row), $db->select('SHOW TABLES'));
    $source = preg_replace('/^--.*$/m', '', file_get_contents(BASE_PATH . '/database/rentals.sql'));
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)` \((.*?)\) ENGINE=InnoDB[^;]*;/s', $source, $tables, PREG_SET_ORDER);
    if (count($tables) !== 9) { throw new RuntimeException('Invalid schema source.'); }
    if ($command === '--migrate' && in_array('payment_methods', $existing, true)) {
        $paymentColumns = array_column($db->select('SHOW COLUMNS FROM `payment_methods`'), null, 'Field');
        if (!isset($paymentColumns['qr_image_path'])) {
            $db->statement('ALTER TABLE `payment_methods` ADD COLUMN `qr_image_path` varchar(255) DEFAULT NULL');
            echo "Added payment_methods.qr_image_path\n";
        }
    }
    // DDL is explicit and restricted to local/testing. Never silently coerce
    // an unknown legacy state into an approved or rejected payment.
    if ($command === '--migrate' && in_array('order_header', $existing, true)) {
        $orderColumns = array_column($db->select('SHOW COLUMNS FROM `order_header`'), null, 'Field');
        foreach ([
            'payment_proof_path' => 'varchar(255) DEFAULT NULL',
            'payment_reviewed_at' => 'timestamp NULL DEFAULT NULL',
            'payment_reviewed_by' => 'bigint(20) UNSIGNED DEFAULT NULL',
            'status_token' => 'varchar(64) DEFAULT NULL',
        ] as $field => $definition) {
            if (!isset($orderColumns[$field])) {
                $db->statement('ALTER TABLE `order_header` ADD COLUMN `' . $field . '` ' . $definition);
                echo "Added order_header.$field\n";
            }
        }
        $orderColumns = array_column($db->select('SHOW COLUMNS FROM `order_header`'), null, 'Field');
        if (!str_contains(strtolower((string) $orderColumns['payment_status']['Type']), 'tinyint')) {
            $legacy = $db->select('SELECT id, status, payment_status, payment_review_status FROM order_header');
            $mapped = [];
            foreach ($legacy as $row) {
                $status = (string) $row['status'];
                $payment = (string) $row['payment_status'];
                $review = (string) $row['payment_review_status'];
                if (!in_array($status, ['pending', 'approved', 'rejected'], true)
                    || !in_array($payment, ['pending', 'paid', 'failed'], true)
                    || !in_array($review, ['none', 'pending', 'approved', 'rejected'], true)) {
                    throw new RuntimeException('An existing order has an unmappable legacy status. Review it before migration.');
                }
                $mapped[(int) $row['id']] = $status === 'rejected' || $review === 'rejected' || $payment === 'failed' ? 2
                    : ($status === 'approved' || $review === 'approved' || $payment === 'paid' ? 1 : 0);
            }
            $db->statement('ALTER TABLE `order_header` ADD COLUMN `payment_status_next` tinyint(3) UNSIGNED DEFAULT NULL');
            foreach ($mapped as $id => $value) {
                $db->update('UPDATE order_header SET payment_status_next = ? WHERE id = ?', [$value, $id]);
            }
            if ((int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE payment_status_next IS NULL') !== 0) {
                throw new RuntimeException('Status copy did not cover every order. Legacy columns remain available.');
            }
            $db->statement('ALTER TABLE `order_header`
                DROP INDEX `order_header_status_date_index`,
                DROP INDEX `order_header_payment_status_index`,
                DROP COLUMN `payment_status`, DROP COLUMN `status`, DROP COLUMN `payment_review_status`,
                CHANGE COLUMN `payment_status_next` `payment_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
                ADD KEY `order_header_payment_date_index` (`payment_status`, `created_at`),
                ADD KEY `order_header_payment_status_index` (`payment_status`)');
            echo "Converted order_header.payment_status to Pending=0, Approved=1, Rejected=2; removed redundant status columns.\n";
        }
        $indexes = array_column($db->select('SHOW INDEX FROM `order_header`'), 'Key_name');
        if (!in_array('order_header_status_token_unique', $indexes, true)) {
            $db->statement('ALTER TABLE `order_header` ADD UNIQUE KEY `order_header_status_token_unique` (`status_token`)');
        }
        foreach ($db->select('SELECT id FROM order_header WHERE status_token IS NULL') as $row) {
            $db->update('UPDATE order_header SET status_token = ? WHERE id = ? AND status_token IS NULL', [bin2hex(random_bytes(32)), (int) $row['id']]);
        }
    }
    $missing = []; $issues = [];
    foreach ($tables as $table) {
        $name = $table[1];
        if (!in_array($name, $existing, true)) { $missing[$name] = $table[0]; continue; }
        $identifier = $db->identifier($name, array_column($tables, 1));
        $columns = array_column($db->select('SHOW COLUMNS FROM ' . $identifier), null, 'Field');
        preg_match_all('/^\s*`([a-z_]+)` ([^\n]+)/m', $table[2], $definitions, PREG_SET_ORDER);
        foreach ($definitions as $definition) {
            $field = $definition[1];
            if (!isset($columns[$field])) { $issues[] = "$name.$field missing"; continue; }
            if (str_contains($definition[2], 'AUTO_INCREMENT') && !str_contains($columns[$field]['Extra'], 'auto_increment')) {
                $issues[] = "$name.$field must auto-increment";
            }
            if ($name === 'order_header' && $field === 'payment_status'
                && !str_contains(strtolower((string) $columns[$field]['Type']), 'tinyint')) {
                $issues[] = 'order_header.payment_status must be TINYINT';
            }
            if (in_array($field, ['slug', 'totp_secret', 'last_login'], true) && $columns[$field]['Null'] !== 'YES') {
                $issues[] = "$name.$field must allow NULL";
            }
        }
        if ($name === 'order_header') {
            foreach (['status', 'order_status', 'payment_review_status'] as $redundant) {
                if (isset($columns[$redundant])) { $issues[] = "order_header.$redundant must be removed after reviewed migration"; }
            }
            if (isset($columns['payment_status'])
                && !str_contains(strtolower((string) $columns['payment_status']['Type']), 'unsigned')) {
                $issues[] = 'order_header.payment_status must be unsigned';
            }
        }
        $indexes = $db->select('SHOW INDEX FROM ' . $identifier);
        if (!in_array('PRIMARY', array_column($indexes, 'Key_name'), true)) { $issues[] = "$name missing primary key"; }
        preg_match_all('/UNIQUE KEY `[^`]+` \(`([a-z_]+)`\)/', $table[2], $uniqueKeys, PREG_SET_ORDER);
        foreach ($uniqueKeys as $key) {
            if (!array_filter($indexes, static fn($index) => (int) $index['Non_unique'] === 0 && $index['Column_name'] === $key[1])) { $issues[] = "$name.$key[1] missing unique index"; }
        }
        preg_match_all('/FOREIGN KEY \(`([a-z_]+)`\) REFERENCES `([a-z_]+)` \(`([a-z_]+)`\)/', $table[2], $foreignKeys, PREG_SET_ORDER);
        foreach ($foreignKeys as $fk) {
            $found = $db->selectValue('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', [$name, $fk[1], $fk[2], $fk[3]]);
            if (!$found) { $issues[] = "$name.$fk[1] missing foreign key"; }
        }
        echo "$name: " . $db->selectValue('SELECT COUNT(*) FROM ' . $identifier) . " records\n";
    }
    foreach ($issues as $issue) { echo "Review: $issue\n"; }
    if ($issues) { fwrite(STDERR, "Existing schema needs a reviewed migration. No changes made.\n"); exit(1); }
    foreach ($missing as $name => $sql) {
        if ($command === '--migrate') { $db->statement($sql); echo "Created $name\n"; }
        else { echo "Missing: $name\n"; }
    }
    if ($missing && $command === '--check') { exit(1); }
    echo "Required Rentals columns and relationships checked. Existing records preserved.\n";
} catch (Throwable $e) {
    $cause = $e->getPrevious() ?? $e;
    $code = $cause instanceof PDOException ? ($cause->errorInfo[1] ?? $cause->getCode()) : $cause->getCode();
    fwrite(STDERR, "Rental database check/setup failed (code " . (int) $code . "). Verify connection/permissions privately; see docs/rentals.md.\n"); exit(1);
}
