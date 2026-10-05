<?php
declare(strict_types=1);
// Removed-settings HTTP regression QA. Loopback-only, delivery disabled in QA router.
$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^http://127\.0\.0\.1:[0-9]+(?:/[A-Za-z0-9_/-]+)?$#', $base)) { throw new RuntimeException('Pass the loopback QA URL.'); }
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class); $key = bin2hex(random_bytes(6));
$jar = tempnam(sys_get_temp_dir(), 'fixed-mail-http-'); $ids = [];
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$http = static function (string $path, ?array $post = null) use ($base, $jar): array {
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 15]);
    if ($post !== null) { curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException('Local QA HTTP request failed.'); }
    $result = [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $body];
    curl_close($curl); return $result;
};
$token = static function (string $html): string {
    if (!preg_match('/name="_token" value="([^"<>]+)"/', $html, $match)) { throw new RuntimeException('Missing CSRF token.'); }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$removed = ['/rentals/admin/email', '/rentals/admin/email/templates/rental_submitted/customer',
    '/rentals/admin/email/templates/rental_approved/admin', '/rentals/admin/email/recipients',
    '/rentals/admin/email/history', '/rentals/admin/email/recipients/1/remove', '/rentals/admin/email/deliveries/1/retry'];
$businessCount = $db->selectValue('SELECT COUNT(*) FROM order_header');
try {
    foreach (['guest', 'customer', 'admin', 'superadmin'] as $role) {
        if ($role !== 'guest') {
            $ids[] = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
                ['[TEST] Fixed mail HTTP ' . $key, $role . '-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), $role]);
            [, $login] = $http('/rentals/account');
            [$status] = $http('/rentals/account', ['_token' => $token($login), 'action' => 'login',
                'email' => $role . '-' . $key . '@example.test', 'password' => $key]);
            $assert($status === 200, 'QA login failed.');
        }
        [, $account] = $http('/rentals/account'); $csrf = $token($account);
        foreach ($removed as $path) {
            [$status] = $http($path); $assert($status === 404, 'Removed mailer page remains: ' . $role . ' ' . $path);
            [$status] = $http($path, ['_token' => $csrf, 'action' => 'save', 'email' => 'injected@example.test']);
            $assert($status === 404, 'Removed mailer write remains: ' . $role . ' ' . $path);
        }
        if (in_array($role, ['admin', 'superadmin'], true)) {
            [$status, $dashboard] = $http('/rentals/admin');
            $assert($status === 200 && !str_contains($dashboard, 'Email Notifications')
                && !str_contains($dashboard, '/admin/email'), 'Admin header still exposes mail configuration.');
            foreach (array_merge([config('rentals-mail.owner')], config('rentals-mail.cc')) as $address) {
                $assert(!str_contains($dashboard, $address), 'Admin header exposes a fixed mail recipient.');
            }
        }
        $http('/rentals/logout', ['_token' => $csrf]);
    }
    foreach (['/', '/information', '/rentals', '/rentals/items'] as $path) {
        [$status] = $http($path); $assert($status === 200, 'Existing page failed: ' . $path);
    }
    $assert($db->selectValue('SELECT COUNT(*) FROM order_header') === $businessCount, 'Removed settings request changed orders.');
    echo "PASS: removed email pages and writes return 404 for guest/customer/admin/superadmin, Admin navigation clean, existing main/Rentals pages work, no order changes.\n";
} finally {
    foreach ($ids as $id) { $db->delete('DELETE FROM carts WHERE user_id=?', [$id]); $db->delete('DELETE FROM users WHERE id=?', [$id]); }
    @unlink($jar);
}
