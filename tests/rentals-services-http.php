<?php
declare(strict_types=1);

$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^http://127\.0\.0\.1:[0-9]+(?:/[A-Za-z0-9_/-]+)?$#', $base)) { throw new RuntimeException('Loopback QA server required.'); }
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) { throw new RuntimeException('Local QA database only.'); }
$db = $container->get(App\Core\Database::class);
$store = new App\Services\RentalServiceRequests($db);
$assert = static function (bool $pass, string $message): void { if (!$pass) { throw new RuntimeException($message); } };
$key = bin2hex(random_bytes(6)); $password = bin2hex(random_bytes(12));
$jar = tempnam(sys_get_temp_dir(), 'services-http-'); $users = []; $category = $service = 0;
$http = static function (string $path, ?array $post = null) use ($base, $jar): array {
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 15, CURLOPT_MAXREDIRS => 5]);
    if ($post !== null) { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException('QA HTTP request failed.'); }
    $result = [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $body, (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL)];
    curl_close($curl); return $result;
};
$field = static function (string $html, string $name): string {
    if (!preg_match('/name="' . preg_quote($name, '/') . '" value="([^"<>]+)"/', $html, $match)) { throw new RuntimeException('Missing form field: ' . $name); }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$login = static function (int $index) use ($http, $field, $key, $password): void {
    [, $page] = $http('/rentals/account');
    $http('/rentals/account', ['_token' => $field($page, '_token'), 'action' => 'login',
        'email' => 'service-http-' . $key . '-' . $index . '@example.test', 'password' => $password]);
};
$logout = static function () use ($http, $field): void {
    [, $page] = $http('/rentals/account'); $http('/rentals/logout', ['_token' => $field($page, '_token')]);
};
try {
    foreach (['customer', 'customer', 'admin', 'superadmin'] as $index => $role) {
        $users[] = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
            ['[TEST] Services HTTP ' . $key, 'service-http-' . $key . '-' . $index . '@example.test', password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    $category = $db->insert('INSERT INTO rental_categories(name,slug,is_service) VALUES (?,?,1)', ['[TEST] Services HTTP ' . $key, 'service-http-' . $key]);
    $service = $db->insert('INSERT INTO rental_items(category_id,name,slug,availability_status,available_quantity) VALUES (?,?,?,?,0)',
        [$category, '[TEST] Production crew ' . $key, 'service-http-' . $key, 'inquire']);
    [$code, $page] = $http('/rentals/services');
    $mount = (string) parse_url($base, PHP_URL_PATH);
    $assert($code === 200 && str_contains($page, 'href="' . $mount . '/rentals/services/' . $service . '/request"'), 'Service CTA still loses selected service.');
    [$code, , $final] = $http('/rentals/services/' . $service . '/request');
    $assert($code === 200 && $final === $base . '/rentals/account', 'Guest service request does not require login.');
    $login(0);
    [, $page, $final] = $http('/rentals/services/' . $service . '/request');
    $assert($final === $base . '/rentals/services/' . $service . '/request' && str_contains($page, 'Production crew ' . $key), 'Login did not retain service context.');
    $keyFromForm = $field($page, 'request_key');
    $fields = ['phone' => 'QA number', 'start_date' => date('Y-m-d', strtotime('+10 days')),
        'end_date' => date('Y-m-d', strtotime('+11 days')), 'location' => 'QA event hall', 'details' => 'Live camera production and crew requirements'];
    $post = $fields + ['_token' => $field($page, '_token'), 'request_key' => $keyFromForm];
    [$code] = $http('/rentals/services/' . $service . '/request', array_replace($post, ['_token' => 'invalid']));
    $assert($code === 419, 'Service POST bypassed global CSRF.');
    [$code, $invalid] = $http('/rentals/services/' . $service . '/request', array_replace($post, ['location' => '']));
    $assert($code === 422 && str_contains($invalid, 'Event location is required.'), 'HTTP validation missing.');
    // A previously captured/attempted fixture verifies real HTTP replay/redirects without touching the user's inquiry backup.
    // Initial save + inquiry capture/SMTP failure are exercised with isolated storage in rentals-services.php.
    $record = $store->submit($users[0], $service, hash('sha256', $users[0] . '|' . $service . '|' . $keyFromForm), $fields);
    $db->update('UPDATE rental_service_requests SET notification_attempted_at=CURRENT_TIMESTAMP WHERE id=?', [$record['id']]);
    [$code, $history, $final] = $http('/rentals/services/' . $service . '/request', $post);
    $assert($code === 200 && $final === $base . '/rentals/service-requests' && str_contains($history, $record['reference']) && str_contains($history, 'Pending'), 'HTTP replay/confirmation/history failed.');
    $http('/rentals/services/' . $service . '/request', $post);
    $assert((int) $db->selectValue('SELECT COUNT(*) FROM rental_service_requests WHERE user_id=?', [$users[0]]) === 1, 'HTTP repeated POST created duplicate request.');
    [$code] = $http('/rentals/admin/service-requests/' . $record['id']);
    $assert($code === 403, 'Customer can access Admin service review.');
    $logout(); $login(1);
    [, $other] = $http('/rentals/service-requests');
    $assert(!str_contains($other, $record['reference']), 'Customer HTTP history exposes another owner.');
    $logout(); $login(2);
    [$code, $pending] = $http('/rentals/admin/service-requests');
    $assert($code === 200 && str_contains($pending, $record['reference']), 'Admin pending queue missing.');
    [, $review] = $http('/rentals/admin/service-requests/' . $record['id']);
    [$code] = $http('/rentals/admin/service-requests/' . $record['id'] . '/review', ['_token' => 'invalid', 'decision' => 'approved']);
    $assert($code === 419, 'Admin review bypassed CSRF.');
    $reviewPost = ['_token' => $field($review, '_token'), 'decision' => 'approved', 'customer_message' => 'We can coordinate the requested crew.'];
    [$code, $reviewed] = $http('/rentals/admin/service-requests/' . $record['id'] . '/review', $reviewPost);
    $assert($code === 200 && str_contains($reviewed, 'Approved') && !str_contains($reviewed, '>Approve request</button>') && !str_contains($reviewed, '>Reject request</button>'), 'Admin review does not persist/remove review controls.');
    $http('/rentals/admin/service-requests/' . $record['id'] . '/review', array_replace($reviewPost, ['decision' => 'rejected']));
    $assert($store->find((int) $record['id'])['status'] === 'approved', 'Conflicting HTTP repeated review changed final status.');
    $logout(); $login(0);
    [, $history] = $http('/rentals/service-requests?status=approved');
    $assert(str_contains($history, $record['reference']) && str_contains($history, 'We can coordinate the requested crew.'), 'Customer status/team-message update missing.');
    $logout(); $login(3);
    [$code] = $http('/rentals/admin/service-requests/' . $record['id']);
    $assert($code === 200, 'Superadmin service access failed.');
    foreach (['/', '/information', '/rentals/items', '/rentals/services'] as $path) {
        [$code] = $http($path); $assert($code === 200, 'Existing page failed: ' . $path);
    }
    echo "PASS: actual HTTP Services CTA/login/form; POST/review CSRF; field errors; replay/redirect; owner-only history; Admin pending queue/approval; repeated review; customer-visible updates; Superadmin; existing Home/Information/Equipment/Services.\n";
} finally {
    foreach ($users as $id) { $db->delete('DELETE FROM rental_notification_deliveries WHERE service_request_id IN (SELECT id FROM rental_service_requests WHERE user_id=?)',[$id]); $db->delete('DELETE FROM rental_service_requests WHERE user_id=?', [$id]); }
    if ($service) { $db->delete('DELETE FROM rental_items WHERE id=?', [$service]); }
    if ($category) { $db->delete('DELETE FROM rental_categories WHERE id=?', [$category]); }
    foreach ($users as $id) { $db->delete('DELETE FROM users WHERE id=?', [$id]); }
    if (is_file($jar)) { unlink($jar); }
}
