<?php

declare(strict_types=1);

// Local-only regression fixtures; every database write is rolled back. No mail/files uploaded.
$_ENV['MAIL_ENABLED'] = 'false';
session_start();
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Local loopback database only.');
}
$db = $container->get(App\Core\Database::class);
$check = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$rejects = static function (callable $operation, string $message) use ($check): void {
    try { $operation(); } catch (RuntimeException $e) {
        $check(str_contains($e->getMessage(), $message), 'Unexpected rejection: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
};
$request = static function (array $post = [], string $method = 'POST'): App\Core\Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/rentals/checkout';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = $post; $_GET = []; $_FILES = [];
    return App\Core\Request::capture();
};
$tomorrow = new DateTimeImmutable('tomorrow');
$start = $tomorrow->format('Y-m-d');
$last = $tomorrow->modify('+399 days')->format('Y-m-d');
$check(App\Services\RentalDateRange::isBookable($start, $last), '400 inclusive days rejected.');
$check(!App\Services\RentalDateRange::isBookable($start, $tomorrow->modify('+400 days')->format('Y-m-d')), '401 days accepted.');
$check(!App\Services\RentalDateRange::isBookable($start, '9999-12-31'), 'Extreme range accepted.');
$check(!App\Services\RentalDateRange::isBookable(date('Y-m-d', strtotime('-1 day')), $start), 'Past booking accepted.');
foreach ([['', ''], ['2028-02-30', '2028-03-01'], [$start, '2026-01-01'], ["$start\0", $start]] as [$from, $through]) {
    $check(App\Services\RentalDateRange::parse($from, $through) === null, 'Invalid date range accepted.');
}
$check(App\Services\RentalDateRange::parse('2028-02-28', '2028-03-01') !== null, 'Leap-day range rejected.');
$check(App\Services\RentalDateRange::isBookable(date('Y-m-d'), (new DateTimeImmutable('today'))->modify('+12 months')->modify('last day of this month')->format('Y-m-d')), 'Existing calendar window exceeds safety cap.');
// An invalid range must return before even opening a database connection.
$check((new App\Models\RentalCatalog(new App\Core\Database([])))->availabilityByDate(['db_id' => 1], $start, '9999-12-31') === [], 'Model did not reject extreme range before SQL.');

$db->beginTransaction();
try {
    $key = bin2hex(random_bytes(6));
    $email = 'review-' . $key . '@example.test';
    $password = 'ReviewFixturePassword123';
    $user = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)', ['[TEST] Review customer', $email, password_hash($password, PASSWORD_DEFAULT), 'customer']);
    $admin = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)', ['[TEST] Review admin', 'review-admin-' . $key . '@example.test', password_hash($password, PASSWORD_DEFAULT), 'admin']);
    $category = $db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)', ['[TEST] Review ' . $key, 'review-' . $key]);
    $item = $db->insert('INSERT INTO rental_items(category_id,name,slug,rental_rate,available_quantity,availability_status) VALUES (?,?,?,?,?,?)', [$category, '[TEST] Review camera', 'review-camera-' . $key, 100, 10, 'available']);
    $method = $db->insert('INSERT INTO payment_methods(name,type,account_name,account_number) VALUES (?,?,?,?)', ['[TEST] Review ' . $key, 'manual', 'QA account', '000000']);
    $_SESSION = ['user_id' => $user, 'rentals_authenticated_at' => microtime(true)];
    $cart = new App\Services\RentalCart();
    $account = new App\Services\RentalAccount($db, $cart);
    $check($account->current() !== null, 'Valid account rejected.');
    $validSession = $_SESSION;
    $line = $cart->add('review-camera-' . $key, 1, $start, $start);
    $lineId = (int)$line['line_id'];
    $staleSession = array_replace($validSession, ['rentals_password_stamp' => str_repeat('0', 64)]);
    $_SESSION = $staleSession;
    $check($cart->contents() === [] && !isset($_SESSION['user_id']), 'Stale session read authenticated cart.');
    foreach (['remove', 'update', 'clear'] as $action) {
        $_SESSION = $staleSession;
        match ($action) {
            'remove' => $cart->remove((string)$lineId),
            'update' => $cart->update((string)$lineId, 9, $start, $start),
            'clear' => $cart->clear(),
        };
        $check((int)$db->selectValue('SELECT quantity FROM cart_items WHERE id=?', [$lineId]) === 1, 'Stale session changed database cart.');
    }
    $controller = new App\Controllers\Rentals\RentalCheckoutController($container);
    foreach (['show', 'submit'] as $action) {
        $_SESSION = $staleSession;
        $response = $controller->$action($request([], $action === 'show' ? 'GET' : 'POST'));
        $check($response->status() === 302 && str_ends_with((string)$response->header('Location'), '/rentals/account'), 'Stale Checkout request did not require login.');
    }
    $_SESSION = $staleSession;
    $rejects(static fn () => (new App\Services\RentalCheckout($db, $cart))->createOrder($user, []), 'authenticated customer');
    $_SESSION = $validSession;
    $check($controller->show($request([], 'GET'))->status() === 200, 'Normal Checkout no longer loads.');
    $badRange = ['id' => 'review-camera-' . $key, 'quantity' => 1, 'rental_start_date' => $start, 'rental_end_date' => '9999-12-31'];
    $check((new App\Controllers\Rentals\RentalCartController($container))->add($request($badRange))->status() === 422, 'Extreme Add to Cart request was not rejected.');
    $check((int)$db->selectValue('SELECT quantity FROM cart_items WHERE id=?', [$lineId]) === 1, 'Rejected date request changed Cart.');
    $db->insert('INSERT INTO rental_password_resets(user_id,token_hash,expires_at,reset_at,created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)', [$user, hash('sha256', $key), date('Y-m-d H:i:s', time()+1800), date('Y-m-d H:i:s')]);
    $_SESSION = ['user_id' => $user, 'rentals_authenticated_at' => time()-60];
    $check($cart->contents() === [] && !isset($_SESSION['user_id']), 'Legacy pre-reset session read Cart.');
    $account->login($email, $password);
    $check($cart->count() === 1, 'Fresh login failed or changed saved Cart.');

    $service = $db->insert('INSERT INTO rental_items(category_id,name,slug,is_service,availability_status) VALUES (?,?,?,?,?)', [$category, '[TEST] Review service', 'review-service-' . $key, 1, 'inquire']);
    $store = new App\Services\RentalServiceRequests($db);
    $record = $store->submit($user, $service, hash('sha256', $key . '-service'), ['phone'=>'QA phone', 'start_date'=>$start, 'end_date'=>$start, 'location'=>'QA venue', 'details'=>'Camera and crew']);
    $id = (int)$record['id'];
    $store->review($id, $admin, 'approved', '');
    $store->quote($id, $admin, '1000', 'Original scope');
    $store->quote($id, $admin, '1200', 'Updated scope');
    foreach ([null, -1, 1] as $version) {
        $rejects(static fn () => $store->submitPayment($id, $user, $method, '', null, $version), 'quotation');
        $unchanged = $store->find($id);
        $check($unchanged['payment_status'] === 'unpaid' && $unchanged['payment_proof_path'] === null && (int)$unchanged['quote_version'] === 2, 'Stale payment changed request.');
    }
    $rejects(static fn () => $store->submitPayment($id, $user, $method, '', null, 2), 'Choose a JPG');
    $db->update("UPDATE rental_service_requests SET payment_status='pending',payment_proof_path=? WHERE id=?", ['micro/payment/' . str_repeat('a', 32) . '.jpg', $id]);
    $check(!$store->submitPayment($id, $user, $method, '', null, 1), 'Payment replay was not ignored.');
    echo "PASS: bounded dates including leap/boundary/invalid/extreme input; reject-before-SQL; stale and legacy-reset session protection in Cart/Checkout; normal login/Cart/Checkout; quotation revision rejection before upload; payment replay unchanged. All fixture rows rolled back.\n";
} finally {
    $db->rollBack();
    $_SESSION = [];
}
