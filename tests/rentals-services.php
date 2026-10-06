<?php
declare(strict_types=1);

// Isolated local fixtures. Never applies DDL or sends real mail.
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)
    || !in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Local loopback database only.');
}
$db = $container->get(App\Core\Database::class);
$store = new App\Services\RentalServiceRequests($db);
if (!$store->ready()) { throw new RuntimeException('Apply the reviewed Services additive SQL to the local test database first.'); }
$assert = static function (bool $pass, string $message): void { if (!$pass) { throw new RuntimeException($message); } };
$columns = array_column($db->select('SHOW COLUMNS FROM rental_service_requests'), 'Field');
$expectedColumns = ['id', 'reference', 'submission_key', 'user_id', 'rental_item_id', 'service_name',
    'customer_name', 'customer_email', 'customer_phone', 'event_start_date', 'event_end_date', 'location',
    'details', 'status', 'customer_message', 'reviewed_by', 'reviewed_at', 'notification_attempted_at', 'created_at', 'updated_at'];
$assert(array_diff($expectedColumns, $columns) === [], 'Services schema is missing a required column.');
$indexes = array_column($db->select('SHOW INDEX FROM rental_service_requests'), 'Key_name');
foreach (['PRIMARY', 'uq_service_request_reference', 'uq_service_request_submission', 'idx_service_request_customer',
          'idx_service_request_status', 'idx_service_request_item', 'idx_service_request_reviewer'] as $index) {
    $assert(in_array($index, $indexes, true), 'Missing service index: ' . $index);
}
$assert((int) $db->selectValue("SELECT COUNT(*) FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE() AND table_name = 'rental_service_requests' AND referenced_table_name IS NOT NULL") === 3,
    'Services ownership/item/reviewer foreign keys are missing.');
$request = static function (array $fields = [], bool $post = false): App\Core\Request {
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $_SERVER['REQUEST_URI'] = '/rentals/services';
    $_POST = $post ? $fields : []; $_GET = $post ? [] : $fields; $_FILES = [];
    return App\Core\Request::capture();
};
$key = bin2hex(random_bytes(6));
$users = []; $category = $service = $equipment = 0; $records = [];
$dir = sys_get_temp_dir() . '/rentals-services-' . $key;
$inquiries = new App\Services\InquiryStore($dir, 'QA services');
$container->set(App\Services\InquiryStore::class, $inquiries);
$ordersBefore = (int) $db->selectValue('SELECT COUNT(*) FROM order_header');
$fields = ['phone' => 'QA phone', 'start_date' => date('Y-m-d', strtotime('+5 days')),
    'end_date' => date('Y-m-d', strtotime('+6 days')), 'location' => 'QA event venue',
    'details' => 'Event coverage with cameras, camera crew and streaming support <script>unsafe</script>'];
try {
    foreach (['customer', 'customer', 'admin', 'superadmin'] as $i => $role) {
        $users[] = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
            ['[TEST] Services ' . $key, 'service-' . $key . '-' . $i . '@example.test', password_hash($key, PASSWORD_DEFAULT), $role]);
    }
    $category = $db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)', ['[TEST] Services ' . $key, 'services-' . $key]);
    $service = $db->insert('INSERT INTO rental_items(category_id,name,slug,is_service,availability_status,available_quantity) VALUES (?,?,?,1,?,0)',
        [$category, '[TEST] Event coverage ' . $key, 'service-' . $key, 'inquire']);
    $equipment = $db->insert('INSERT INTO rental_items(category_id,name,slug,is_service) VALUES (?,?,?,0)', [$category, '[TEST] Equipment ' . $key, 'equipment-' . $key]);
    $assert($store->service($service) !== null && $store->service($equipment) === null, 'Equipment accepted as a service.');
    foreach ([['phone', ''], ['location', ''], ['details', ''], ['start_date', '2026-02-30'],
              ['end_date', date('Y-m-d', strtotime('-1 day'))], ['details', str_repeat('x', 5001)]] as [$field, $value]) {
        $assert(isset(App\Services\RentalServiceRequests::errors(array_replace($fields, [$field => $value]))[$field]), 'Missing field validation: ' . $field);
    }
    $assert(isset(App\Services\RentalServiceRequests::errors(array_replace($fields, ['end_date' => date('Y-m-d', strtotime('+4 days'))]))['end_date']), 'Reversed range accepted.');
    $controller = new App\Controllers\Rentals\RentalServiceRequestController($container);
    $_SESSION = [];
    $assert($controller->show($request(), (string) $service)->status() === 302
        && $_SESSION['rentals_after_auth'] === 'services/' . $service . '/request', 'Guest request/login destination lost.');
    foreach ([null, $users[0], $users[1]] as $user) {
        $_SESSION = $user ? ['user_id' => $user] : [];
        $assert($controller->adminList($request())->status() === 403, 'Service admin list exposed to guest/customer.');
        $assert($controller->review($request(['decision' => 'approved'], true), '1')->status() === 403, 'Customer could review service requests.');
    }
    $_SESSION = ['user_id' => $users[0]];
    $form = $controller->show($request(), (string) $service);
    $assert($form->status() === 200 && str_contains($form->body(), 'Event coverage ' . $key)
        && str_contains($form->body(), 'name="request_key"') && !str_contains($form->body(), 'payment_method_id'), 'Selected service/safe enquiry form missing.');
    $formKey = $_SESSION['rentals_service_form_keys'][$service];
    $invalid = $controller->submit($request(array_replace($fields, ['phone' => '', 'request_key' => $formKey]), true), (string) $service);
    $assert($invalid->status() === 422 && str_contains($invalid->body(), 'Contact number is required.')
        && str_contains($invalid->body(), 'QA event venue'), 'Validation loses fields or hides errors.');
    $countBefore = (int) $db->selectValue('SELECT COUNT(*) FROM rental_service_requests');
    $forged = $controller->submit($request($fields + ['request_key' => bin2hex(random_bytes(32))], true), (string) $service);
    $assert($forged->status() === 422 && (int) $db->selectValue('SELECT COUNT(*) FROM rental_service_requests') === $countBefore, 'Forged submission key saved a request.');
    $post = $fields + ['request_key' => $formKey, 'email' => 'forged@example.test'];
    $response = $controller->submit($request($post, true), (string) $service);
    $record = $db->selectOne('SELECT * FROM rental_service_requests WHERE user_id=?', [$users[0]]);
    if ($record) { $records[] = (int) $record['id']; }
    $assert($response->status() === 303 && $record && $record['status'] === 'pending'
        && $record['service_name'] === '[TEST] Event coverage ' . $key
        && $record['customer_email'] === 'service-' . $key . '-0@example.test', 'Submission/status/dynamic saved customer incorrect.');
    $controller->submit($request($post, true), (string) $service);
    $assert((int) $db->selectValue('SELECT COUNT(*) FROM rental_service_requests WHERE user_id=?', [$users[0]]) === 1,
        'Repeated submission created duplicate requests.');
    $lines = file($dir . '/inquiries.jsonl', FILE_IGNORE_NEW_LINES);
    $assert(count($lines) === 1 && str_contains($lines[0], $record['reference']) && str_contains($lines[0], 'Event coverage'), 'Repeated submission duplicated/lost the existing inquiry confirmation.');
    $assert((int) $db->selectValue('SELECT COUNT(*) FROM order_header') === $ordersBefore, 'Service inquiry created a financial/equipment order.');
    $_SESSION = ['user_id' => $users[1]];
    $assert($store->history($users[1]) === [] && !str_contains($controller->history($request())->body(), $record['reference']), 'Other customer can read private service history.');
    $assert($controller->adminView($request(), (string) $record['id'])->status() === 403, 'Customer accessed Admin detail.');
    $_SESSION = ['user_id' => $users[2]];
    $assert($controller->adminList($request())->status() === 200 && $controller->adminView($request(), (string) $record['id'])->status() === 200, 'Admin list/detail failed.');
    $assert($store->review((int) $record['id'], $users[2], 'approved', 'Scope accepted <script>unsafe</script>'), 'Admin approval failed.');
    $assert(!$store->review((int) $record['id'], $users[2], 'rejected', 'Overwrite'), 'Repeated/conflicting review changed a final decision.');
    $_SESSION = ['user_id' => $users[0]];
    $history = $controller->history($request(['status' => 'approved']))->body();
    $assert(str_contains($history, 'Approved') && str_contains($history, '&lt;script&gt;unsafe&lt;/script&gt;')
        && !str_contains($history, '<script>unsafe') && !str_contains($history, $record['submission_key']), 'History status or escaped private details incorrect.');
    $assert($store->history($users[0], 'pending') === [], 'Pending filter contains approved requests.');
    $second = $store->submit($users[0], $service, bin2hex(random_bytes(32)), $fields);
    $records[] = (int) $second['id'];
    $assert($store->review((int) $second['id'], $users[3], 'rejected', 'Team unavailable'), 'Superadmin rejection failed.');
    $assert(count($store->history($users[0], 'rejected')) === 1, 'Rejected history/filter missing.');
    // Loopback connection refusal exercises failure handling without using real SMTP.
    $failure = $store->submit($users[0], $service, bin2hex(random_bytes(32)), $fields);
    $records[] = (int) $failure['id'];
    $smtp = new App\Services\SmtpMailer(['enabled' => true, 'host' => '127.0.0.1', 'port' => 1,
        'encryption' => 'none', 'timeout' => 1, 'from' => ['address' => 'qa@example.test', 'name' => 'QA']]);
    $failedInquiry = new App\Services\InquiryStore($dir, 'QA services', mailer: $smtp);
    $store->notify($failure, $failedInquiry);
    $store->notify($failure, $failedInquiry);
    $assert($store->find((int) $failure['id'])['status'] === 'pending' && count(file($dir . '/inquiries.jsonl')) === 2,
        'SMTP failure lost the saved request or repeated notification capture.');
    $db->update('UPDATE rental_categories SET is_active=0 WHERE id=?', [$category]);
    $assert($store->service($service) === null, 'Inactive category still accepts new services.');
    $assert(count($store->history($users[0])) === 3, 'Historical requests disappeared when service category became inactive.');
    echo "PASS: selected service request; field/date validation; login return; owner-only history; Admin/Superadmin review; Pending/Approved/Rejected filters; escaped customer messages; no payment orders; duplicate submission/notification protection; loopback SMTP failure safety; history survives inactive services.\n";
} finally {
    foreach ($records as $id) { $db->delete('DELETE FROM rental_service_requests WHERE id=?', [$id]); }
    // Include any fixture created just before an assertion failed.
    foreach ($users as $id) { $db->delete('DELETE FROM rental_service_requests WHERE user_id=?', [$id]); }
    if ($service) { $db->delete('DELETE FROM rental_items WHERE id=?', [$service]); }
    if ($equipment) { $db->delete('DELETE FROM rental_items WHERE id=?', [$equipment]); }
    if ($category) { $db->delete('DELETE FROM rental_categories WHERE id=?', [$category]); }
    foreach ($users as $id) { $db->delete('DELETE FROM users WHERE id=?', [$id]); }
    if (is_file($dir . '/inquiries.jsonl')) { unlink($dir . '/inquiries.jsonl'); }
    if (is_dir($dir)) { rmdir($dir); }
}
