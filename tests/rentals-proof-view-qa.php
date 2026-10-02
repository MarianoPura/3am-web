<?php
declare(strict_types=1);

// Read-only database QA: isolated encrypted proof files are removed afterwards.
$root = sys_get_temp_dir() . '/rentals-proof-view-qa-' . bin2hex(random_bytes(6));
$_ENV['APP_ENV'] = 'production';
$_ENV['APP_KEY'] = bin2hex(random_bytes(32));
$_ENV['RENTALS_STORAGE_ROOT'] = $root;
require dirname(__DIR__) . '/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$directory = $root . '/payment';
if (!mkdir($directory, 0700, true)) { throw new RuntimeException('QA temporary directory unavailable.'); }
$files = [];
$reference = static function (string $extension, string $plain) use ($directory, &$files): string {
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    $nonce = random_bytes(12);
    $tag = '';
    $key = hash_hmac('sha256', '3am-rentals-proof-v1', (string) config('app.key'), true);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) { throw new RuntimeException('QA encryption failed.'); }
    $path = $directory . '/' . $name;
    file_put_contents($path, '3AMPROOF1' . $nonce . $tag . $cipher);
    $files[] = $path;
    return 'micro/payment/' . $name;
};

try {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', true);
    $imagePath = $reference('png', $png);
    $image = \App\Services\RentalPaymentProof::response($imagePath);
    $assert($image->status() === 200 && $image->body() === $png
        && $image->header('Content-Type') === 'image/png'
        && str_contains((string) $image->header('Content-Disposition'), 'inline;')
        && str_contains((string) $image->header('Cache-Control'), 'no-store'),
        'Encrypted image proof did not return inline, uncached plaintext to the protected caller.');

    $pdfBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    $pdfPath = $reference('pdf', $pdfBytes);
    $pdf = \App\Services\RentalPaymentProof::response($pdfPath);
    $assert($pdf->status() === 200 && $pdf->body() === $pdfBytes
        && $pdf->header('Content-Type') === 'application/pdf'
        && str_contains((string) $pdf->header('Content-Disposition'), 'inline;'),
        'Encrypted PDF proof did not return the correct inline content type.');

    $assert(\App\Services\RentalPaymentProof::response(null)->status() === 404,
        'A missing proof did not return 404.');
    $imageFile = $directory . '/' . basename($imagePath);
    $original = file_get_contents($imageFile);
    $tampered = $original;
    $tampered[strlen($tampered) - 1] = chr(ord($tampered[strlen($tampered) - 1]) ^ 1);
    file_put_contents($imageFile, $tampered);
    try {
        $invalid = \App\Services\RentalPaymentProof::response($imagePath);
        $assert($invalid->status() === 422 && $invalid->body() !== $png,
            'A failed proof authentication was mistaken for a missing file or exposed plaintext.');
    } finally {
        file_put_contents($imageFile, $original);
    }

    $record = [
        'id' => 42, 'order_number' => 'QA-42', 'customer_name' => 'QA Customer',
        'customer_email' => 'qa@example.test', 'customer_phone' => '',
        'payment_method' => 'QA manual method', 'payment_status' => 1,
        'payment_reference' => '', 'created_at' => '2026-10-01',
        'payment_reviewed_at' => '2026-10-01', 'subtotal' => 100,
        'security_deposit' => 0, 'total_amount' => 100,
        'notes' => '', 'payment_type' => 'manual',
    ];
    $view = new \App\Core\View(BASE_PATH . '/app/Views');
    $render = static fn (?string $path): string => $view->render('rentals.admin.orders-view', [
        'record' => array_replace($record, ['payment_proof_path' => $path]),
        'details' => [], 'section' => 'orders', 'adminUser' => ['name' => 'QA Admin'],
    ]);
    $imageHtml = $render($imagePath);
    $assert(str_contains($imageHtml, 'data-admin-proof-image')
        && str_contains($imageHtml, url('rentals/admin/proof/42'))
        && !str_contains($imageHtml, $imagePath),
        'Admin image proof did not use the protected URL.');
    $pdfHtml = $render($pdfPath);
    $assert(str_contains($pdfHtml, 'Open PDF proof in a new tab')
        && !str_contains($pdfHtml, 'data-admin-proof-image'),
        'Admin PDF proof did not use the protected PDF link.');
    $assert(str_contains($render(null), 'No payment proof has been uploaded yet.')
        && str_contains($render('micro/payment/' . str_repeat('0', 32) . '.png'), 'The saved payment proof file is unavailable.'),
        'Admin missing-proof empty state failed.');
    echo "PASS: encrypted image/PDF proof responses and Admin proof/empty-state markup.\n";
} finally {
    foreach ($files as $file) { @unlink($file); }
    @rmdir($directory);
    @rmdir($root);
}
