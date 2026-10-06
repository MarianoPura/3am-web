<?php
declare(strict_types=1);

// Local HTTP security regression: only this test's unique fixtures are removed.
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^http://(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/[A-Za-z0-9_/-]+)?$#D', $base)) {
    throw new RuntimeException('Pass a local development base URL.');
}
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$mount = (string) parse_url($base, PHP_URL_PATH);
$key = bin2hex(random_bytes(6));
$users = []; $jars = []; $orderId = 0;
$statusToken = bin2hex(random_bytes(32));
$privateName = 'PRIVATE QR CUSTOMER ' . $key;
$proofPath = 'micro/payment/' . bin2hex(random_bytes(16)) . '.jpg';
$assert = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$http = static function (string $jar, string $path, ?array $post = null, bool $follow = true) use ($base): array {
    $h = curl_init($base . $path); $headers = '';
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 20, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_HEADERFUNCTION => static function ($h, string $line) use (&$headers): int { $headers .= $line; return strlen($line); }]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($h);
    if ($body === false) { throw new RuntimeException(curl_error($h)); }
    $result = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $body, curl_getinfo($h, CURLINFO_EFFECTIVE_URL), $headers];
    curl_close($h); return $result;
};
$csrf = static function (string $html): string {
    if (!preg_match('/name="_token" value="([^"]+)"/', $html, $m)) { throw new RuntimeException('Missing CSRF token.'); }
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
};
try {
    foreach (['owner' => 'customer', 'other' => 'customer', 'admin' => 'admin', 'superadmin' => 'superadmin'] as $kind => $role) {
        $email = 'qr-' . $kind . '-' . $key . '@example.test';
        $password = bin2hex(random_bytes(16));
        $id = $db->insert('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)',
            ['[TEST] QR ' . $kind . ' ' . $key, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $users[$kind] = compact('id', 'email', 'password');
    }
    $orderId = $db->insert('INSERT INTO order_header(order_number,user_id,customer_name,customer_email,customer_phone,payment_reference,payment_proof_path,status_token,notes) VALUES(?,?,?,?,?,?,?,?,?)',
        ['RNT-QR-' . $key, $users['owner']['id'], $privateName, $users['owner']['email'], 'PRIVATE-PHONE', 'PRIVATE-REFERENCE', $proofPath, $statusToken, 'PRIVATE-NOTE']);
    foreach (['order-status', 'confirmation'] as $page) {
        foreach ($users as $kind => $user) {
            $jar = tempnam(sys_get_temp_dir(), 'rentals-qr-cookie-');
            if ($jar === false) { throw new RuntimeException('Temporary cookie storage unavailable.'); }
            $jars[] = $jar;
            $target = '/rentals/' . $page . '/' . $statusToken;
            [$code, $body, , $headers] = $http($jar, $target, null, false);
            $assert($code === 302 && str_contains($headers, 'Location: ' . $mount . '/rentals/account')
                && !str_contains($body, 'RNT-QR-') && str_contains($headers, 'no-store'), 'Guest QR did not redirect safely to login.');
            [, $login] = $http($jar, '/rentals/account');
            [$code, $failure] = $http($jar, '/rentals/account', ['_token' => $csrf($login), 'action' => 'login', 'email' => $user['email'], 'password' => 'incorrect']);
            $assert($code === 422, 'Invalid login unexpectedly succeeded.');
            [$code, $body, $final, $headers] = $http($jar, '/rentals/account',
                ['_token' => $csrf($failure), 'action' => 'login', 'email' => $user['email'], 'password' => $user['password']]);
            $expected = $kind === 'other' ? 403 : 200;
            $isAdmin = in_array($kind, ['admin','superadmin'], true);
            $expectedUrl = $isAdmin ? $base . '/rentals/admin/orders/' . $orderId : $base . $target;
            $assert($code === $expected && $final === $expectedUrl && str_contains($headers, 'no-store'), 'QR login return/access failed for ' . $page . '/' . $kind);
            if ($isAdmin) {
                $assert(str_contains($body,'Payment status') && str_contains($body,'rentals-payment-badge--pending')
                    && !str_contains($body,'data-qr-url=') && !str_contains($body,$statusToken) && !str_contains($body,$proofPath), 'Admin was not routed to its dedicated authorized order view.');
                [$redirectCode,$redirectBody,,$redirectHeaders]=$http($jar,$target,null,false);
                $assert($redirectCode===302 && str_contains($redirectHeaders,'Location: '.$mount.'/rentals/admin/orders/'.$orderId)
                    && !str_contains($redirectBody,$privateName), 'Admin token route rendered customer status or leaked details.');
            }
            foreach ($isAdmin ? [] : [$privateName, $proofPath, $users['owner']['email'], 'PRIVATE-PHONE', 'PRIVATE-REFERENCE', 'PRIVATE-NOTE'] as $secret) {
                $assert(!str_contains($body, $secret), 'QR page exposed private order fields.');
            }
            if ($kind === 'owner') {
                $assert(str_contains($body, '<meta name="referrer" content="no-referrer">')
                    && str_contains($body, '<meta name="robots" content="noindex, nofollow">'), 'Token page lacks scoped referrer/index protection.');
                $assert(str_contains($body, 'RNT-QR-' . $key) && str_contains($body, 'rentals-payment-badge--pending'), 'Authorized status missing.');
                $assert(preg_match('/data-qr-url="([^"]+)"/', $body, $qr) === 1
                    && html_entity_decode($qr[1], ENT_QUOTES, 'UTF-8') === $base . '/rentals/order-status/' . $statusToken,
                    'QR payload contains anything other than the protected token URL.');
            } elseif (!$isAdmin) {
                $assert(!str_contains($body, 'RNT-QR-' . $key) && !str_contains($body, $statusToken), '403 disclosed order or QR data.');
            }
            [$code] = $http($jar, '/rentals/' . $page . '/' . str_repeat('f', 64));
            $assert($code === 404, 'Unknown token accepted.');
            [$code] = $http($jar, '/rentals/' . $page . '/' . $orderId);
            $assert($code === 404, 'Numeric ID accepted as a customer status token.');
            if ($kind === 'other') {
                [$code] = $http($jar,'/rentals/orders/'.$orderId.'/proof');
                $assert($code===404,'Another customer accessed a numeric proof ID.');
                [$code] = $http($jar,'/rentals/admin/orders/'.$orderId);
                $assert($code===403,'Customer bypassed dedicated Admin order access.');
                [$code] = $http($jar,'/rentals/admin/proof/'.$orderId);
                $assert($code===403,'Customer bypassed Admin proof authorization.');
            }
            [, $account] = $http($jar, '/rentals/account');
            $http($jar, '/rentals/logout', ['_token' => $csrf($account)]);
            [$code] = $http($jar, $target, null, false);
            $assert($code === 302, 'Logout left the protected QR page accessible.');
        }
    }
    echo "PASS: status/confirmation require login; owner allowed; other customer 403; Admin/Superadmin redirected to dedicated Admin order/status view; numeric token/proof tampering blocked; private paths omitted; opaque QR; unknown token 404; logout and no-store preserved.\n";
} finally {
    if ($orderId > 0) { $db->delete('DELETE FROM order_header WHERE id=?', [$orderId]); }
    foreach ($users as $user) {
        $db->delete('DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id=?)', [$user['id']]);
        $db->delete('DELETE FROM carts WHERE user_id=?', [$user['id']]);
        $db->delete('DELETE FROM users WHERE id=?', [$user['id']]);
    }
    foreach ($jars as $jar) { @unlink($jar); }
}
