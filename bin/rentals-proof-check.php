<?php
declare(strict_types=1);

// Read-only diagnostic. Prints no proof bytes, account data, or key material.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1]) || preg_match('/^[1-9][0-9]*$/D', $argv[1]) !== 1) {
    fwrite(STDERR, "Usage: php bin/rentals-proof-check.php <order-id>\n");
    exit(1);
}

$container = require dirname(__DIR__) . '/bootstrap.php';
$id = (int) $argv[1];
$row = $container->get(\App\Core\Database::class)->selectOne(
    'SELECT payment_proof_path FROM order_header WHERE id = ?', [$id]
);
if ($row === null) { echo "Order: not found\n"; exit(0); }

$reference = (string) ($row['payment_proof_path'] ?? '');
if ($reference === '') { echo "Proof reference: empty\n"; exit(0); }
echo "Proof reference: present\n";

if (preg_match('#^micro/payment/[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$#D', $reference) === 1) {
    try { $external = \App\Services\RentalStorage::root() . '/' . substr($reference, strlen('micro/')); }
    catch (RuntimeException $e) { echo "Storage root: invalid\n"; exit(0); }
    $legacy = BASE_PATH . '/' . $reference;
    echo 'External file: ' . (is_file($external) ? 'present' : 'missing') . "\n";
    echo 'Legacy project file: ' . (is_file($legacy) ? 'present' : 'missing') . "\n";
    $selected = is_file($external) ? $external : (is_file($legacy) ? $legacy : null);
    if ($selected !== null) { echo 'Selected storage: ' . ($selected === $external ? 'external' : 'legacy project') . "\n"; }
} elseif (preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)\.php$/D', $reference) === 1) {
    $selected = BASE_PATH . '/storage/rentals/payment-proofs/' . $reference;
    echo 'Legacy guarded file: ' . (is_file($selected) ? 'present' : 'missing') . "\n";
} else { echo "Proof reference: invalid format\n"; exit(0); }

if ($selected === null || !is_file($selected)) { echo "Result: missing file\n"; exit(0); }
if (!is_readable($selected)) { echo "Result: file not readable by this PHP process\n"; exit(0); }
$prefix = @file_get_contents($selected, false, null, 0, 64);
if ($prefix === false) { echo "Result: file read failed\n"; exit(0); }
$format = str_starts_with($prefix, '3AMPROOF1') ? 'encrypted' :
    (str_starts_with($prefix, '<?php http_response_code(404); exit; __halt_compiler();') ? 'legacy guarded' : 'unrecognized');
echo 'File format: ' . $format . "\n";

$environment = (string) config('app.env');
if (in_array($environment, ['local', 'testing'], true) && $format === 'encrypted'
    && !is_file(BASE_PATH . '/storage/rentals/payment-key.php')) {
    echo "Result: local test key is absent; diagnostic did not create one\n";
    exit(0);
}
if (!in_array($environment, ['local', 'testing'], true)) {
    echo 'Stable APP_KEY configured: ' . (strlen((string) config('app.key')) >= 32 ? 'yes' : 'no') . "\n";
}

try { $response = \App\Services\RentalPaymentProof::response($reference); }
catch (RuntimeException $e) {
    echo "Result: proof key/configuration unavailable\n";
    exit(0);
}
echo 'Protected response: HTTP ' . $response->status() . "\n";
if ($response->status() === 200) {
    echo 'Content-Type: ' . $response->header('Content-Type') . "\n";
    echo "Result: proof opens with this PHP process's configuration\n";
} elseif ($format === 'encrypted' && $response->status() === 422) {
    echo "Result: AES-GCM authentication failed; key mismatch or damaged ciphertext\n";
} elseif ($format === 'encrypted' && $response->status() === 404) {
    echo "Result: encrypted proof could not be opened; check key consistency and file integrity\n";
} else {
    echo "Result: proof format or file could not be opened\n";
}
