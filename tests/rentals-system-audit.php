<?php
declare(strict_types=1);
// Local transactional fixtures only; no mail, uploads, DDL or persistent rows.
$_ENV['MAIL_ENABLED'] = 'false';
session_start();
$c = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)
    || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Local loopback database only.');
}
$db = $c->get(App\Core\Database::class);
$failures = [];
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    if (!$ok) { $failures[] = $label; }
};
$request = static function (array $data, string $method = 'POST'): App\Core\Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/rentals/cart';
    $_POST = $method === 'POST' ? $data : [];
    $_GET = $method === 'GET' ? $data : [];
    $_FILES = [];
    return App\Core\Request::capture();
};
$db->beginTransaction();
try {
    $key = bin2hex(random_bytes(6));
    $category = $db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)', ['[TEST] Audit', 'audit-' . $key]);
    $ids = [];
    foreach ([1, 10] as $stock) {
        $ids[] = $db->insert("INSERT INTO rental_items(category_id,name,slug,available_quantity,rental_rate,security_deposit,rental_unit) VALUES (?,?,?,?,100,25,'day')",
            [$category, '[TEST] Audit ' . $stock, 'audit-' . $key . '-' . $stock, $stock]);
    }
    $user = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)', ['[TEST] Audit customer', 'audit-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), 'customer']);
    $admin = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)', ['[TEST] Audit admin', 'audit-admin-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), 'admin']);
    $date = date('Y-m-d', strtotime('+5 days'));
    $cartController = new App\Controllers\Rentals\RentalCartController($c);
    // Both guest and authenticated carts must validate the actual line's product.
    foreach ([null, $user] as $owner) {
        $_SESSION = $owner ? ['user_id' => $owner] : [];
        $cart = new App\Services\RentalCart();
        $line = $cart->add('audit-' . $key . '-1', 1, $date, $date);
        $summary = (new App\Services\RentalCheckout($db, $cart))->summary();
        $check($summary['subtotal'] === 100.0 && $summary['security_deposit'] === 25.0 && $summary['total'] === 125.0, 'Same-day rental = one day, one deposit per unit');
        $cartController->update($request(['line' => $line['line_id'], 'id' => 'audit-' . $key . '-10', 'quantity' => 5, 'rental_start_date' => $date, 'rental_end_date' => $date]));
        $contents = $cart->contents();
        $check((int)$contents[$line['line_id']]['quantity'] === 1, 'Forged product ID cannot validate another cart line (' . ($owner ? 'customer' : 'guest') . ')');
        $cart->clear();
    }
    $_SESSION = ['user_id' => $admin];
    $adminController = new App\Controllers\Rentals\RentalAdminController($c);
    $badMonth = date('Y') . "-\0" . date('m');
    foreach (['public', 'admin'] as $audience) {
        try {
            $response = $audience === 'public'
                ? $cartController->availability($request(['month' => $badMonth, 'id' => (string)$ids[0]], 'GET'))
                : $adminController->availability($request(['month' => $badMonth], 'GET'), (string)$ids[0]);
            $check($response->status() === 422, 'Malformed month returns HTTP 422 (' . $audience . ')');
        } catch (ValueError $e) { $check(false, 'Malformed month returns HTTP 422 (' . $audience . ')'); }
    }
    try {
        $response = $adminController->saveBlackout($request(['start_date' => substr($date, 0, 8) . "\0" . '01', 'end_date' => $date]), (string)$ids[0]);
        $check($response->status() === 302 && (int)$db->selectValue('SELECT COUNT(*) FROM rental_item_blackouts WHERE rental_item_id=?', [$ids[0]]) === 0, 'Malformed blackout date redirects with no write');
    } catch (ValueError $e) { $check(false, 'Malformed blackout date redirects with no write'); }
    try {
        $report = (new App\Services\RentalAdminInsights($db))->salesReport($request(['from' => substr($date, 0, 8) . "\0" . '01'], 'GET'));
        $check(is_array($report), 'Malformed report date follows the existing ignored-filter policy');
    } catch (ValueError $e) { $check(false, 'Malformed report date follows the existing ignored-filter policy'); }
} finally { $db->rollBack(); }
if ($failures !== []) { throw new RuntimeException(count($failures) . ' audit regression checks failed.'); }
echo "PASS: all audit fixture rows rolled back.\n";
