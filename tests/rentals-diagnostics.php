<?php
declare(strict_types=1);

// Isolated service/logger QA; no database writes or live files.
$_ENV['APP_ENV'] = 'production';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['APP_KEY'] = bin2hex(random_bytes(32));
$root = sys_get_temp_dir() . '/rentals-diagnostic-' . bin2hex(random_bytes(6));
$_ENV['RENTALS_STORAGE_ROOT'] = $root;
require dirname(__DIR__) . '/bootstrap.php';

use App\Services\RentalDiagnostic as Diagnostic;
use App\Services\RentalPaymentProof as Proof;
use App\Services\RentalStorage as Storage;

$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$log = tempnam(sys_get_temp_dir(), 'rentals-log-');
if ($log === false) { throw new RuntimeException('QA log unavailable.'); }
$oldLog = ini_get('error_log');
ini_set('error_log', $log);
$files = [];
try {
    Diagnostic::context('GET', '/web-dev/3am-web/rentals/admin/proof/2?secret=do-not-log',
        App\Controllers\Rentals\RentalAdminController::class, 'viewProof');
    Diagnostic::trace('admin-proof', 'start', ['order_id' => 2]);
    $assert(filesize($log) === 0, 'Successful stage logging must remain buffered.');
    $directory = Storage::directory('payment', 'proof-upload');
    $name = bin2hex(random_bytes(16)) . '.png';
    $file = $directory . '/' . $name;
    $files[] = $file;
    $nonce = random_bytes(12);
    $tag = '';
    $plain = 'private-proof-bytes-do-not-log';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash_hmac('sha256', '3am-rentals-proof-v1', config('app.key'), true), OPENSSL_RAW_DATA, $nonce, $tag);
    file_put_contents($file, '3AMPROOF1' . $nonce . $tag . $cipher);
    $reference = 'micro/payment/' . $name;
    $assert(Proof::response($reference, 'admin-proof')->body() === $plain, 'Configured key failed.');
    clearstatcache(true, $log);
    $assert(filesize($log) === 0, 'Successful proof/storage requests were logged.');
    file_put_contents($file, '3AMPROOF1' . $nonce . $tag . str_repeat('x', strlen($cipher)));
    $assert(Proof::response($reference, 'admin-proof')->status() === 422, 'Invalid ciphertext status changed.');
    Diagnostic::exception('database', 'query', new PDOException('password=secret-sentinel customer@example.test'),
        ['APP_KEY' => config('app.key'), 'password' => 'secret-sentinel', 'mime' => "bad\nInjected", 'order_id' => 2]);
    $text = file_get_contents($log);
    $assert(str_contains($text, 'stage=decrypt ') && str_contains($text, 'decrypt=failed response_status=422')
        && str_contains($text, 'storage_source=external') && str_contains($text, 'file_readable=yes')
        && str_contains($text, 'app_key=configured app_key_length=valid'), 'Proof stages are incomplete.');
    preg_match_all('/\[req:([a-f0-9]{8})\]/', $text, $ids);
    $assert(count(array_unique($ids[1])) === 1, 'One request used multiple correlation IDs.');
    foreach ([config('app.key'), $plain, 'secret-sentinel', 'customer@example.test', $name, 'do-not-log', 'Injected'] as $secret) {
        $assert(!str_contains($text, $secret), 'Secret, raw path or arbitrary text leaked into diagnostics.');
    }
    $assert(str_contains($text, 'route=/rentals/admin/proof/:id') && str_contains($text, 'action=viewProof'), 'Safe routing context missing.');
    $assert(Proof::response('micro/payment/' . str_repeat('a', 32) . '.png', 'admin-proof')->status() === 404, 'Missing-file status changed.');
    file_put_contents($file, '3AMPROOF1short');
    $assert(Proof::response($reference, 'admin-proof')->status() === 422, 'Truncated proof status changed.');
    file_put_contents($file, 'invalid');
    $assert(Proof::response($reference, 'admin-proof')->status() === 404, 'Invalid-format status changed.');
    $savedKey = $_ENV['APP_KEY'];
    foreach (['' => 'missing', 'short-key' => 'configured', bin2hex(random_bytes(32)) => 'configured'] as $testKey => $state) {
        $_ENV['APP_KEY'] = $testKey;
        App\Core\Container::instance()->loadConfig(BASE_PATH . '/config');
        file_put_contents($file, '3AMPROOF1' . $nonce . $tag . $cipher);
        try {
            $result = Proof::response($reference, 'admin-proof');
            $assert(strlen($testKey) >= 32 && $result->status() === 422, 'Wrong key did not fail authentication.');
        } catch (RuntimeException $exception) {
            $assert(strlen($testKey) < 32, 'Valid key unexpectedly rejected.');
        }
    }
    $_ENV['APP_KEY'] = $savedKey;
    $blocker = $root . '/not-a-directory';
    file_put_contents($blocker, '');
    $files[] = $blocker;
    $_ENV['RENTALS_STORAGE_ROOT'] = $blocker;
    App\Core\Container::instance()->loadConfig(BASE_PATH . '/config');
    try {
        Storage::directory('payment', 'proof-upload');
        $assert(false, 'Invalid storage directory unexpectedly accepted.');
    } catch (RuntimeException $exception) {
        $assert(str_contains(file_get_contents($log), 'mkdir=failed directory_exists=no'), 'Storage mkdir failure stage missing.');
    }
    $_ENV['RENTALS_STORAGE_ROOT'] = $root;
    App\Core\Container::instance()->loadConfig(BASE_PATH . '/config');
    $text = file_get_contents($log);
    $assert(str_contains($text, 'app_key=missing app_key_length=invalid')
        && str_contains($text, 'app_key=configured app_key_length=invalid'), 'Missing versus short APP_KEY was not distinguished.');
    // Native fatal path reuses bootstrap shutdown handling; no second handler.
    $fatalScript = tempnam(sys_get_temp_dir(), 'rentals-fatal-');
    $files[] = $fatalScript;
    $bootstrap = var_export(BASE_PATH . '/bootstrap.php', true);
    file_put_contents($fatalScript, '<?php $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/rentals/admin/proof/2?secret=secret-sentinel"; $_ENV["APP_ENV"]="production"; $_ENV["APP_DEBUG"]="false"; require ' . $bootstrap . '; ini_set("error_log", $argv[1]); throw new RuntimeException("secret-sentinel");');
    $process = proc_open([PHP_BINARY, $fatalScript, $log], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $assert(is_resource($process), 'Fatal diagnostic subprocess could not start.');
    $fatalOutput = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $fatalExit = proc_close($process);
    $assert($fatalExit !== 0 && str_contains(file_get_contents($log), 'stage=fatal')
        && !str_contains($fatalOutput, 'secret-sentinel') && !str_contains(file_get_contents($log), 'secret-sentinel'),
        'Native fatal handler leaked raw error details or failed to log.');
    // Read-only mode suppresses explicit logging, even on failures.
    define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
    $before = file_get_contents($log);
    Proof::response(null, 'admin-proof');
    Diagnostic::failure('test', 'read-only', ['error' => 'unexpected']);
    $assert(file_get_contents($log) === $before, 'Read-only diagnostics wrote to a log.');
    echo "PASS: bounded failure traces, request correlation, secret redaction, proof/key/storage failure stages/statuses, quiet success and read-only logging.\n";
} finally {
    ini_set('error_log', (string) $oldLog);
    foreach ($files as $file) { @unlink($file); }
    @unlink($root . '/payment/.htaccess');
    @rmdir($root . '/payment');
    @rmdir($root);
    @unlink($log);
}
