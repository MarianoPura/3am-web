<?php
declare(strict_types=1);
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);

// Read-only. Run with the same PHP user/configuration as the live web worker.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "PHP 8.1 or newer is required.\n"); exit(1); }
try {
require dirname(__DIR__) . '/bootstrap.php';

function describeDirectory(string $label, string $path): void
{
    $exists = is_dir($path);
    echo $label . ': ' . $path . "\n";
    echo '  exists=' . ($exists ? 'yes' : 'no')
        . ' writable=' . ($exists && is_writable($path) ? 'yes' : 'no') . "\n";
    if (!$exists) {
        $parent = dirname($path);
        while (!is_dir($parent) && dirname($parent) !== $parent) { $parent = dirname($parent); }
        echo '  nearest existing parent=' . $parent
            . ' writable=' . (is_dir($parent) && is_writable($parent) ? 'yes' : 'no') . "\n";
    }
}

echo 'PHP SAPI: ' . PHP_SAPI . " (CLI results may differ from PHP-FPM/Apache)\n";
if (function_exists('posix_geteuid')) { echo 'Effective UID: ' . posix_geteuid() . "\n"; }
echo 'Project root: ' . BASE_PATH . "\n";
echo 'Loaded php.ini: ' . (php_ini_loaded_file() ?: '(none)') . "\n";
echo 'Working directory: ' . getcwd() . "\n";
foreach (['file_uploads', 'upload_tmp_dir', 'upload_max_filesize', 'post_max_size', 'max_file_uploads', 'open_basedir'] as $setting) {
    $value = (string) ini_get($setting);
    echo $setting . ': ' . ($value === '' ? '(unset)' : $value) . "\n";
}
$temporary = (string) ini_get('upload_tmp_dir');
describeDirectory('PHP upload temporary directory', $temporary !== '' ? $temporary : sys_get_temp_dir());

try {
    $root = \App\Services\RentalStorage::root();
} catch (\RuntimeException $exception) {
    fwrite(STDERR, 'Storage root invalid: ' . $exception->getMessage() . "\n");
    exit(1);
}
describeDirectory('Configured external storage root', $root);
echo 'Storage root fingerprint: ' . \App\Services\RentalDiagnostic::fingerprint($root) . "\n";
foreach (['rentals/products', 'payment', 'payment/qr'] as $relative) {
    $directory = $root . '/' . $relative;
    describeDirectory($relative, $directory);
    $guard = $directory . '/.htaccess';
    echo '  deny guard=' . (is_file($guard) ? 'present' : 'missing') . "\n";
}
echo "No directories or files were created. Check these results under the web worker before changing ownership or permissions.\n";

} catch (\Throwable $exception) {
    fwrite(STDERR, 'Diagnostic could not complete: ' . get_class($exception) . " (no secrets or data printed).\n");
    exit(1);
}
