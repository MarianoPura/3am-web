<?php
declare(strict_types=1);

// Real simultaneous local HTTP check. Persistent fixture rows are narrowly cleaned up.
$base = rtrim((string) ($argv[1] ?? ''), '/');
if ($base !== 'http://127.0.0.1:80/3am-web') {
    throw new RuntimeException('STOP: Supply exactly http://127.0.0.1:80/3am-web.');
}
$container = require dirname(__DIR__) . '/bootstrap.php';
$connection = config('database.connections.mysql');
if (config('app.env') !== 'local' || $connection['host'] !== '127.0.0.1'
    || (int) $connection['port'] !== 3306 || $connection['database'] !== 'd3am_rentals_new'
    || config('mail.enabled') !== false) {
    throw new RuntimeException('STOP: Exact HOME PC database and disabled email are required.');
}
$db = $container->get(App\Core\Database::class);
$identity = $db->selectOne('SELECT DATABASE() AS db, @@port AS port');
if ($identity['db'] !== 'd3am_rentals_new' || (int) $identity['port'] !== 3306) {
    throw new RuntimeException('STOP: Connected local database identity mismatch.');
}
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$token = static function (string $html): string {
    if (preg_match('/name="_token" value="([^"<>]+)"/', $html, $match) !== 1) {
        throw new RuntimeException('Local response lacks a form token.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$handle = static function (string $jar, string $path, ?array $post = null, bool $ajax = false) use ($base): CurlHandle {
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROXY => '',
        CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, array_filter($post, static fn ($value): bool => $value instanceof CURLFile)
            ? $post : http_build_query($post));
    }
    if ($ajax) { curl_setopt($curl, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']); }
    return $curl;
};
$http = static function (string $jar, string $path, ?array $post = null, bool $ajax = false) use ($handle): array {
    $curl = $handle($jar, $path, $post, $ajax);
    try {
        $body = curl_exec($curl);
        if ($body === false) { throw new RuntimeException('Local HTTP request failed.'); }
        return [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $body];
    } finally { curl_close($curl); }
};
$key = bin2hex(random_bytes(8));
$name = '[TEST] Concurrency ' . $key;
$slug = 'concurrency-' . $key;
$date = (new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
$proofDirectory = App\Services\RentalStorage::root() . '/payment';
$assert(is_dir($proofDirectory), 'Existing local proof storage is required; initialize it through the normal local setup first.');
$files = static function () use ($proofDirectory): array {
    clearstatcache(true);
    return array_values(array_filter(glob($proofDirectory . '/*') ?: [],
        static fn (string $file): bool => preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$/D', basename($file)) === 1));
};
$before = $files();
// The unique plaintext marker identifies this test's encrypted artifacts for
// cleanup; another simultaneous user's upload can never be deleted by a diff.
$plain = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', true)
    . "\n3AM-CONCURRENCY-QA:" . $key;
$ownedReferences = static function () use ($files, $before, $plain): array {
    $owned = [];
    foreach (array_diff($files(), $before) as $file) {
        $reference = 'micro/payment/' . basename($file);
        try {
            if (App\Services\RentalPaymentProof::response($reference)->body() === $plain) { $owned[] = $reference; }
        } catch (Throwable) {
            // An unrelated/unreadable file is never attributed to this fixture.
        }
    }
    sort($owned);
    return $owned;
};
$categoryId = $itemId = $methodId = 0;
$customers = $tempFiles = $multiHandles = [];
$multi = null;
$proof = '';
try {
    $proof = tempnam(BASE_PATH . '/storage/tmp', 'concurrency-proof-');
    if ($proof === false) { throw new RuntimeException('Local fixture file unavailable.'); }
    $tempFiles[] = $proof;
    file_put_contents($proof, $plain);
    $categoryId = $db->insert('INSERT INTO rental_categories(name,slug,is_active,is_service) VALUES(?,?,1,0)', [$name, $slug]);
    $itemId = $db->insert("INSERT INTO rental_items(category_id,name,slug,is_active,availability_status,rental_unit,rental_rate,security_deposit,available_quantity) VALUES(?,?,?,1,'available','day',100,25,1)",
        [$categoryId, $name . ' Camera', $slug . '-camera']);
    $methodId = $db->insert("INSERT INTO payment_methods(name,type,provider,account_name,account_number,is_active) VALUES(?,'manual','Local QA','Fictional QA account','0000000000',1)", [$name]);
    for ($i = 0; $i < 2; ++$i) {
        $email = $slug . '-' . $i . '@example.test';
        $password = bin2hex(random_bytes(16));
        $userId = $db->insert("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'customer')",
            [$name . ' Customer ' . $i, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $customers[$i] = ['id' => $userId, 'email' => $email, 'name' => $name . ' Customer ' . $i, 'jar' => '', 'token' => ''];
        $jar = tempnam(BASE_PATH . '/storage/tmp', 'concurrency-cookie-');
        if ($jar === false) { throw new RuntimeException('Local cookie jar unavailable.'); }
        $tempFiles[] = $jar;
        $customers[$i]['jar'] = $jar;
        [$status, $login] = $http($jar, '/rentals/account');
        $assert($status === 200, 'Local login page failed.');
        [$status] = $http($jar, '/rentals/account', ['_token' => $token($login), 'action' => 'login', 'email' => $email, 'password' => $password]);
        $assert($status === 302, 'Fictional Customer login failed.');
        [$status, $account] = $http($jar, '/rentals/account');
        $assert($status === 200 && str_contains($account, $email), 'Customer session did not authenticate the correct fixture.');
        [$status, $result] = $http($jar, '/rentals/cart/add', ['_token' => $token($account), 'id' => $slug . '-camera', 'quantity' => '1',
            'rental_start_date' => $date, 'rental_end_date' => $date], true);
        $assert($status === 200 && (json_decode($result, true)['ok'] ?? false) === true, 'Independent Customer Cart setup failed.');
        [$status, $checkout] = $http($jar, '/rentals/checkout');
        $assert($status === 200 && str_contains($checkout, 'Proof of payment'), 'Pre-race checkout is not bookable.');
        $customers[$i]['token'] = $token($checkout);
    }
    foreach ($customers as $customer) {
        [$status, $account] = $http($customer['jar'], '/rentals/account');
        $assert($status === 200 && str_contains($account, $customer['email']),
            'Customer sessions must remain independent after both logins.');
    }
    $multi = curl_multi_init();
    foreach ($customers as $customer) {
        $curl = $handle($customer['jar'], '/rentals/checkout', ['_token' => $customer['token'],
            'customer_name' => $customer['name'], 'customer_email' => $customer['email'], 'customer_phone' => '',
            'payment_method_id' => (string) $methodId, 'payment_reference' => 'LOCAL-QA-' . $key,
            'notes' => 'Concurrent fixture', 'proof' => new CURLFile($proof, 'image/png', 'proof.png')]);
        $multiHandles[] = $curl;
        curl_multi_add_handle($multi, $curl);
    }
    $started = microtime(true);
    do {
        $status = curl_multi_exec($multi, $running);
        if ($status !== CURLM_OK) { throw new RuntimeException('Concurrent local HTTP transfer failed.'); }
        if ($running && curl_multi_select($multi, 1.0) === -1) { usleep(1000); }
    } while ($running);
    $elapsed = microtime(true) - $started;
    $responses = [];
    foreach ($multiHandles as $curl) {
        $assert(curl_errno($curl) === 0, 'Concurrent local checkout request timed out or failed.');
        $responses[] = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    }
    sort($responses);
    $assert($responses === [302, 422], 'Expected one receipt redirect and one rejected checkout.');
    $userIds = array_column($customers, 'id');
    $orders = $db->select('SELECT id,user_id,payment_status,payment_proof_path,subtotal,security_deposit,total_amount FROM order_header WHERE user_id IN (?,?)', $userIds);
    $assert(count($orders) === 1 && (int) $orders[0]['payment_status'] === App\Services\RentalPaymentStatus::PENDING,
        'Concurrent checkout overbooked or did not create exactly one Pending order.');
    $order = $orders[0];
    $details = $db->select('SELECT rental_item_id,quantity,rental_start_date,rental_end_date,line_total FROM order_details WHERE order_header_id=?', [(int) $order['id']]);
    $assert(count($details) === 1 && (int) $details[0]['rental_item_id'] === $itemId && (int) $details[0]['quantity'] === 1
        && $details[0]['rental_start_date'] === $date && $details[0]['rental_end_date'] === $date,
        'Concurrent reservation changed the selected item, dates or quantity.');
    $assert((float) $order['subtotal'] === 100.0 && (float) $order['security_deposit'] === 25.0 && (float) $order['total_amount'] === 125.0,
        'Existing same-day inclusive rental/deposit totals changed.');
    foreach ($customers as $customer) {
        $count = (int) $db->selectValue('SELECT COUNT(*) FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE c.user_id=?', [$customer['id']]);
        $assert($count === ((int) $order['user_id'] === $customer['id'] ? 0 : 1), 'Winning Cart was not cleared or losing Cart was lost.');
    }
    $assert($ownedReferences() === [(string) $order['payment_proof_path']], 'Failed concurrent checkout left an orphan proof or winning proof is unreadable.');
    $catalog = new App\Models\RentalCatalog($db);
    $assert(!$catalog->isAvailable($catalog->find($slug . '-camera'), 1, $date, $date), 'Pending reservation did not consume the single stock unit.');
} finally {
    if ($multi !== null) {
        foreach ($multiHandles as $curl) { curl_multi_remove_handle($multi, $curl); curl_close($curl); }
        curl_multi_close($multi);
    }
    foreach ($customers as $customer) {
        if ($customer['jar'] !== '' && $customer['token'] !== '') {
            try { $http($customer['jar'], '/rentals/logout', ['_token' => $customer['token']]); }
            catch (Throwable) { /* Row/file cleanup below must still run. */ }
        }
    }
    $proofs = $ownedReferences();
    foreach ($customers as $customer) {
        $orderIds = $db->select('SELECT id,payment_proof_path FROM order_header WHERE user_id=?', [$customer['id']]);
        foreach ($orderIds as $row) {
            if (is_string($row['payment_proof_path']) && $row['payment_proof_path'] !== '') { $proofs[] = $row['payment_proof_path']; }
            $db->delete('DELETE FROM rental_notification_deliveries WHERE order_id=?', [(int) $row['id']]);
            $db->delete('DELETE FROM order_details WHERE order_header_id=?', [(int) $row['id']]);
            $db->delete('DELETE FROM order_header WHERE id=? AND user_id=?', [(int) $row['id'], $customer['id']]);
        }
        $carts = $db->select('SELECT id FROM carts WHERE user_id=?', [$customer['id']]);
        foreach ($carts as $cart) { $db->delete('DELETE FROM cart_items WHERE cart_id=?', [(int) $cart['id']]); }
        $db->delete('DELETE FROM carts WHERE user_id=?', [$customer['id']]);
        $db->delete('DELETE FROM users WHERE id=? AND email=?', [$customer['id'], $customer['email']]);
    }
    if ($itemId > 0) { $db->delete('DELETE FROM rental_items WHERE id=? AND category_id=?', [$itemId, $categoryId]); }
    if ($categoryId > 0) { $db->delete('DELETE FROM rental_categories WHERE id=? AND slug=?', [$categoryId, $slug]); }
    if ($methodId > 0) { $db->delete('DELETE FROM payment_methods WHERE id=? AND name=?', [$methodId, $name]); }
    foreach (array_unique($proofs) as $reference) { App\Services\RentalPaymentProof::remove($reference); }
    foreach ($tempFiles as $file) { if (is_file($file)) { unlink($file); } }
    $assert($db->selectValue('SELECT id FROM rental_categories WHERE slug=?', [$slug]) === null && $ownedReferences() === [],
        'Unique fixture cleanup did not complete.');
    echo "Cleanup: unique local rows and marker-verified proof files removed; existing data preserved.\n";
}
echo 'PASS: ' . $checks . ' assertions; two curl_multi Customer submissions, exactly one Pending order, losing Cart preserved, no owned proof orphan; '
    . number_format($elapsed, 3) . " seconds. Requests are concurrent; scheduling does not prove both reached the item lock at the same instant.\n";
