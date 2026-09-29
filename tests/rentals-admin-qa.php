<?php
declare(strict_types=1);

// Real local HTTP + database QA. Only uniquely identified fixtures are removed.
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^http://(?:localhost|127\.0\.0\.1):\d+(?:/[A-Za-z0-9_/-]+)?$#', $base)) {
    throw new RuntimeException('Supply a local QA-server URL.');
}
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$key = bin2hex(random_bytes(6));
$name = '[TEST] Admin QA ' . $key;
$email = 'admin-qa-' . $key . '@example.test';
$password = bin2hex(random_bytes(16));
$mount = (string) parse_url($base, PHP_URL_PATH);
$jar = tempnam(sys_get_temp_dir(), 'rentals-qa-cookie-');
$picture = tempnam(sys_get_temp_dir(), 'rentals-qa-image-');
$malicious = tempnam(sys_get_temp_dir(), 'rentals-qa-invalid-');
$large = tempnam(sys_get_temp_dir(), 'rentals-qa-large-');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=');
file_put_contents($picture, $png);
file_put_contents($malicious, '<?php echo "unsafe"; ?>');
file_put_contents($large, $png . str_repeat('0', 5 * 1024 * 1024));
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$http = static function (string $path, ?array $post = null, array $headers = []) use ($base, $jar, $mount, $assert): array {
    $h = curl_init($base . $path);
    $received = '';
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 20, CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADERFUNCTION => static function ($h, string $line) use (&$received, $mount, $assert): int {
            $received .= $line;
            if ($mount !== '' && stripos($line, 'Location:') === 0) {
                $assert(!str_contains($line, $mount . $mount . '/'), 'Duplicated mount in Location header.');
            }
            return strlen($line);
        }]);
    if ($post !== null) {
        curl_setopt($h, CURLOPT_POST, true);
        curl_setopt($h, CURLOPT_POSTFIELDS, array_filter($post, static fn ($v) => $v instanceof CURLFile)
            ? $post : http_build_query($post));
    }
    if ($headers) { curl_setopt($h, CURLOPT_HTTPHEADER, $headers); }
    $body = curl_exec($h);
    if ($body === false) { throw new RuntimeException(curl_error($h)); }
    $result = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) $body,
        (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), $received];
    curl_close($h);
    return $result;
};
$token = static function (string $html): string {
    if (!preg_match('/name="_token" value="([^"<>]+)"/', $html, $m)) { throw new RuntimeException('Missing form token.'); }
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
};
$adminId = $categoryId = $itemId = $methodId = 0;
$orderIds = [];
$extraItemIds = [];
try {
    foreach (['/rentals/admin' => '/rentals/account', '/rentals/orders' => '/rentals/account', '/rentals/checkout' => '/rentals/cart'] as $path => $destination) {
        [$code, , $final] = $http($path);
        $assert($code === 200 && $final === $base . $destination, 'Guest redirect failed: ' . $path);
    }
    [$code, $information] = $http('/information');
    $assert($code === 200 && str_contains($information, 'href="' . $mount . '/rentals">Start renting'), 'Start Renting URL failed.');
    [$code, $error, , $errorHeaders] = $http('/_qa-error');
    $assert($code === 500 && !str_contains($error, 'RuntimeException') && !str_contains($error, 'SQLSTATE')
        && !str_contains($error, dirname(__DIR__)), 'Production error exposed internals.');
    foreach (['Content-Security-Policy:', 'X-Frame-Options:', 'Referrer-Policy:', 'X-Content-Type-Options:'] as $header) {
        $assert(stripos($errorHeaders, $header) !== false, 'Error response lost security header: ' . $header);
    }
    [$code, $sessionError, , $sessionHeaders] = $http('/_qa-session-error');
    $assert($code === 500 && !str_contains($sessionError, 'session_start') && !str_contains($sessionError, dirname(__DIR__))
        && stripos($sessionHeaders, 'Content-Security-Policy:') !== false, 'Session failure exposed internals or lost security headers.');
    $adminId = $db->insert('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    [, $login] = $http('/rentals/account');
    [$code, $dashboard, $final] = $http('/rentals/account', ['_token' => $token($login), 'action' => 'login', 'email' => $email, 'password' => $password]);
    $assert($code === 200 && $final === $base . '/rentals/admin', 'Admin direct-login URL failed.');
    [, $editor] = $http('/rentals/admin/categories?new=1');
    [$code, $categoryList] = $http('/rentals/admin/categories', ['_token' => $token($editor), 'name' => $name, 'slug' => 'qa-' . $key, 'is_active' => '1']);
    $categoryId = (int) $db->selectValue('SELECT id FROM rental_categories WHERE slug = ?', ['qa-' . $key]);
    $assert($code === 200 && $categoryId > 0 && str_contains($categoryList, $name), 'Category creation failed.');
    [, $editor, $addUrl] = $http('/rentals/admin/items?new=1');
    $assert($addUrl === $base . '/rentals/admin/items?new=1' && str_contains($editor, $name), 'Product category dropdown or Add URL failed.');
    $product = ['_token' => $token($editor), 'category_id' => (string) $categoryId, 'name' => $name,
        'slug' => 'qa-product-' . $key, 'sku' => 'PRIVATE-' . $key, 'description' => 'QA original description',
        'ideal_use' => 'QA production', 'is_service' => '0', 'availability_status' => 'available',
        'rental_unit' => 'day', 'rental_rate' => '125.00', 'security_deposit' => '10000.00',
        'available_quantity' => '3', 'is_active' => '1'];
    foreach ([$malicious, $large] as $badFile) {
        [$code, $failure] = $http('/rentals/admin/items', $product + ['product_image' => new CURLFile($badFile, 'image/jpeg', 'photo.jpg')]);
        $assert(in_array($code, [200, 413], true) && !$db->selectValue('SELECT id FROM rental_items WHERE slug = ?', [$product['slug']]), 'Invalid image created a product or returned an unexpected response.');
        if ($code === 413) { $assert(str_contains($failure, 'upload limit'), 'Oversized upload was reported as a session/slug error.'); }
    }
    [$code] = $http('/rentals/admin/items', $product + ['product_image' => new CURLFile($picture, 'image/png', '../unsafe.php.png')]);
    $item = $db->selectOne('SELECT * FROM rental_items WHERE slug = ?', [$product['slug']]);
    $itemId = (int) ($item['id'] ?? 0);
    $assert($code === 200 && $itemId > 0 && App\Services\RentalManagedImage::publicPath($item['image_path'], 'product') !== null, 'Product + image creation failed.');
    $assert(!preg_match('/<input[^>]*name="slug"[^>]*required/', $editor), 'Product URL is still required by the browser.');
    $autoProduct = array_replace($product, ['name' => 'QA Camera ' . $key, 'slug' => '', 'sku' => '']);
    foreach (['qa-camera-' . $key, 'qa-camera-' . $key . '-2'] as $expectedSlug) {
        [$code, $saved] = $http('/rentals/admin/items', $autoProduct);
        $autoItem = $db->selectOne('SELECT * FROM rental_items WHERE slug = ?', [$expectedSlug]);
        if ($autoItem !== null) { $extraItemIds[] = (int) $autoItem['id']; }
        $assert($code === 200 && $autoItem !== null && $autoItem['sku'] === null && str_contains($saved, 'Changes saved.'), 'Blank slug/SKU or duplicate-name product creation failed.');
    }
    $autoId = $extraItemIds[0];
    $http('/rentals/admin/items', array_replace($autoProduct, ['id' => $autoId, 'name' => 'QA renamed ' . $key]));
    $assert($db->selectValue('SELECT slug FROM rental_items WHERE id = ?', [$autoId]) === 'qa-camera-' . $key, 'Blank slug changed an existing product URL.');
    $http('/rentals/admin/items', array_replace($autoProduct, ['id' => $autoId, 'slug' => 'QA Camera / ' . $key . ' Custom!']));
    $assert($db->selectValue('SELECT slug FROM rental_items WHERE id = ?', [$autoId]) === 'qa-camera-' . $key . '-custom', 'Friendly product URL was not normalized.');
    $beforeCount = (int) $db->selectValue('SELECT COUNT(*) FROM rental_items WHERE category_id = ?', [$categoryId]);
    [$code, $rejected] = $http('/rentals/admin/items', array_replace($autoProduct, ['name' => 'QA rejected ' . $key, 'sku' => $product['sku']]));
    $assert($code === 200 && str_contains($rejected, 'That SKU is already used') && str_contains($rejected, 'value="QA rejected ' . $key . '"')
        && str_contains($rejected, 'QA original description') && (int) $db->selectValue('SELECT COUNT(*) FROM rental_items WHERE category_id = ?', [$categoryId]) === $beforeCount,
        'Duplicate SKU did not show a clear error, retain form input, or prevent insertion.');
    [$code, $rejected] = $http('/rentals/admin/items', array_replace($autoProduct, ['sku' => str_repeat('x', 81)]));
    $assert($code === 200 && str_contains($rejected, '80 characters or fewer')
        && (int) $db->selectValue('SELECT COUNT(*) FROM rental_items WHERE category_id = ?', [$categoryId]) === $beforeCount, 'Overlong SKU was silently truncated or inserted.');
    foreach ([
        ['category_id', '', 'Category:'], ['category_id', $categoryId . '.5', 'Category:'], ['category_id', '999999999999999999', 'Choose an existing category'],
        ['name', '  ', 'Name:'], ['name', str_repeat('é', 191), 'Name:'],
        ['is_service', 'camera', 'Type:'], ['availability_status', 'unknown', 'availability status'],
        ['rental_unit', str_repeat('x', 31), 'Rental unit:'], ['ideal_use', str_repeat('x', 501), 'Ideal use:'],
        ['description', str_repeat('x', 5001), 'Description:'], ['slug', str_repeat('x', 191), 'Slug:'],
        ['available_quantity', '', 'Available quantity:'], ['available_quantity', '1.5', 'Available quantity:'],
        ['available_quantity', '-1', 'Available quantity:'], ['available_quantity', 'many', 'Available quantity:'],
        ['available_quantity', '1000000', 'Available quantity:'],
        ['rental_rate', '', 'Rate:'], ['rental_rate', '-1', 'Rate:'], ['rental_rate', '1.234', 'Rate:'], ['rental_rate', '10000000000', 'Rate:'],
        ['security_deposit', '-1', 'Security deposit:'], ['is_active', '2', 'active status'],
    ] as [$field, $value, $message]) {
        [$code, $rejected] = $http('/rentals/admin/items', array_replace($autoProduct, [$field => $value]));
        $assert($code === 200 && str_contains($rejected, $message) && str_contains($rejected, 'role="alert"')
            && (int) $db->selectValue('SELECT COUNT(*) FROM rental_items WHERE category_id = ?', [$categoryId]) === $beforeCount,
            'Product validation failed for ' . $field . '.');
    }
    $valid = array_replace($autoProduct, ['id' => $autoId, 'name' => str_repeat('é', 190), 'sku' => str_repeat('é', 80),
        'rental_unit' => str_repeat('é', 30), 'ideal_use' => str_repeat('é', 500), 'description' => str_repeat('é', 5000),
        'security_deposit' => '', 'rental_rate' => '.50', 'available_quantity' => '999999']);
    $http('/rentals/admin/items', $valid);
    $validRow = $db->selectOne('SELECT * FROM rental_items WHERE id = ?', [$autoId]);
    $assert($validRow['name'] === $valid['name'] && $validRow['sku'] === $valid['sku'] && $validRow['description'] === $valid['description']
        && $validRow['ideal_use'] === $valid['ideal_use'] && $validRow['rental_unit'] === $valid['rental_unit']
        && (float) $validRow['security_deposit'] === 0.0 && (float) $validRow['rental_rate'] === .50, 'Valid Unicode text, optional deposit or decimal rate was rejected/truncated.');
    foreach (['0', '1'] as $type) {
        foreach (['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'] as $availability) {
            $http('/rentals/admin/items', array_replace($autoProduct, ['id' => $autoId, 'is_service' => $type, 'availability_status' => $availability, 'rental_unit' => 'event']));
            $actual = $db->selectOne('SELECT is_service, availability_status, rental_unit FROM rental_items WHERE id = ?', [$autoId]);
            $assert((string) $actual['is_service'] === $type && $actual['availability_status'] === $availability && $actual['rental_unit'] === 'event', 'Valid product type/availability/unit was rejected.');
        }
    }
    $externalImage = App\Services\RentalStorage::path($item['image_path']);
    $assert($externalImage !== null && str_starts_with($externalImage, App\Services\RentalStorage::root() . '/')
        && !is_file(BASE_PATH . '/' . $item['image_path']), 'Product upload was saved inside the checkout.');
    [$imageCode, $imageBytes, , $imageHeaders] = $http('/' . $item['image_path']);
    $assert($imageCode === 200 && $imageBytes === $png && stripos($imageHeaders, 'Content-Type: image/png') !== false, 'Externally stored product image route failed.');
    foreach (['/micro/rentals/products/' . str_repeat('a', 32) . '.php', '/micro/rentals/products/not-an-image.png'] as $badPath) {
        [$code] = $http($badPath);
        $assert($code === 404, 'Invalid managed product image path was served.');
    }
    $oldImage = $item['image_path'];
    [, $public] = $http('/rentals/items');
    $assert(str_contains($public, $name) && str_contains($public, $item['image_path']) && !str_contains($public, $product['sku']), 'Public product/image missing or SKU exposed.');
    $product['id'] = (string) $itemId;
    $product['name'] .= ' edited'; $product['description'] = 'QA edited description';
    $product['rental_rate'] = '150.00'; $product['security_deposit'] = '9000.00'; $product['available_quantity'] = '2';
    [, $editor] = $http('/rentals/admin/items?edit=' . $itemId);
    $product['_token'] = $token($editor);
    $http('/rentals/admin/items', $product);
    $item = $db->selectOne('SELECT * FROM rental_items WHERE id = ?', [$itemId]);
    $assert($item['name'] === $product['name'] && $item['description'] === $product['description'] && (float) $item['rental_rate'] === 150.0
        && (float) $item['security_deposit'] === 9000.0 && (int) $item['available_quantity'] === 2 && $item['image_path'] === $oldImage, 'Product edit did not persist/retain image.');
    $http('/rentals/admin/items', $product + ['product_image' => new CURLFile($picture, 'image/png', 'replacement.png')]);
    $item = $db->selectOne('SELECT * FROM rental_items WHERE id = ?', [$itemId]);
    $assert($item['image_path'] !== $oldImage && App\Services\RentalStorage::path($oldImage) === null, 'Managed product replacement failed.');
    [, $public] = $http('/rentals/items');
    $assert(str_contains($public, $product['description']) && str_contains($public, $product['name']), 'Public product data was stale.');
    [, $editor] = $http('/rentals/admin/payments?new=1');
    $payment = ['_token' => $token($editor), 'name' => $name, 'type' => 'manual', 'provider' => 'QA Bank',
        'account_name' => 'QA Account', 'account_number' => '0000000000', 'is_active' => '1'];
    $http('/rentals/admin/payments', array_replace($payment, ['account_name' => '']));
    $assert(!$db->selectValue('SELECT id FROM payment_methods WHERE name = ?', [$name]), 'Incomplete active payment method was accepted.');
    foreach ([$malicious, $large] as $badFile) {
        $http('/rentals/admin/payments', $payment + ['qr_image' => new CURLFile($badFile, 'image/jpeg', 'qr.jpg')]);
        $assert(!$db->selectValue('SELECT id FROM payment_methods WHERE name = ?', [$name]), 'Invalid QR created a payment method.');
    }
    $http('/rentals/admin/payments', $payment + ['qr_image' => new CURLFile($picture, 'image/png', 'qr.png')]);
    $method = $db->selectOne('SELECT * FROM payment_methods WHERE name = ?', [$name]);
    $methodId = (int) ($method['id'] ?? 0);
    $assert($methodId > 0 && App\Services\RentalManagedImage::publicPath($method['qr_image_path'], 'qr') !== null, 'Payment method QR upload failed.');
    $oldQr = $method['qr_image_path']; $payment['id'] = (string) $methodId;
    $http('/rentals/admin/payments', $payment);
    $assert($db->selectValue('SELECT qr_image_path FROM payment_methods WHERE id = ?', [$methodId]) === $oldQr, 'QR was lost without replacement.');
    $http('/rentals/admin/payments', $payment + ['qr_image' => new CURLFile($picture, 'image/png', 'qr-replacement.png')]);
    $assert(App\Services\RentalStorage::path($oldQr) === null, 'Old managed QR remained after successful replacement.');
    [$code, $qr] = $http('/rentals/payment-qr/' . $methodId);
    $assert($code === 200 && $qr === $png, 'Protected-folder QR route failed.');
    $date = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');
    [, $editor] = $http('/rentals/admin/items?edit=' . $itemId);
    $csrf = $token($editor);
    $http('/rentals/admin/items/' . $itemId . '/blackouts', ['_token' => $csrf, 'start_date' => $date, 'end_date' => $date, 'note' => 'QA maintenance']);
    $blackoutId = (int) $db->selectValue('SELECT id FROM rental_item_blackouts WHERE rental_item_id = ?', [$itemId]);
    $catalog = new App\Models\RentalCatalog($db); $record = $catalog->find((string) $itemId);
    $assert($blackoutId > 0 && !$catalog->isAvailable($record, 1, $date, $date), 'Admin blackout did not block availability.');
    $api = '/rentals/availability?id=' . $itemId . '&month=' . substr($date, 0, 7) . '&quantity=1';
    [, $json] = $http($api);
    $day = array_values(array_filter(json_decode($json, true)['days'], static fn ($d) => $d['date'] === $date))[0];
    $assert(!$day['available'], 'Picker API ignored Admin block.');
    $adminApi = '/rentals/admin/items/' . $itemId . '/availability?month=' . substr($date, 0, 7);
    [$code, $calendarJson] = $http($adminApi);
    $calendarData = json_decode($calendarJson, true);
    $blocked = array_values(array_filter($calendarData['days'], static fn ($d) => $d['date'] === $date))[0];
    $assert($code === 200 && $blocked['admin_blocked'] && $blocked['reserved'] === 0 && $blocked['remaining'] === 0
        && !str_contains($calendarJson, $email), 'Admin calendar did not distinguish manual blocks or exposed personal information.');
    [$code] = $http('/rentals/admin/items/' . $itemId . '/availability?month=invalid');
    $assert($code === 422, 'Admin calendar accepted an invalid month.');
    $http('/rentals/admin/items/' . $itemId . '/blackouts/' . $blackoutId . '/toggle', ['_token' => $csrf]);
    $assert($catalog->isAvailable($record, 2, $date, $date), 'Unblocking failed.');
    foreach ([0, 1, 2] as $state) {
        $orderId = $db->insert('INSERT INTO order_header (order_number, user_id, customer_name, customer_email, payment_method_id,
            payment_status, status_token, subtotal, security_deposit, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['QA-' . $key . '-' . $state, $adminId, $name, $email, $methodId, $state, bin2hex(random_bytes(32)), 300, 9000, 9300]);
        $orderIds[] = $orderId;
        $db->insert('INSERT INTO order_details (order_header_id, rental_item_id, item_name, quantity, rental_start_date, rental_end_date, unit_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $itemId, $name, $state === 1 ? 1 : 2, $date, $date, 150, $state === 1 ? 150 : 300]);
        if ($state === 1) {
            $db->insert('INSERT INTO order_details (order_header_id, rental_item_id, item_name, quantity, rental_start_date, rental_end_date, unit_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $itemId, $name . ' second snapshot', 1, $date, $date, 150, 150]);
        }
    }
    $http('/rentals/admin/items/' . $itemId . '/blackouts/' . $blackoutId . '/toggle', ['_token' => $csrf]);
    $http('/rentals/admin/items/' . $itemId . '/blackouts/' . $blackoutId . '/toggle', ['_token' => $csrf]);
    $assert(!$catalog->isAvailable($record, 1, $date, $date), 'Unblocking overrode a real reservation.');
    [, $calendarJson] = $http($adminApi);
    $reserved = array_values(array_filter(json_decode($calendarJson, true)['days'], static fn ($d) => $d['date'] === $date))[0];
    $assert(!$reserved['admin_blocked'] && $reserved['reserved'] === 4 && $reserved['remaining'] === 0, 'Calendar lost Pending/Approved reservations after removing manual block.');
    foreach (['QA-' . $key . '-0', $name] as $search) {
        [$code, $orders] = $http('/rentals/admin/orders?q=' . rawurlencode($search));
        $assert($code === 200 && str_contains($orders, 'QA-' . $key . '-0'), 'Server-side order search failed.');
    }
    [, $empty] = $http('/rentals/admin/orders?q=qa-no-match-' . $key);
    $assert(str_contains($empty, 'No records match this view.'), 'Orders no-match state failed.');
    foreach ([0 => 'pending', 1 => 'approved', 2 => 'rejected'] as $state => $badge) {
        [, $orders] = $http('/rentals/admin/orders?q=' . rawurlencode($name) . '&status=' . $state);
        $assert(str_contains($orders, 'QA-' . $key . '-' . $state) && str_contains($orders, 'rentals-payment-badge--' . $badge), 'Order filter/badge failed.');
    }
    [$code, $report] = $http('/rentals/admin/payment-report?method=' . $methodId);
    $assert($code === 200 && str_contains($report, 'QA-' . $key . '-0') && str_contains($report, 'Approved total collected')
        && str_contains($report, 'Pending amount') && str_contains($report, '9,300.00'), 'Payment report failed.');
    foreach ([0 => 'pending', 1 => 'approved', 2 => 'rejected'] as $state => $badge) {
        [$code, $filteredReport] = $http('/rentals/admin/payment-report?method=' . $methodId . '&payment_status=' . $state);
        $assert($code === 200 && str_contains($filteredReport, 'QA-' . $key . '-' . $state)
            && str_contains($filteredReport, 'rentals-payment-badge--' . $badge), 'Payment report HTTP/status filter failed.');
    }
    $db->update('UPDATE payment_methods SET is_active=0 WHERE id=?', [$methodId]);
    [$code, $historicalReport] = $http('/rentals/admin/payment-report?method=' . $methodId);
    $assert($code === 200 && str_contains($historicalReport, 'QA-' . $key . '-1'), 'Inactive method hid historical payment.');
    $db->update('UPDATE payment_methods SET is_active=1 WHERE id=?', [$methodId]);
    [$code, $emptyReport] = $http('/rentals/admin/payment-report?method=' . $methodId . '&from=2099-01-01');
    $assert($code === 200 && str_contains($emptyReport, 'No payments match these filters.'), 'Empty Payment Report did not return HTTP 200.');
    $insights = new App\Services\RentalAdminInsights($db);
    $expected = $db->selectValue('SELECT COALESCE(SUM(h.subtotal),0) FROM order_header h WHERE h.payment_status = 1 AND EXISTS (SELECT 1 FROM order_details d WHERE d.order_header_id = h.id)');
    $assert((float) $insights->dashboard()['metrics']['Total rental sales'] === (float) $expected, 'Dashboard sales include deposits or hide [TEST] snapshots.');
    $query = $_GET;
    try {
        $_GET = ['category' => $categoryId];
        $sales = $insights->salesReport(App\Core\Request::capture());
        $assert(count($sales['rows']) === 1 && (float) $sales['totals']['approved_sales'] === 300.0
            && count($sales['rows'][0]['details']) === 2, 'Sales mixed states/deposits or multiplied the two-detail order.');
        $_GET = ['method' => $methodId];
        $paymentReport = $insights->paymentReport(App\Core\Request::capture());
        foreach (['orders' => 3, 'rental_amount' => 300, 'security_deposits' => 9000,
            'total_collected' => 9300, 'pending_amount' => 9300] as $field => $amount) {
            $assert((float) $paymentReport['totals'][$field] === (float) $amount, 'Incorrect payment metric: ' . $field);
        }
        $_GET = ['period' => '7d'];
        $analytics = $insights->analytics(App\Core\Request::capture());
        $rangeStart = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d 00:00:00');
        $rangeEnd = (new DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');
        $revenue = $db->selectValue('SELECT COALESCE(SUM(h.subtotal),0) FROM order_header h WHERE h.payment_status=1
            AND h.created_at >= ? AND h.created_at < ? AND EXISTS (SELECT 1 FROM order_details d WHERE d.order_header_id=h.id)', [$rangeStart, $rangeEnd]);
        $assert((float) $analytics['kpis']['sales'] === (float) $revenue, 'Analytics mixed Pending/Rejected or deposits into revenue.');
    } finally { $_GET = $query; }
    // Invalid CSRF must prevent every type of Admin write, even with valid payloads.
    $before = $db->selectOne('SELECT name, is_active FROM rental_items WHERE id = ?', [$itemId]);
    $writes = ['/rentals/admin/items' => $product, '/rentals/admin/items/toggle' => ['id' => $itemId],
        '/rentals/admin/categories' => ['name' => 'csrf-' . $key, 'slug' => 'csrf-' . $key],
        '/rentals/admin/categories/toggle' => ['id' => $categoryId], '/rentals/admin/payments' => $payment,
        '/rentals/admin/payments/toggle' => ['id' => $methodId],
        '/rentals/admin/orders/' . $orderIds[0] . '/review' => ['decision' => 'approved'],
        '/rentals/admin/items/' . $itemId . '/blackouts' => ['start_date' => $date, 'end_date' => $date],
        '/rentals/admin/items/' . $itemId . '/blackouts/' . $blackoutId . '/toggle' => []];
    foreach ($writes as $path => $payload) {
        unset($payload['_token']);
        [$code] = $http($path, $payload + ['_token' => 'invalid']);
        $assert($code === 419, 'Admin write accepted invalid CSRF: ' . $path);
    }
    $assert($before === $db->selectOne('SELECT name, is_active FROM rental_items WHERE id = ?', [$itemId])
        && (int) $db->selectValue('SELECT payment_status FROM order_header WHERE id = ?', [$orderIds[0]]) === 0, 'CSRF failures changed data.');
    [, $review] = $http('/rentals/admin/orders?edit=' . $orderIds[0]);
    [$code] = $http('/rentals/admin/orders/' . $orderIds[0] . '/review?decision=rejected');
    $assert(in_array($code, [404, 405], true) && (int) $db->selectValue('SELECT payment_status FROM order_header WHERE id=?', [$orderIds[0]]) === 0, 'GET-based review accepted a write or changed order status.');
    $assert(str_contains($review, 'value="rejected"') && str_contains($review, 'value="approved"'), 'Pending order without proof hid the review actions.');
    // A rejected request releases only its own reservation; approved stock remains occupied.
    $http('/rentals/admin/orders/' . $orderIds[0] . '/review', ['_token' => $token($review), 'decision' => 'rejected']);
    $rejected = $db->selectOne('SELECT payment_status, paid_at, payment_reviewed_at, payment_reviewed_by FROM order_header WHERE id = ?', [$orderIds[0]]);
    $assert((int) $rejected['payment_status'] === 2 && $rejected['paid_at'] === null && $rejected['payment_reviewed_at'] !== null
        && (int) $rejected['payment_reviewed_by'] === $adminId, 'Reject did not persist status/reviewer/time correctly.');
    [, $calendarJson] = $http($adminApi);
    $reserved = array_values(array_filter(json_decode($calendarJson, true)['days'], static fn ($d) => $d['date'] === $date))[0];
    $assert($reserved['reserved'] === 2 && $reserved['remaining'] === 0, 'Reject failed to release its own stock or released approved stock.');
    $statusToken = $db->selectValue('SELECT status_token FROM order_header WHERE id = ?', [$orderIds[0]]);
    foreach (['/rentals/orders', '/rentals/order-status/' . $statusToken, '/rentals/admin/payment-report?method=' . $methodId] as $path) {
        [, $updated] = $http($path);
        $assert(str_contains($updated, 'rentals-payment-badge--rejected'), 'Rejected status missing from ' . $path);
    }
    $http('/rentals/admin/items/toggle', ['_token' => $csrf, 'id' => $itemId]);
    [, $public] = $http('/rentals/items');
    $assert(!str_contains($public, $product['name']) && (int) $db->selectValue('SELECT is_active FROM rental_items WHERE id = ?', [$itemId]) === 0, 'Inactive product remained public.');
    [, $itemsPage] = $http('/rentals/items');
    [$code, $addError] = $http('/rentals/cart/add', ['_token' => $token($itemsPage), 'id' => $itemId, 'quantity' => 1,
        'rental_start_date' => $date, 'rental_end_date' => $date], ['X-Requested-With: XMLHttpRequest']);
    $assert($code === 422 && !(json_decode($addError, true)['ok'] ?? true), 'Inactive product was added to Cart.');
    $http('/rentals/admin/payments/toggle', ['_token' => $csrf, 'id' => $methodId]);
    $assert((int) $db->selectValue('SELECT is_active FROM payment_methods WHERE id = ?', [$methodId]) === 0, 'Payment method deactivation failed.');
    $methods = (new App\Services\RentalCheckout($db, new App\Services\RentalCart()))->activePaymentMethods();
    $assert(!in_array($methodId, array_map('intval', array_column($methods, 'id')), true), 'Inactive method remained at Checkout.');
    [, $historic] = $http('/rentals/admin/orders?edit=' . $orderIds[0]);
    $assert(str_contains($historic, $name), 'Deactivation broke historical orders.');
    [, $dashboard] = $http('/rentals/admin');
    [, , $final, $logoutHeaders] = $http('/rentals/logout', ['_token' => $token($dashboard)]);
    $assert($final === $base . '/rentals' && str_contains($logoutHeaders, 'Max-Age=0'), 'Logout did not expire the session cookie.');
    [, , $final] = $http('/rentals/admin');
    $assert($final === $base . '/rentals/account', 'Logged-out Admin remained authenticated.');
    [, , $final] = $http($adminApi);
    $assert(str_starts_with($final, $base . '/rentals/account'), 'Guest accessed Admin availability API.');
    echo "PASS: Admin auth/mounted redirects/logout, category/product CRUD/uploads, malicious files, QR replacement/route, blackouts, searches/status filters, CSRF, reports and production errors.\n";
} finally {
    foreach ($orderIds as $id) { $db->delete('DELETE FROM order_header WHERE id = ?', [$id]); }
    foreach ($extraItemIds as $id) { $db->delete('DELETE FROM rental_items WHERE id = ?', [$id]); }
    if ($itemId) {
        $path = $db->selectValue('SELECT image_path FROM rental_items WHERE id = ?', [$itemId]);
        App\Services\RentalManagedImage::remove($path, 'product');
        $db->delete('DELETE FROM rental_items WHERE id = ?', [$itemId]);
    }
    if ($categoryId) { $db->delete('DELETE FROM rental_categories WHERE id = ?', [$categoryId]); }
    if ($methodId) {
        $path = $db->selectValue('SELECT qr_image_path FROM payment_methods WHERE id = ?', [$methodId]);
        App\Services\RentalManagedImage::remove($path, 'qr');
        $db->delete('DELETE FROM payment_methods WHERE id = ?', [$methodId]);
    }
    if ($adminId) { $db->delete('DELETE FROM users WHERE id = ?', [$adminId]); }
    foreach ([$jar, $picture, $malicious, $large] as $file) { @unlink($file); }
}
