<?php
declare(strict_types=1);

session_start();
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if (!$ok) { $failures[] = $message; }
};
$savedSession = $_SESSION;
$db->beginTransaction();
try {
    $_SESSION = [];
    $key = bin2hex(random_bytes(6));
    $category = $db->insert('INSERT INTO rental_categories (name,slug) VALUES (?,?)', ['[TEST] Regression', 'regression-' . $key]);
    $user = $db->insert('INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)',
        ['[TEST] Regression', 'regression-' . $key . '@example.test', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'customer']);
    $_SESSION['user_id'] = $user;
    $slug = 'regression-' . $key;
    $id = $db->insert('INSERT INTO rental_items (category_id,name,slug,availability_status,rental_unit,rental_rate,available_quantity)
        VALUES (?,?,?,\'available\',\'day\',100,2)', [$category, '[TEST] Regression camera', $slug]);
    $catalog = new App\Models\RentalCatalog($db);
    $cart = new App\Services\RentalCart();
    $first = (new DateTimeImmutable('today'))->modify('+45 days')->format('Y-m-d');
    $last = (new DateTimeImmutable($first))->modify('+2 days')->format('Y-m-d');
    $db->update('UPDATE rental_items SET availability_status=\'inquire\' WHERE id=?', [$id]);
    $check(!$catalog->isAvailable($catalog->find($slug), 1, $first, $last), 'Inquiry-only equipment cannot be rented directly');
    $db->update('UPDATE rental_items SET availability_status=\'available\' WHERE id=?', [$id]);
    $cart->add($slug, 1, $first, $last);
    $cart->add($slug, 1, $first, $first);
    $cart->add($slug, 1, $last, $last);
    $_GET = $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/rentals/checkout';
    $response = (new App\Controllers\Rentals\RentalCheckoutController($container))->show(App\Core\Request::capture());
    $check($response->status() === 200, 'Checkout accepts three date ranges using at most two units per day');
    $cart->add($slug, 1, $first, $first);
    $response = (new App\Controllers\Rentals\RentalCheckoutController($container))->show(App\Core\Request::capture());
    $check($response->status() === 302, 'Checkout still rejects actual per-day overbooking');
    $account = new App\Services\RentalAccount($db, $cart);
    try {
        $account->register('QA', 'nul-' . $key . '@example.test', "longpassword\0value");
        $check(false, 'Invalid password receives validation rather than a PHP fatal');
    } catch (RuntimeException $e) {
        $check(true, 'Invalid password receives validation rather than a PHP fatal');
    } catch (Throwable $e) {
        $check(false, 'Invalid password receives validation rather than a PHP fatal (' . get_class($e) . ')');
    }
    try {
        $account->register('QA', 'long-' . $key . '@example.test', str_repeat('x', 73));
        $check(false, 'Overlong passwords are rejected without truncation');
    } catch (RuntimeException $e) {
        $check(true, 'Overlong passwords are rejected without truncation');
    }
} finally {
    $db->rollBack();
    $_SESSION = $savedSession;
}
if ($failures !== []) { exit(1); }
