<?php

declare(strict_types=1);

// Local HTTP workflow test. Only uniquely named test records are removed.
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?$#', $base)) {
    throw new RuntimeException('Pass a local development base URL.');
}
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$key = bin2hex(random_bytes(6));
$name = '[TEST] HTTP Flow ' . $key;
$email = 'http-flow-' . $key . '@example.test';
$adminEmail = 'http-admin-' . $key . '@example.test';
$password = bin2hex(random_bytes(14));
$adminPassword = bin2hex(random_bytes(14));
$jar = tempnam(sys_get_temp_dir(), 'rentals-cookie-');
$proof = tempnam(sys_get_temp_dir(), 'rentals-proof-');
$invalidProof = tempnam(sys_get_temp_dir(), 'rentals-invalid-proof-');
$largeProof = tempnam(sys_get_temp_dir(), 'rentals-large-proof-');
if ($jar === false || $proof === false || $invalidProof === false || $largeProof === false) { throw new RuntimeException('Temporary files unavailable.'); }
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', true);
file_put_contents($proof, $png);
file_put_contents($invalidProof, 'not an image');
file_put_contents($largeProof, $png . str_repeat('0', 5 * 1024 * 1024));
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$http = static function (string $path, ?array $post = null, array $headers = []) use ($base, $jar): array {
    $h = curl_init($base . $path);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 20, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar]);
    if ($post !== null) {
        curl_setopt($h, CURLOPT_POST, true);
        curl_setopt($h, CURLOPT_POSTFIELDS, in_array(true, array_map(static fn ($v): bool => $v instanceof CURLFile, $post), true)
            ? $post : http_build_query($post));
    }
    if ($headers !== []) { curl_setopt($h, CURLOPT_HTTPHEADER, $headers); }
    $body = curl_exec($h);
    if ($body === false) { throw new RuntimeException('HTTP failure: ' . curl_error($h)); }
    $result = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) $body, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    curl_close($h);
    return $result;
};
$token = static function (string $html): string {
    if (preg_match('/name="_token" value="([^"]+)"/', $html, $m) !== 1) { throw new RuntimeException('Missing CSRF token.'); }
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
};
$asset = glob(dirname(__DIR__) . '/media/rentals-*')[0] ?? null;
$assert($asset !== null, 'No local Rentals image exists for the test.');
$date = (new DateTimeImmutable('today'))->modify('+130 days')->format('Y-m-d');
$categoryId = 0; $methodId = 0; $userId = 0; $adminId = 0;
$itemIds = [];
try {
    $categoryId = $db->insert('INSERT INTO rental_categories (name, slug) VALUES (?, ?)', [$name, 'http-flow-' . $key]);
    $methodId = $db->insert('INSERT INTO payment_methods (name, type, provider, account_name, account_number) VALUES (?, ?, ?, ?, ?)', [$name, 'manual', 'Test Bank', 'Test Account', '0000000000']);
    foreach (['camera', 'light'] as $kind) {
        $itemIds[$kind] = $db->insert('INSERT INTO rental_items (category_id, name, slug, image_path, availability_status,
            rental_unit, rental_rate, security_deposit, available_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $name . ' ' . $kind, 'http-flow-' . $kind . '-' . $key,
                'media/' . basename($asset), 'available', 'day', 100, 20, 1]);
    }
    [$status, $items] = $http('/rentals/items');
    $assert($status === 200 && str_contains($items, $name), 'Equipment page failed.');
    [$status, $error] = $http('/rentals/cart/add', ['_token' => $token($items), 'id' => 'http-flow-camera-' . $key],
        ['X-Requested-With: XMLHttpRequest']);
    $error = json_decode($error, true);
    $assert($status === 422 && ($error['ok'] ?? null) === false
        && str_contains((string) ($error['message'] ?? ''), 'Select your rental dates'),
        'AJAX validation did not return a useful 422 JSON message.');
    [$status, $availability] = $http('/rentals/availability?id=http-flow-camera-' . $key . '&month=' . substr($date, 0, 7) . '&quantity=1');
    $calendar = json_decode($availability, true);
    $assert($status === 200 && ($calendar['ok'] ?? false) === true && count($calendar['days'] ?? []) >= 28,
        'Availability calendar endpoint failed.');
    [$status, $cart] = $http('/rentals/cart/add', ['_token' => $token($items), 'id' => 'http-flow-camera-' . $key]);
    $assert($status === 200 && str_contains($cart, 'Select your rental dates'), 'Undated Add to Cart was not rejected.');
    foreach (['camera', 'light'] as $kind) {
        [$status, $cart] = $http('/rentals/cart/add', ['_token' => $token($kind === 'camera' ? $items : $cart),
            'id' => 'http-flow-' . $kind . '-' . $key, 'quantity' => '1',
            'rental_start_date' => $date, 'rental_end_date' => $date]);
        $assert($status === 200 && str_contains($cart, $name . ' ' . $kind), 'Date-aware Add to Cart failed for ' . $kind);
    }
    [$status, $account] = $http('/rentals/account');
    [$status, $cart] = $http('/rentals/account', ['_token' => $token($account), 'action' => 'register',
        'name' => $name, 'email' => $email, 'password' => $password]);
    $assert($status === 200, 'Registration failed.');
    $userId = (int) $db->selectValue('SELECT id FROM users WHERE email = ?', [$email]);
    $assert($userId > 0 && (int) $db->selectValue('SELECT COUNT(*) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?', [$userId]) === 2,
        'Two dated items did not migrate into one customer cart.');
    [$status] = $http('/rentals/admin');
    $assert($status === 403, 'Customer could access Admin.');
    [$status, $checkout] = $http('/rentals/checkout');
    $assert($status === 200 && str_contains($checkout, 'Proof of payment')
        && str_contains($checkout, 'Test Account') && str_contains($checkout, '0000000000'),
        'Checkout did not show configured manual-payment instructions and request proof.');
    $baseCheckout = ['_token' => $token($checkout), 'customer_name' => $name, 'customer_email' => $email,
        'customer_phone' => '', 'payment_method_id' => (string) $methodId];
    [$status] = $http('/rentals/checkout', $baseCheckout);
    $assert($status === 422 && (int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id = ?', [$userId]) === 0,
        'Checkout accepted a missing payment proof.');
    foreach ([$invalidProof => 'invalid', $largeProof => 'oversized'] as $file => $reason) {
        [$status] = $http('/rentals/checkout', $baseCheckout + ['proof' => new CURLFile($file, 'image/png', 'proof.png')]);
        $assert($status === 422 && (int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id = ?', [$userId]) === 0,
            'Checkout accepted an ' . $reason . ' payment proof.');
    }
    $db->update('UPDATE payment_methods SET is_active = 0 WHERE id = ?', [$methodId]);
    [$status] = $http('/rentals/checkout', $baseCheckout + ['proof' => new CURLFile($proof, 'image/png', 'proof.png')]);
    $assert($status === 422 && (int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id = ?', [$userId]) === 0,
        'Checkout accepted an inactive payment method.');
    $db->update('UPDATE payment_methods SET is_active = 1 WHERE id = ?', [$methodId]);
    [$status, $confirmation] = $http('/rentals/checkout', [
        '_token' => $token($checkout), 'customer_name' => $name, 'customer_email' => $email,
        'customer_phone' => '', 'payment_method_id' => (string) $methodId,
        'payment_reference' => 'LOCAL-' . $key, 'notes' => 'HTTP test',
        'proof' => new CURLFile($proof, 'image/png', 'proof.png'),
    ]);
    $assert($status === 200 && str_contains($confirmation, 'Request received') && str_contains($confirmation, 'data-rentals-qr'), 'Checkout/QR confirmation failed.');
    [$duplicateStatus] = $http('/rentals/checkout', [
        '_token' => $token($checkout), 'customer_name' => $name, 'customer_email' => $email,
        'payment_method_id' => (string) $methodId, 'proof' => new CURLFile($proof, 'image/png', 'proof.png'),
    ]);
    $assert($duplicateStatus !== 200 || (int) $db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id = ?', [$userId]) === 1,
        'Duplicate submission created another order.');
    $orders = $db->select('SELECT id, order_number, payment_status, payment_proof_path, status_token FROM order_header WHERE user_id = ?', [$userId]);
    $assert(count($orders) === 1 && (int) $orders[0]['payment_status'] === 0, 'Checkout did not create one pending order header.');
    $order = $orders[0];
    $assert((int) $db->selectValue('SELECT COUNT(*) FROM order_details WHERE order_header_id = ?', [(int) $order['id']]) === 2,
        'Checkout did not create two details under one header.');
    $assert(preg_match('/^[a-f0-9]{64}$/', (string) $order['status_token']) === 1, 'Secure status token is missing.');
    $assert(preg_match('#^micro/payment/[a-f0-9]{32}\.png$#', (string) $order['payment_proof_path']) === 1,
        'Proof path is not project-relative and random.');
    $assert(App\Services\RentalPaymentProof::path($order['payment_proof_path']) !== null, 'Proof file is missing.');
    [$status, $raw] = $http('/' . $order['payment_proof_path']);
    $assert($status !== 200 || !str_starts_with($raw, $png), 'Direct URL exposed the plaintext proof.');
    [$status, $privateProof] = $http('/rentals/orders/' . (int) $order['id'] . '/proof');
    $assert($status === 200 && $privateProof === $png, 'Authorized proof route failed to decrypt the proof.');
    [$status, $statusPage] = $http('/rentals/order-status/' . $order['status_token']);
    $assert($status === 200 && str_contains($statusPage, 'Pending') && str_contains($statusPage, 'data-rentals-qr'), 'Token status page failed.');
    [$status] = $http('/rentals/order-status/' . str_repeat('a', 64));
    $assert($status === 404, 'Invalid status token was accepted.');
    $catalog = new App\Models\RentalCatalog($db);
    $record = $catalog->find('http-flow-camera-' . $key);
    $assert($record !== null && !$catalog->isAvailable($record, 1, $date, $date), 'Pending order did not reserve stock.');
    [$status, $signedIn] = $http('/rentals/account');
    $http('/rentals/logout', ['_token' => $token($signedIn)]);
    $adminId = $db->insert('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        [$name . ' Admin', $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
    [$status, $login] = $http('/rentals/account');
    [$status, $dashboard, $finalUrl] = $http('/rentals/account', ['_token' => $token($login),
        'action' => 'login', 'email' => $adminEmail, 'password' => $adminPassword]);
    $assert($status === 200 && str_ends_with($finalUrl, '/rentals/admin') && str_contains($dashboard, 'Total rental sales'),
        'Admin login did not redirect straight to dashboard.');
    $assert(!str_contains($dashboard, 'dashboard-analytics-heading') && str_contains($dashboard, '>Analytics</a>'),
        'Analytics should be separate from Dashboard.');
    [$status, $filteredDashboard] = $http('/rentals/admin/analytics?period=7d');
    $assert($status === 200 && str_contains($filteredDashboard, 'Rental revenue over time'), 'Dedicated analytics filter failed.');
    foreach (['sales-report', 'payment-report', 'orders', 'items', 'categories', 'payments'] as $section) {
        [$status] = $http('/rentals/admin/' . $section);
        $assert($status === 200, 'Admin section failed: ' . $section);
    }
    [$status, $orderView] = $http('/rentals/admin/orders?edit=' . (int) $order['id']);
    $assert($status === 200 && str_contains($orderView, 'Payment proof review'), 'Admin review page failed.');
    [$status] = $http('/rentals/admin/orders/' . (int) $order['id'] . '/review',
        ['_token' => $token($orderView), 'decision' => 'approved']);
    $reviewed = $db->selectOne('SELECT payment_status, payment_reviewed_at, payment_reviewed_by, paid_at FROM order_header WHERE id = ?', [(int) $order['id']]);
    $assert($status === 200 && (int) $reviewed['payment_status'] === 1 && $reviewed['payment_reviewed_at'] !== null
        && (int) $reviewed['payment_reviewed_by'] === $adminId && $reviewed['paid_at'] !== null, 'Approval did not update one status source.');
    [$status, $salesReport] = $http('/rentals/admin/sales-report');
    $assert($status === 200 && !str_contains($salesReport, (string) $order['order_number']),
        'Local [TEST] order appeared as a real sale.');
    [$status, $statusPage] = $http('/rentals/order-status/' . $order['status_token']);
    $assert($status === 200 && str_contains($statusPage, 'Approved'), 'Status page did not reflect approval.');
    $db->update('UPDATE users SET role = ? WHERE id = ?', ['superadmin', $adminId]);
    [$status, $adminPage] = $http('/rentals/admin');
    $http('/rentals/logout', ['_token' => $token($adminPage)]);
    [$status, $login] = $http('/rentals/account');
    [$status, $customerPage] = $http('/rentals/account', ['_token' => $token($login),
        'action' => 'login', 'email' => $email, 'password' => $password]);
    $assert($status === 200 && !str_contains($customerPage, 'Rentals Admin navigation'), 'Customer login did not return to customer UX.');
    $nextDate = (new DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d');
    [$status, $items] = $http('/rentals/items');
    [$status, $cart] = $http('/rentals/cart/add', ['_token' => $token($items), 'id' => 'http-flow-camera-' . $key,
        'quantity' => '1', 'rental_start_date' => $nextDate, 'rental_end_date' => $nextDate]);
    $assert($status === 200 && str_contains($cart, $name . ' camera'), 'Second dated cart failed.');
    [$status, $checkout] = $http('/rentals/checkout');
    [$status] = $http('/rentals/checkout', [
        '_token' => $token($checkout), 'customer_name' => $name, 'customer_email' => $email,
        'customer_phone' => '', 'payment_method_id' => (string) $methodId,
        'proof' => new CURLFile($proof, 'image/png', 'proof.png'),
    ]);
    $assert($status === 200, 'Second checkout failed.');
    $secondOrder = $db->selectOne('SELECT id, status_token, payment_status FROM order_header WHERE user_id = ? AND id <> ?', [$userId, (int) $order['id']]);
    $assert($secondOrder !== null && (int) $secondOrder['payment_status'] === 0, 'Second order is not pending.');
    [$status, $signedIn] = $http('/rentals/account');
    $http('/rentals/logout', ['_token' => $token($signedIn)]);
    [$status, $login] = $http('/rentals/account');
    [$status, , $finalUrl] = $http('/rentals/account', ['_token' => $token($login),
        'action' => 'login', 'email' => $adminEmail, 'password' => $adminPassword]);
    $assert($status === 200 && str_ends_with($finalUrl, '/rentals/admin'), 'Superadmin direct login failed.');
    [$status, $orderView] = $http('/rentals/admin/orders?edit=' . (int) $secondOrder['id']);
    [$status] = $http('/rentals/admin/orders/' . (int) $secondOrder['id'] . '/review',
        ['_token' => $token($orderView), 'decision' => 'rejected']);
    $rejected = $db->selectOne('SELECT payment_status, payment_reviewed_at, payment_reviewed_by, paid_at FROM order_header WHERE id = ?', [(int) $secondOrder['id']]);
    $assert($status === 200 && (int) $rejected['payment_status'] === 2 && $rejected['payment_reviewed_at'] !== null
        && (int) $rejected['payment_reviewed_by'] === $adminId && $rejected['paid_at'] === null,
        'Rejected proof did not update payment review metadata.');
    $assert($catalog->isAvailable($record, 1, $nextDate, $nextDate), 'Rejected order still reserves equipment.');
    [$status, $statusPage] = $http('/rentals/order-status/' . $secondOrder['status_token']);
    $assert($status === 200 && str_contains($statusPage, 'Rejected'), 'Status page did not reflect rejection.');
    [$status, $adminPage] = $http('/rentals/admin');
    $http('/rentals/logout', ['_token' => $token($adminPage)]);
    [$status, $login] = $http('/rentals/account');
    [$status] = $http('/rentals/account', ['_token' => $token($login),
        'action' => 'login', 'email' => $email, 'password' => $password]);
    [$status, $myRentals] = $http('/rentals/orders');
    [$status] = $http('/rentals/orders/' . (int) $secondOrder['id'] . '/proof', [
        '_token' => $token($myRentals), 'payment_reference' => 'RETRY-' . $key,
        'proof' => new CURLFile($proof, 'image/png', 'proof.png'),
    ]);
    $assert($status === 200 && (int) $db->selectValue('SELECT payment_status FROM order_header WHERE id = ?', [(int) $secondOrder['id']]) === 0
        && !$catalog->isAvailable($record, 1, $nextDate, $nextDate), 'Resubmitted proof did not return to Pending and reserve stock.');
    echo "PASS: dated multi-item cart, one header/two details, encrypted proof, QR/status, Admin login, reports, approval, rejection and proof resubmission.\n";
} finally {
    if ($userId > 0) {
        foreach ($db->select('SELECT id, payment_proof_path FROM order_header WHERE user_id = ?', [$userId]) as $row) {
            App\Services\RentalPaymentProof::remove($row['payment_proof_path']);
            $db->delete('DELETE FROM order_details WHERE order_header_id = ?', [(int) $row['id']]);
            $db->delete('DELETE FROM order_header WHERE id = ?', [(int) $row['id']]);
        }
        $db->delete('DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = ?)', [$userId]);
        $db->delete('DELETE FROM carts WHERE user_id = ?', [$userId]);
        $db->delete('DELETE FROM users WHERE id = ?', [$userId]);
    }
    if ($adminId > 0) { $db->delete('DELETE FROM users WHERE id = ?', [$adminId]); }
    foreach ($itemIds as $id) { $db->delete('DELETE FROM rental_items WHERE id = ?', [$id]); }
    if ($categoryId > 0) { $db->delete('DELETE FROM rental_categories WHERE id = ?', [$categoryId]); }
    if ($methodId > 0) { $db->delete('DELETE FROM payment_methods WHERE id = ?', [$methodId]); }
    @unlink($jar); @unlink($proof); @unlink($invalidProof); @unlink($largeProof);
}
