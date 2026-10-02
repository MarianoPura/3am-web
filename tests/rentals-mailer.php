<?php
declare(strict_types=1);
// Local integration QA. Only an in-memory transport is used; no real mail.
$_ENV['MAIL_ENABLED'] = 'false';
$_ENV['RENTALS_STORAGE_ROOT'] = sys_get_temp_dir() . '/rentals-fixed-mail-qa-' . bin2hex(random_bytes(6));
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$fake = new class implements App\Services\RentalMailTransport {
    public array $messages = [];
    public string $mode = 'success';
    public function isConfigured(): bool { return $this->mode !== 'unconfigured'; }
    public function send(string $destination, string $subject, string $text, string $html, array $cc = []): void {
        if ($this->mode === 'failure') { throw new RuntimeException('PRIVATE SMTP FAILURE'); }
        if ($this->mode === 'uncertain') { throw new App\Services\SmtpDeliveryUncertain(); }
        $this->messages[] = compact('destination', 'subject', 'text', 'html', 'cc');
    }
};
$container->set(App\Services\RentalMailTransport::class, $fake);
$mailer = $container->get(App\Services\RentalNotification::class);
$events = App\Services\RentalMailEvents::class;
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$request = static function (string $decision): App\Core\Request {
    $_POST = ['decision' => $decision]; $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/rentals/admin/orders/1/review-proof';
    return App\Core\Request::capture();
};
$key = bin2hex(random_bytes(6)); $email = 'mailer-' . $key . '@example.test';
$users = []; $orders = []; $category = $item = $method = 0; $proofs = [];
$countCustomer = static fn (): int => count(array_filter($fake->messages, static fn ($m) => $m['destination'] === $email));
try {
    $users[] = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
        ['[TEST] Mail customer', $email, password_hash($key, PASSWORD_DEFAULT), 'customer']);
    $users[] = $db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',
        ['[TEST] Mail admin', 'mailer-admin-' . $key . '@example.test', password_hash($key, PASSWORD_DEFAULT), 'admin']);
    $category = $db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)', ['[TEST] Mail category', 'mail-' . $key]);
    $item = $db->insert('INSERT INTO rental_items(category_id,name,slug,rental_rate,available_quantity) VALUES (?,?,?,?,?)',
        [$category, '[TEST] Mail camera', 'mail-' . $key, 100, 100]);
    $method = $db->insert('INSERT INTO payment_methods(name,type) VALUES (?,?)', ['[TEST] Mail method ' . $key, 'manual']);
    // Existing review requires a stored file. Isolate all fake proofs from user uploads.
    $directory = App\Services\RentalStorage::directory('payment');
    $newProof = static function () use ($directory, &$proofs): string {
        $name = bin2hex(random_bytes(16)) . '.png';
        $proofs[] = $directory . '/' . $name;
        file_put_contents($proofs[array_key_last($proofs)], 'isolated QA fixture; never emailed');
        return 'micro/payment/' . $name;
    };
    $newOrder = static function (bool $proof = true) use ($db, &$orders, $users, $email, $method, $item, $newProof, $key): int {
        $id = $db->insert('INSERT INTO order_header
            (order_number,user_id,customer_name,customer_email,customer_phone,payment_method_id,payment_proof_path,
             status_token,subtotal,security_deposit,total_amount,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            ['MAIL-' . $key . '-' . count($orders), $users[0], '[TEST] Customer <b>safe</b>', $email,
                'Sample phone', $method, $proof ? $newProof() : null, bin2hex(random_bytes(32)), 300, 100, 400, 'PRIVATE INTERNAL NOTE']);
        $orders[] = $id;
        $db->insert('INSERT INTO order_details
            (order_header_id,rental_item_id,item_name,quantity,rental_start_date,rental_end_date,line_total) VALUES (?,?,?,?,?,?,?)',
            [$id, $item, 'Camera <script>unsafe</script>', 2, '2026-11-10', '2026-11-12', 300]);
        return $id;
    };
    $assert(config('rentals-mail.owner') === 'lilbeemail88@gmail.com', 'Fixed owner differs from requirement.');
    $assert(config('rentals-mail.cc') === ['rentals@example.com', 'accounting@example.com', 'operations@example.com'], 'Fixed CC differs.');
    $first = $newOrder();
    // Guards must stop mail before the transaction commits.
    $db->beginTransaction();
    try { $mailer->rentalSubmitted($first); } finally { $db->rollBack(); }
    $assert(!$fake->messages, 'Mail sent before commit.');
    $mailer->rentalSubmitted($first);
    $assert($countCustomer() === 1 && count($fake->messages) === 2, 'Pending workflow must send one customer and one company message.');
    $customer = $fake->messages[0]; $company = $fake->messages[1];
    $assert($customer['cc'] === [] && $company['destination'] === config('rentals-mail.owner')
        && $company['cc'] === config('rentals-mail.cc'), 'Company CC missing or leaked onto customer message.');
    $assert(str_starts_with($customer['subject'], 'Rental Request Received — MAIL-')
        && str_starts_with($company['subject'], 'New Rental Request — MAIL-'), 'Pending subjects incorrect.');
    $assert(str_contains($customer['text'], 'Pending') && str_contains($customer['text'], 'payment proof was received')
        && str_contains($customer['text'], '₱400.00') && str_contains($customer['text'], '× 2')
        && str_contains($company['text'], 'Proof submitted: Yes'), 'Order snapshot/receipt missing.');
    foreach ([$customer, $company] as $message) {
        $assert(!str_contains($message['text'], 'PRIVATE INTERNAL NOTE') && !str_contains($message['text'], 'micro/payment')
            && !str_contains($message['html'], '<script>') && str_contains($message['html'], '&lt;script&gt;'), 'Sensitive data or executable markup leaked.');
        $assert(!str_contains($message['text'], '{{'), 'Unsubstituted fixed variable.');
    }
    $assert(!str_contains($customer['text'], '/rentals/admin'), 'Protected company link leaked to customer.');
    $mailer->rentalSubmitted($first); $assert(count($fake->messages) === 2, 'Repeated submission resent mail.');
    // Account edits must never redirect an existing transaction email.
    $db->update('UPDATE users SET email=? WHERE id=?', ['changed-' . $key . '@example.test', $users[0]]);
    $_SESSION = ['user_id' => $users[1]]; $admin = new App\Controllers\Rentals\RentalAdminController($container);
    $before = count($fake->messages);
    $response = $admin->reviewProof($request('approved'), (string) $first);
    $assert($response->status() === 302 && count($fake->messages) === $before + 2 && $countCustomer() === 2, 'Approved customer/company workflow failed.');
    $approved = $fake->messages[$before];
    $assert(str_starts_with($approved['subject'], 'Rental Request Approved — ')
        && str_contains($approved['text'], 'rental request and payment review have been approved'), 'Approval not combined.');
    $before = count($fake->messages); $admin->reviewProof($request('approved'), (string) $first);
    $mailer->reviewed($first, App\Services\RentalPaymentStatus::APPROVED);
    $mailer->reviewed($first, App\Services\RentalPaymentStatus::REJECTED);
    $assert(count($fake->messages) === $before, 'Repeated/mismatched approval generated mail.');
    $second = $newOrder(); $mailer->rentalSubmitted($second); $before = count($fake->messages);
    $admin->reviewProof($request('rejected'), (string) $second);
    $assert(count($fake->messages) === $before + 2
        && str_starts_with($fake->messages[$before]['subject'], 'Rental Request Update — ')
        && str_starts_with($fake->messages[$before + 1]['subject'], 'Rental Request Rejected — ')
        && str_contains($fake->messages[$before]['text'], 'rental request and payment review were rejected'), 'Rejection not combined.');
    $before = count($fake->messages); $admin->reviewProof($request('rejected'), (string) $second);
    $assert(count($fake->messages) === $before, 'Repeated rejection resent mail.');
    // Keep the existing replacement-proof event and allow its next real review.
    $db->update('UPDATE order_header SET payment_proof_path=?,payment_status=0,payment_reviewed_at=NULL WHERE id=?', [$newProof(), $second]);
    $mailer->paymentProofReceived($second); $before = count($fake->messages); $mailer->paymentProofReceived($second);
    $assert(count($fake->messages) === $before, 'Same replacement proof resent mail.');
    $admin->reviewProof($request('approved'), (string) $second);
    $assert(count($fake->messages) === $before + 2, 'New proof revision blocked new review.');
    // SMTP failure leaves the actual Approved state, records safe failure, and never auto-resends.
    $third = $newOrder(); $fake->mode = 'failure'; $admin->reviewProof($request('approved'), (string) $third);
    $assert((int) $db->selectValue('SELECT payment_status FROM order_header WHERE id=?', [$third]) === 1, 'SMTP failure undid approval.');
    $failures = $db->select('SELECT status,failure_category FROM rental_notification_deliveries WHERE order_id=?', [$third]);
    $assert(count($failures) === 2 && !array_filter($failures, static fn ($d) => $d['status'] !== 'failed' || $d['failure_category'] !== 'smtp_failure'), 'Safe failure categories missing.');
    $fake->mode = 'success'; $before = count($fake->messages); $mailer->reviewed($third, 1);
    $assert(count($fake->messages) === $before, 'Refresh retried failed event unsafely.');
    $fourth = $newOrder(); $fake->mode = 'uncertain'; $mailer->rentalSubmitted($fourth);
    $assert((int) $db->selectValue("SELECT COUNT(*) FROM rental_notification_deliveries WHERE order_id=? AND status='uncertain'", [$fourth]) === 2, 'Unknown SMTP acceptance not protected.');
    $fake->mode = 'success'; $before = count($fake->messages); $mailer->rentalSubmitted($fourth);
    $assert(count($fake->messages) === $before, 'Uncertain event resent.');
    $fifth = $newOrder(); $fake->mode = 'unconfigured'; $mailer->rentalSubmitted($fifth);
    $assert((int) $db->selectValue("SELECT COUNT(*) FROM rental_notification_deliveries WHERE order_id=? AND failure_category='smtp_not_configured'", [$fifth]) === 2
        && (int) $db->selectValue('SELECT payment_status FROM order_header WHERE id=?', [$fifth]) === 0, 'Missing SMTP did not preserve Pending.');
    $fake->mode = 'success'; $sixth = $newOrder(false); $mailer->rentalSubmitted($sixth);
    $assert(str_contains($fake->messages[count($fake->messages) - 2]['text'], 'No payment proof is currently attached.')
        && str_contains($fake->messages[array_key_last($fake->messages)]['text'], 'Proof submitted: No'), 'Receipt incorrectly claims a missing proof.');
    // Compatibility: historical payment_approved delivery shares the same key.
    $seventh = $newOrder();
    $db->update('UPDATE order_header SET payment_status=1,payment_reviewed_at=CURRENT_TIMESTAMP WHERE id=?', [$seventh]);
    $version = hash('sha256', (string) $db->selectValue('SELECT payment_proof_path FROM order_header WHERE id=?', [$seventh]));
    foreach (['customer' => $email, 'admin' => config('rentals-mail.owner')] as $audience => $destination) {
        $db->insert("INSERT INTO rental_notification_deliveries(dedup_key,event_key,event_version,order_id,audience,recipient_email,status,attempts)
            VALUES (?,'payment_approved',?,?,?,?,'sent',1)",
            [hash('sha256', $seventh . '|review_approved|' . $version . '|' . $audience . '|' . strtolower($destination)),
                $version, $seventh, $audience, $destination]);
    }
    $before = count($fake->messages); $mailer->reviewed($seventh, 1);
    $assert(count($fake->messages) === $before, 'Deployment resent historical combined review.');
    $eighth = $newOrder();
    $db->update('UPDATE order_header SET customer_email=? WHERE id=?', ['invalid', $eighth]);
    $before = count($fake->messages); $mailer->rentalSubmitted($eighth);
    $assert(count($fake->messages) === $before + 1 && $fake->messages[$before]['destination'] === config('rentals-mail.owner')
        && $db->selectValue("SELECT failure_category FROM rental_notification_deliveries WHERE order_id=? AND audience='customer'", [$eighth]) === 'invalid_destination',
        'Invalid customer address prevented independent company notification.');
    foreach ($fake->messages as $message) {
        if ($message['destination'] === $email) { $assert($message['cc'] === [], 'Customer CC must be empty.'); }
        else { $assert($message['destination'] === config('rentals-mail.owner') && $message['cc'] === config('rentals-mail.cc'), 'Company routing changed between events.'); }
    }
    $assert(!method_exists($mailer, 'sendTest') && !method_exists($mailer, 'retry')
        && !class_exists(App\Controllers\Rentals\RentalEmailController::class)
        && !class_exists(App\Models\RentalMailSettings::class), 'Editable mailer remains.');
    echo "PASS: fixed Owner/CC, dynamic order customer, combined Pending/Approved/Rejected, post-commit flow, escaped snapshots, real review transitions, duplicate and legacy delivery protection, SMTP failure safety, proof replacement, no editable mailer.\n";
} finally {
    foreach ($orders as $id) { $db->delete('DELETE FROM rental_notification_deliveries WHERE order_id=?', [$id]); $db->delete('DELETE FROM order_header WHERE id=?', [$id]); }
    if ($item) { $db->delete('DELETE FROM rental_items WHERE id=?', [$item]); }
    if ($category) { $db->delete('DELETE FROM rental_categories WHERE id=?', [$category]); }
    if ($method) { $db->delete('DELETE FROM payment_methods WHERE id=?', [$method]); }
    foreach ($users as $id) { $db->delete('DELETE FROM users WHERE id=?', [$id]); }
    foreach ($proofs as $path) { @unlink($path); }
    if (isset($directory)) { @unlink($directory . '/.htaccess'); @rmdir($directory); }
    @rmdir($_ENV['RENTALS_STORAGE_ROOT']);
    $_SESSION = [];
}
