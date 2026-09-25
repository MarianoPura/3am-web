<?php
declare(strict_types=1);
// Isolated behavioural tests: no database, emails or application session writes.
$container = require dirname(__DIR__) . '/bootstrap.php';
$check = static function(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$_SESSION = [];
$cart = new App\Services\RentalCart('rentals_test');
$cart->add('item-1', 2, '2026-11-18', '2026-11-20');
$cart->add('item-1', 1, '2026-11-18', '2026-11-20');
$check($cart->count() === 3 && count($cart->contents()) === 1, 'Matching rental period did not merge');
$cart->add('item-1', 1, '2026-12-01', '2026-12-03');
$check($cart->count() === 4 && count($cart->contents()) === 2, 'Separate rental period did not create a separate line');
$cart->remove('item-1');
$check($cart->empty(), 'Remove failed');
$row = $cart->add('item-2', 10000, '2026-02-30');
$check($row['quantity'] === 999 && $row['rental_start_date'] === null, 'Invalid input not bounded');
$cart->clear();
$check($cart->empty(), 'Clear failed');
foreach (['../.env', 'http://localhost/test.jpg', 'C:/Pictures/test.jpg', 'media/../.env', 'media/missing.jpg'] as $path) {
    $check(App\Models\RentalCatalog::imagePath($path) === null, 'Unsafe image accepted');
}
$check(App\Models\RentalCatalog::imagePath('media/rentals-camera-lineup.jpg') !== null, 'Valid public asset rejected');
// Verify failure does not silently become live inventory, without connecting anywhere.
class FailedRentalPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { throw new PDOException('Test connection failure'); }
}
$db = new App\Core\Database([]);
(new ReflectionProperty($db, 'pdo'))->setValue($db, new FailedRentalPDO());
$catalog = new App\Models\RentalCatalog($db);
$data = $catalog->load();
$check($data['catalogUnavailable'] === true, 'Failure not surfaced');
if (!config('rentals.preview_samples')) {
    $check($data['items'] === [] && $data['services'] === [], 'Failure exposed sample stock');
}
$container->set(App\Core\Database::class, $db);
$response = (new App\Controllers\Rentals\RentalCartController($container))->index(App\Core\Request::capture());
$check($response->status() === 200, 'Cart page failed to render');
echo "PASS: guest cart, quantity bounds, date validation, portable images, honest unavailable catalogue. No database writes.\n";
