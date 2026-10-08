<?php
declare(strict_types=1);

// Local regression: all fixture writes roll back, with no uploads or email.
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)
    || config('database.connections.mysql.database') !== 'd3am_rentals_new') {
    throw new RuntimeException('Local d3am_rentals_new only.');
}
$db = $container->get(App\Core\Database::class);
$check = static function (bool $ok, string $why): void {
    if (!$ok) { throw new RuntimeException($why); }
};
$request = static function (array $fields = [], array $query = []): App\Core\Request {
    $_POST = $fields; $_GET = $query; $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = $fields === [] ? 'GET' : 'POST';
    $_SERVER['REQUEST_URI'] = '/rentals/admin/items';
    return App\Core\Request::capture();
};
$rejects = static function (callable $operation, string $message) use ($check): void {
    try { $operation(); } catch (InvalidArgumentException $e) {
        $check(str_contains($e->getMessage(), $message), 'Unexpected validation: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected validation: ' . $message);
};
$check(!in_array('is_service', array_column($db->select('SHOW COLUMNS FROM rental_items'), 'Field'), true), 'Item type column must remain removed.');
$before = [];
foreach (['rental_items', 'rental_categories', 'users', 'order_header', 'order_details', 'carts', 'cart_items', 'rental_service_requests'] as $table) {
    $before[$table] = (int) $db->selectValue("SELECT COUNT(*) FROM $table");
}
$db->beginTransaction();
try {
    $key = bin2hex(random_bytes(6));
    $admin = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
        ['[TEST] Category admin', 'category-admin-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), 'admin']);
    $_SESSION = ['user_id' => $admin];
    $controller = new App\Controllers\Rentals\RentalAdminController($container);
    $saveCategory = new ReflectionMethod($controller, 'validateAndSaveCategory');
    $saveItem = new ReflectionMethod($controller, 'validateAndSaveItem');
    $categories = [];
    foreach ([0, 1] as $type) {
        $slug = "classification-$key-$type";
        $saveCategory->invoke($controller, $request(['name' => "[TEST] Classification $key $type", 'slug' => $slug, 'is_service' => (string) $type]), 0);
        $categories[$type] = (int) $db->selectValue('SELECT id FROM rental_categories WHERE slug=?', [$slug]);
        $check($categories[$type] > 0, 'Category create failed.');
    }
    $base = ['name' => "[TEST] Product $key", 'slug' => '', 'sku' => '', 'description' => 'Regression',
        'ideal_use' => 'Tests', 'availability_status' => 'available', 'rental_unit' => 'day',
        'rental_rate' => '125.00', 'security_deposit' => '10', 'available_quantity' => '4', 'is_active' => '1'];
    $ids = [];
    foreach ([0, 1] as $type) {
        // Deliberately forged posted type must never override the category.
        $fields = array_replace($base, ['category_id' => (string) $categories[$type],
            'slug' => "product-$key-$type", 'is_service' => (string) (1 - $type)]);
        if ($type === 1) {
            $fields = array_replace($fields, ['available_quantity' => 'stale', 'security_deposit' => 'stale', 'availability_status' => 'stale']);
        }
        $saveItem->invoke($controller, $request($fields), 0);
        $row = $db->selectOne('SELECT i.*, c.is_service FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE i.slug=?', [$fields['slug']]);
        $ids[$type] = (int) $row['id'];
        $check((int) $row['is_service'] === $type, 'Posted type overrode category.');
        if ($type === 1) { $check((int) $row['available_quantity'] === 0 && $row['availability_status'] === 'inquire', 'Service used equipment controls.'); }
        $fields['name'] .= ' edited';
        $saveItem->invoke($controller, $request($fields), $ids[$type]);
        $check($db->selectValue('SELECT name FROM rental_items WHERE id=?', [$ids[$type]]) === $fields['name'], 'Product edit failed.');
        foreach (['itemsEdit' => [(string) $ids[$type]], 'itemsNew' => [], 'categoriesEdit' => [(string) $categories[$type]], 'categoriesNew' => []] as $method => $args) {
            $response = $controller->$method($request(), ...$args);
            $check($response->status() === 200, "$method failed.");
        }
        $response = $controller->items($request([], ['q' => $key, 'type' => $type ? 'service' : 'equipment', 'category' => $categories[$type]]));
        $check($response->status() === 200 && str_contains($response->body(), $fields['name']), 'Admin search/category/type filter failed.');
        $saveCategory->invoke($controller, $request(['name' => "[TEST] Classification $key $type", 'slug' => "classification-$key-$type", 'is_service' => (string) $type, 'description' => 'Updated']), $categories[$type]);
        $rejects(fn () => $saveCategory->invoke($controller, $request(['name' => "[TEST] Classification $key $type", 'is_service' => (string) (1-$type)]), $categories[$type]), 'protect inventory');
    }
    $rejects(fn () => $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => (string) $categories[1]])), $ids[0]), 'protect inventory');
    $rejects(fn () => $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => '999999999999999999'])), 0), 'existing category');
    $rejects(fn () => $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => (string) $categories[0], 'catalogue_type' => '1'])), 0), 'matching');
    $rejects(fn () => $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => (string) $categories[0], 'catalogue_type' => 'invalid'])), 0), 'matching');
    $db->update('UPDATE rental_categories SET is_active=0 WHERE id=?', [$categories[0]]);
    $rejects(fn () => $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => (string) $categories[0]])), 0), 'active category');
    // Existing products in inactive categories remain editable without moving category.
    $saveItem->invoke($controller, $request(array_replace($base, ['category_id' => (string) $categories[0], 'catalogue_type' => '0'])), $ids[0]);
    $db->update('UPDATE rental_categories SET is_active=1 WHERE id=?', [$categories[0]]);
    $newForm = $controller->itemsNew($request())->body();
    $check((bool) preg_match('/<select name="catalogue_type" data-rental-type>/', $newForm), 'New product type selector must be enabled.');
    $editForm = $controller->itemsEdit($request(), (string) $ids[0])->body();
    $check(!str_contains($editForm, 'value="' . $categories[1] . '"'), 'Edit offered a category of the other type.');
    $requestController = new App\Controllers\Rentals\RentalServiceRequestController($container);
    $emptyRequests = $requestController->adminList($request([], ['q' => 'no-matching-request-' . $key, 'status' => 'all']));
    $check($emptyRequests->status() === 200 && str_contains($emptyRequests->body(), 'No service requests match this view.'), 'Empty Admin request list returned an error.');
    $catalog = new App\Models\RentalCatalog($db);
    $data = $catalog->load();
    $check(!$data['catalogUnavailable'], 'Catalogue failed.');
    $check(in_array($ids[0], array_map('intval', array_column($data['items'], 'db_id')), true), 'Equipment not listed.');
    $check(in_array($ids[1], array_map('intval', array_column($data['services'], 'db_id')), true), 'Service not listed.');
    $check(in_array($categories[0], array_map('intval', array_column($data['categories'], 'db_id')), true)
        && !in_array($categories[1], array_map('intval', array_column($data['categories'], 'db_id')), true), 'Sidebar classification failed.');
    $services = new App\Services\RentalServiceRequests($db);
    $check($services->service($ids[1]) !== null && $services->service($ids[0]) === null, 'Service endpoint classification failed.');
    $check(!$catalog->isAvailable($catalog->find((string) $ids[1]), 1, null, null), 'Service allowed equipment booking.');
    $check($controller->index($request())->status() === 200, 'Dashboard render failed.');
    $check($controller->availability($request([], ['month' => date('Y-m')]), (string) $ids[0])->status() === 200, 'Equipment calendar failed.');
    $check($controller->availability($request([], ['month' => date('Y-m')]), (string) $ids[1])->status() === 404, 'Service allowed equipment calendar.');
    $public = new App\Controllers\Rentals\RentalsController($container);
    foreach (['items', 'services', 'categories'] as $action) {
        $check($public->$action($request())->status() === 200, "Public $action failed.");
    }
    $customer = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
        ['[TEST] Category customer', 'category-customer-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), 'customer']);
    $payment = $db->insert('INSERT INTO payment_methods(name,type,account_name,account_number) VALUES (?,?,?,?)',
        ['[TEST] Category payment ' . $key, 'manual', 'Test account', '000000']);
    $_SESSION = ['user_id' => $customer];
    $cart = new App\Services\RentalCart();
    $start = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $cartController = new App\Controllers\Rentals\RentalCartController($container);
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $check($cartController->add($request(['id' => (string) $ids[1], 'quantity' => '1', 'rental_start_date' => $start, 'rental_end_date' => $start]))->status() === 422,
        'Service accepted into equipment cart.');
    $cart->add((string) $ids[0], 1, $start, $start);
    $checkout = new App\Services\RentalCheckout($db, $cart);
    $summary = $checkout->summary();
    $check(count($summary['items']) === 1 && (int) $summary['items'][0]['db_id'] === $ids[0]
        && (float) $summary['total'] === 135.0, 'Equipment checkout totals changed.');
    try {
        $checkout->createOrder($customer, ['name' => 'Test customer', 'email' => 'ignored@example.test', 'phone' => 'Test phone',
            'payment_method_id' => $payment, 'notes' => '']);
        throw new LogicException('Checkout accepted missing payment proof.');
    } catch (RuntimeException $e) {
        $check($e->getMessage() === 'Choose a JPG, PNG, WebP or PDF payment proof.', 'Unexpected checkout failure: ' . $e->getMessage());
    }
    echo "PASS: checkout reaches required proof validation; no proof-upload bypass or storage writes.\n";
    $orderCount = (int) $db->selectValue('SELECT COUNT(*) FROM order_header');
    $serviceRequest = $services->submit($customer, $ids[1], hash('sha256', $key), [
        'phone' => 'Test phone', 'start_date' => $start, 'end_date' => $start, 'location' => 'Test venue', 'details' => 'Crew for test event']);
    $check((int) $serviceRequest['rental_item_id'] === $ids[1], 'Service request used wrong item.');
    $services->review((int) $serviceRequest['id'], $admin, 'approved', '');
    $services->quote((int) $serviceRequest['id'], $admin, '250.00', 'Test quotation');
    $check((int) $db->selectValue('SELECT COUNT(*) FROM order_header') === $orderCount, 'Service request changed equipment orders.');
    echo "PASS: public pages, service cart rejection, equipment checkout totals, required payment proof, service request/review/quotation, and separate service/order workflows.\n";
    echo "PASS: Add/Edit products and categories, forged-type validation, type-change protection, missing categories, admin search/type/category filtering, catalogue/sidebar, service eligibility, calendars and dashboard rendering.\n";
} finally {
    $db->rollBack();
    $_SESSION = [];
}
foreach ($before as $table => $count) {
    $check((int) $db->selectValue("SELECT COUNT(*) FROM $table") === $count, "Fixture rollback failed: $table");
}
echo "PASS: all fixture rows rolled back; existing record counts unchanged.\n";
