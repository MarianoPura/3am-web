<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run only against an isolated, seeded *_qa database. Never sends email.
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!str_ends_with((string) config('database.connections.mysql.database'), '_qa')) {
    fwrite(STDERR, "Use an isolated seeded database whose name ends in _qa.\n"); exit(1);
}
$db = $container->get(App\Core\Database::class);
$catalog = new App\Models\RentalCatalog($db);
$check = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$directory = sys_get_temp_dir() . '/3am-inquiry-test-' . bin2hex(random_bytes(6));
$container->set(App\Services\InquiryStore::class, new App\Services\InquiryStore($directory, '', '3AM QA'));
$controller = new App\Controllers\Web\InquiryController($container);
$_SESSION = [];
$_GET = $_COOKIE = $_FILES = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/start';
$_SERVER['REMOTE_ADDR'] = '192.0.2.' . random_int(1, 254);
$_POST = ['type' => 'Rentals', 'rental' => 'mirrorless-camera-kit', 'name' => 'QA Visitor', 'email' => 'qa@example.test', 'phone' => '0000000000', 'company' => '', 'details' => 'A test rental inquiry with a date and location.', '_t' => time() - 10];
$db->beginTransaction();
try {
    $check(count($catalog->load()['items']) === 10, 'Seed catalogue did not load.');
    $item = $catalog->find('mirrorless-camera-kit');
    $check($item !== null && str_contains($item['includes'], 'Camera body'), 'Inclusions did not load.');
    $response = $controller->submit(App\Core\Request::capture());
    $check($response->status() === 302 && str_ends_with($response->headers()['Location'], '/start/received'), 'Submission failed.');
    $record = json_decode(trim(file_get_contents($directory . '/inquiries.jsonl')), true);
    $check($record['type'] === 'Rentals' && str_contains($record['details'], 'Mirrorless Camera Kit [mirrorless-camera-kit]'), 'Rental selection not captured.');

    $db->update('UPDATE rental_items SET is_active = 0 WHERE slug = ?', ['mirrorless-camera-kit']);
    $check($catalog->find('mirrorless-camera-kit') === null, 'Inactive item remains public.');
    $controller->submit(App\Core\Request::capture());
    $check(isset($_SESSION['_errors']['rental']), 'Withdrawn rental not rejected.');
    $check($_SESSION['_old']['name'] === 'QA Visitor', 'Validation discarded form values.');

    $db->update('UPDATE rental_categories SET is_active = 0 WHERE slug = ?', ['audio']);
    $check(count($catalog->load()['items']) === 7, 'Inactive category remains public.');
    $_POST['type'] = 'forged';
    $controller->submit(App\Core\Request::capture());
    $check(isset($_SESSION['_errors']['type']), 'Forged project type accepted.');
    $_POST['type'] = 'Media / Production';
    $controller->submit(App\Core\Request::capture());
    $lines = file($directory . '/inquiries.jsonl', FILE_IGNORE_NEW_LINES);
    $record = json_decode(end($lines), true);
    $check(!str_contains($record['details'], 'Selected rental:'), 'Changing project type retained a rental attachment.');
    echo "PASS: database loading, inclusions, inactive items/categories, captured rental selection, type validation and preserved form values. No email sent.\n";
} finally {
    $db->rollBack();
    if (is_file($directory . '/inquiries.jsonl')) { unlink($directory . '/inquiries.jsonl'); }
    if (is_dir($directory)) { rmdir($directory); }
}
