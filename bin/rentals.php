<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Rentals requires PHP 8.1 or newer. No database connection attempted.\n"); exit(1);
}
$container = require dirname(__DIR__) . '/bootstrap.php';
$command = $argv[1] ?? '--check';
if (!in_array($command, ['--check', '--payment-report-check', '--payment-report-sql', '--migrate', '--seed-test', '--cleanup-test'], true)) {
    fwrite(STDERR, "Usage: php bin/rentals.php --check|--payment-report-check|--payment-report-sql|--migrate|--seed-test|--cleanup-test.\n"); exit(1);
}
if (in_array($command, ['--migrate', '--seed-test', '--cleanup-test'], true)
    && !in_array(config('app.env'), ['local', 'testing'], true)) {
    fwrite(STDERR, "This command requires an explicit local/testing environment. No SQL executed.\n"); exit(1);
}
try {
    $db = $container->get(App\Core\Database::class);

    if (in_array($command, ['--payment-report-check', '--payment-report-sql'], true)) {
        $inspection = (new App\Services\RentalPaymentReportSchema($db))->inspect();
        foreach ($inspection['issues'] as $issue) { fwrite(STDERR, "Review: $issue\n"); }
        if ($command === '--payment-report-sql') {
            if ($inspection['migration'] !== null) {
                echo "-- Generated from the currently connected schema; review before executing.\n";
                echo "-- Additive SQL only. This command has NOT executed a migration.\n";
                echo $inspection['migration'];
            } elseif ($inspection['issues'] === []) {
                echo "-- Payment Report schema is compatible. No column migration required.\n";
            } else {
                fwrite(STDERR, "Cannot propose a review-column-only migration for these schema issues. No changes made.\n");
                exit(1);
            }
        } elseif ($inspection['issues'] !== []) {
            fwrite(STDERR, "Payment Report schema mismatch. No changes made.\n");
            exit(1);
        } else {
            $report = (new App\Services\RentalAdminInsights($db))->paymentReport(App\Core\Request::capture());
            echo "Configured Payment Report schema and SELECT checked: " . count($report['rows']) . " rows returned. No changes made.\n";
        }
        exit(0);
    }

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
                     availability_status, rental_unit, rental_rate,
                     security_deposit, available_quantity, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
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
                     availability_status, rental_unit, rental_rate,
                     security_deposit, available_quantity, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
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
        $db->update('UPDATE rental_categories SET is_active = 0 WHERE slug = ? AND name = ? AND NOT EXISTS (SELECT 1 FROM rental_items WHERE category_id = rental_categories.id AND is_active = 1)', ['test-rentals-category', '[TEST] Rental Category']);
        echo "Temporary [TEST] Rentals records removed.\n";
        exit(0);
    }

    if ($command === '--migrate') {
        fwrite(STDERR, "Automatic legacy migration is disabled. Review/apply docs/rentals-completion-additive.sql and docs/rentals-service-requests-additive.sql explicitly. No changes made.\n");
        exit(1);
    }
    $inspection = (new App\Services\RentalSchema($db))->inspect();
    foreach ($inspection['counts'] as $table => $count) { echo "$table: $count records\n"; }
    foreach ($inspection['issues'] as $issue) { fwrite(STDERR, "Review: $issue\n"); }
    if ($inspection['issues'] !== []) { fwrite(STDERR, "Schema mismatch. Review deployment SQL; no changes made.\n"); exit(1); }
    echo "Required Rentals columns, unique keys and relationships checked. No changes made.\n";
    $uploadRoot = App\Services\RentalStorage::root();
    echo "External Rentals storage: $uploadRoot\n";
    foreach (['rentals/products', 'payment', 'payment/qr'] as $subdirectory) {
        $directory = $uploadRoot . '/' . $subdirectory;
        if (!is_dir($directory) || !is_writable($directory)) {
            fwrite(STDERR, "Review: external storage $subdirectory is missing or not writable by this PHP process.\n");
        }
    }
} catch (Throwable $e) {
    $cause = $e->getPrevious() ?? $e;
    $code = $cause instanceof PDOException ? ($cause->errorInfo[1] ?? $cause->getCode()) : $cause->getCode();
    fwrite(STDERR, "Rental database check/setup failed (code " . (int) $code . "). Verify connection/permissions privately; see docs/rentals.md.\n"); exit(1);
}
