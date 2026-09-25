<?php

declare(strict_types=1);

// Transactional service/controller checks; all rows created here roll back.
session_start();
$container = require dirname(__DIR__) . '/bootstrap.php';
$db = $container->get(App\Core\Database::class);
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$request = static function (array $post = [], string $method = 'POST'): App\Core\Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/rentals/items';
    $_POST = $post;
    $_GET = [];
    return App\Core\Request::capture();
};
$db->beginTransaction();
try {
    $_SESSION = [];
    $key = bin2hex(random_bytes(5));
    $categoryId = $db->insert('INSERT INTO rental_categories (name, slug) VALUES (?, ?)', ['[TEST] ' . $key, 'completion-' . $key]);
    $itemId = $db->insert('INSERT INTO rental_items (category_id, name, slug, availability_status, rental_unit,
        rental_rate, security_deposit, available_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$categoryId, '[TEST] Camera', 'completion-' . $key, 'available', 'day', 125, 40, 1]);
    $paymentId = $db->insert('INSERT INTO payment_methods (name, type, provider, account_name, account_number) VALUES (?, ?, ?, ?, ?)', ['[TEST] Manual', 'manual', 'Test Bank', 'Test Account', '0000000000']);
    $userId = $db->insert('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        ['[TEST] Customer', 'completion-' . $key . '@example.test', password_hash(random_bytes(16), PASSWORD_DEFAULT), 'customer']);
    $_SESSION['user_id'] = $userId;
    $cartController = new App\Controllers\Rentals\RentalCartController($container);
    $cartController->add($request(['id' => 'completion-' . $key]));
    $cart = new App\Services\RentalCart();
    $assert($cart->empty(), 'Undated equipment was added to Cart.');
    $date = (new DateTimeImmutable('today'))->modify('+90 days')->format('Y-m-d');
    $cartController->add($request(['id' => 'completion-' . $key, 'quantity' => '1',
        'rental_start_date' => $date, 'rental_end_date' => $date]));
    $assert($cart->count() === 1, 'Dated Add to Cart failed.');
    $summary = (new App\Services\RentalCheckout($db, $cart))->summary();
    $assert($summary['subtotal'] === 125.0 && $summary['security_deposit'] === 40.0, 'Server-side totals are wrong.');
    $checkout = new App\Services\RentalCheckout($db, $cart);
    $failed = false;
    try {
        $checkout->createOrder($userId, ['name' => '[TEST] Customer', 'email' => 'completion-' . $key . '@example.test',
            'phone' => '', 'payment_method_id' => $paymentId, 'notes' => '']);
    } catch (RuntimeException) { $failed = true; }
    $assert($failed && $cart->count() === 1, 'Missing proof did not block checkout without clearing Cart.');
    $token = bin2hex(random_bytes(32));
    $orderId = $db->insert('INSERT INTO order_header (order_number, user_id, customer_name, customer_email,
        payment_method_id, payment_status, status_token, subtotal, security_deposit, total_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['TEST-' . $key, $userId, '[TEST] Customer', 'completion-' . $key . '@example.test', $paymentId,
            App\Services\RentalPaymentStatus::PENDING, $token, 125, 40, 165]);
    $db->insert('INSERT INTO order_details (order_header_id, rental_item_id, item_name, quantity,
        rental_start_date, rental_end_date, unit_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$orderId, $itemId, '[TEST] Camera', 1, $date, $date, 125, 125]);
    $catalog = new App\Models\RentalCatalog($db);
    $record = $catalog->find('completion-' . $key);
    $assert($record !== null && !$catalog->isAvailable($record, 1, $date, $date), 'Pending order did not reserve inventory.');
    $db->update('UPDATE order_header SET payment_status = ? WHERE id = ?', [App\Services\RentalPaymentStatus::REJECTED, $orderId]);
    $assert($catalog->isAvailable($record, 1, $date, $date), 'Rejected order still reserved inventory.');
    $db->update('UPDATE order_header SET payment_status = ? WHERE id = ?', [App\Services\RentalPaymentStatus::APPROVED, $orderId]);
    $assert(!$catalog->isAvailable($record, 1, $date, $date), 'Approved order did not reserve inventory.');
    $calendarRequest = $request([], 'GET');
    $_GET = ['id' => 'completion-' . $key, 'month' => substr($date, 0, 7), 'quantity' => '1'];
    $calendarRequest = App\Core\Request::capture();
    $calendar = json_decode($cartController->availability($calendarRequest)->body(), true);
    $reservedDay = array_values(array_filter($calendar['days'] ?? [], static fn (array $day): bool => $day['date'] === $date));
    $assert(($calendar['ok'] ?? false) && count($reservedDay) === 1 && !$reservedDay[0]['available'],
        'Calendar did not mark approved reservation unavailable.');
    $_GET = [];

    $db->update('UPDATE order_details SET item_name = ? WHERE order_header_id = ?', ['QA Camera', $orderId]);
    $insights = new App\Services\RentalAdminInsights($db);
    $beforeSales = (float) $insights->analytics($request([], 'GET'))['kpis']['sales'];
    foreach ([App\Services\RentalPaymentStatus::PENDING, App\Services\RentalPaymentStatus::REJECTED] as $state) {
        $reportOrderId = $db->insert('INSERT INTO order_header (order_number, user_id, customer_name, customer_email,
            payment_method_id, payment_status, status_token, subtotal, security_deposit, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['TEST-' . $key . '-' . $state, $userId, '[TEST] Customer', 'completion-' . $key . '@example.test',
                $paymentId, $state, bin2hex(random_bytes(32)), 999, 0, 999]);
        $db->insert('INSERT INTO order_details (order_header_id, rental_item_id, item_name, quantity,
            rental_start_date, rental_end_date, unit_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$reportOrderId, $itemId, 'QA Camera', 1, $date, $date, 999, 999]);
    }
    $report = $insights->salesReport($request([], 'GET'));
    $numbers = array_column($report['rows'], 'order_number');
    $assert(in_array('TEST-' . $key, $numbers, true)
        && !in_array('TEST-' . $key . '-0', $numbers, true)
        && !in_array('TEST-' . $key . '-2', $numbers, true), 'Sales Report included Pending or Rejected orders.');
    $assert((float) $insights->analytics($request([], 'GET'))['kpis']['sales'] === $beforeSales,
        'Pending or Rejected orders inflated Analytics revenue.');

    $multiId = $db->insert('INSERT INTO rental_items (category_id, name, slug, availability_status, rental_unit,
        rental_rate, security_deposit, available_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$categoryId, '[TEST] Multi Stock', 'multi-' . $key, 'available', 'day', 125, 0, 3]);
    $firstDay = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    $middleDay = (new DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d');
    $lastDay = (new DateTimeImmutable($date))->modify('+3 days')->format('Y-m-d');
    foreach ([$firstDay, $lastDay] as $bookedDay) {
        $bookingId = $db->insert('INSERT INTO order_header (order_number, user_id, customer_name, customer_email,
            payment_method_id, payment_status, status_token, subtotal, security_deposit, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['TEST-' . $key . '-' . $bookedDay, $userId, '[TEST] Customer', 'completion-' . $key . '@example.test',
                $paymentId, App\Services\RentalPaymentStatus::PENDING, bin2hex(random_bytes(32)), 250, 0, 250]);
        $db->insert('INSERT INTO order_details (order_header_id, rental_item_id, item_name, quantity,
            rental_start_date, rental_end_date, unit_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$bookingId, $multiId, '[TEST] Multi Stock', 2, $bookedDay, $bookedDay, 125, 250]);
    }
    $multi = $catalog->find('multi-' . $key);
    $assert($multi !== null && $catalog->isAvailable($multi, 1, $firstDay, $lastDay)
        && !$catalog->isAvailable($multi, 2, $firstDay, $lastDay),
        'Disjoint reservations were incorrectly added together, or quantity was ignored.');
    $assert(App\Services\RentalPaymentStatus::label(0) === 'Pending'
        && App\Services\RentalPaymentStatus::label(1) === 'Approved'
        && App\Services\RentalPaymentStatus::label(2) === 'Rejected', 'Status labels are inconsistent.');
    $admin = new App\Controllers\Rentals\RentalAdminController($container);
    $assert($admin->index($request([], 'GET'))->status() === 403, 'Customer could access Admin.');
    $adminId = $db->insert('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        ['[TEST] Admin', 'completion-admin-' . $key . '@example.test', password_hash(random_bytes(16), PASSWORD_DEFAULT), 'admin']);
    $_SESSION['user_id'] = $adminId;
    $assert($admin->index($request([], 'GET'))->status() === 200, 'Admin dashboard failed.');
    $assert($admin->section($request([], 'GET'), 'analytics')->status() === 200, 'Analytics page failed.');
    foreach (['sales-report', 'payment-report', 'orders', 'items', 'categories', 'payments'] as $section) {
        $assert($admin->section($request([], 'GET'), $section)->status() === 200, 'Admin section failed: ' . $section);
    }
    $assert($admin->save($request(['id' => (string) $orderId, 'payment_status' => '1']), 'orders')->status() === 404,
        'Generic order status edit is still available.');
    echo "PASS: dated cart, mandatory proof, transactional totals, Pending/Approved/Rejected stock behavior, Admin guards and reports.\n";
} finally {
    $db->rollBack();
    $_SESSION = [];
}
