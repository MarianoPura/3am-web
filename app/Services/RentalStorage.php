<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Physical storage outside the checkout; database references stay portable. */
final class RentalStorage
{
    private const FILE = '#^micro/(?:rentals/products/[a-f0-9]{32}\.(?:jpg|png|webp)|payment/(?:[a-f0-9]{32}\.(?:jpg|png|webp|pdf)|qr/[a-f0-9]{32}\.(?:jpg|png|webp)))$#D';

    public static function root(): string
    {
        $root = rtrim(str_replace('\\', '/', (string) config('rentals.storage_root')), '/');
        if (!preg_match('#^(?:/|[A-Za-z]:/)#', $root) || str_contains($root, '/../')) {
            RentalDiagnostic::failure('storage', 'root', ['storage_root_resolved' => false, 'error' => 'configuration']);
            throw new RuntimeException('Rentals storage needs an absolute directory outside the project.');
        }
        $project = rtrim(str_replace('\\', '/', BASE_PATH), '/');
        if (strcasecmp($root, $project) === 0 || str_starts_with(strtolower($root), strtolower($project) . '/')) {
            RentalDiagnostic::failure('storage', 'root', ['storage_root_resolved' => false, 'error' => 'configuration']);
            throw new RuntimeException('Rentals storage must be outside the project directory.');
        }
        return $root;
    }

    public static function directory(string $relative, string $flow = 'storage'): string
    {
        if (!in_array($relative, ['rentals/products', 'payment', 'payment/qr'], true)) {
            throw new RuntimeException('Unknown Rentals storage directory.');
        }
        $directory = self::root() . '/' . $relative;
        $existed = is_dir($directory);
        RentalDiagnostic::trace($flow, 'directory', ['storage_root_resolved' => true,
            'storage_root' => RentalDiagnostic::fingerprint(self::root()), 'storage_directory' => $relative,
            'directory_exists' => is_dir($directory), 'mkdir' => is_dir($directory) ? 'not-needed' : 'pending']);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            RentalDiagnostic::failure($flow, 'mkdir', ['mkdir' => 'failed', 'directory_exists' => false]);
            throw new RuntimeException('Rentals upload storage is not writable. Ask the server administrator to give the PHP worker access to the external micro directory.');
        }
        RentalDiagnostic::trace($flow, 'mkdir', ['mkdir' => $existed ? 'not-needed' : 'success', 'directory_exists' => true]);
        if (!is_writable($directory)) {
            RentalDiagnostic::failure($flow, 'directory-permissions', ['directory_exists' => true, 'directory_writable' => false]);
            throw new RuntimeException('Rentals upload storage is not writable. Ask the server administrator to give the PHP worker access to the external micro directory.');
        }
        // Public images are served through application routes. Keep direct
        // access to storage denied, including when micro is under a web root.
        $guard = $directory . '/.htaccess';
        RentalDiagnostic::trace($flow, 'directory-ready', ['directory_exists' => true, 'directory_writable' => true,
            'deny_guard' => is_file($guard) ? 'ok' : 'pending']);
        if (!is_file($guard) && @file_put_contents($guard, "Require all denied\nOptions -Indexes\n", LOCK_EX) === false) {
            RentalDiagnostic::failure($flow, 'deny-guard', ['deny_guard' => 'failed']);
            throw new RuntimeException('Rentals storage protection could not be created. Check external directory permissions.');
        }
        RentalDiagnostic::trace($flow, 'storage-ready', ['deny_guard' => 'ok']);
        return $directory;
    }

    public static function path(?string $reference, string $flow = 'storage-read'): ?string
    {
        $valid = $reference !== null && preg_match(self::FILE, $reference) === 1;
        RentalDiagnostic::trace($flow, 'reference', ['reference_valid' => $valid]);
        if (!$valid) { return null; }
        $external = self::root() . '/' . substr($reference, strlen('micro/'));
        // Existing images/proofs remain readable while the server administrator
        // copies legacy uploads. New writes always use the external directory.
        $legacy = BASE_PATH . '/' . $reference;
        $path = is_file($external) ? $external : (is_file($legacy) ? $legacy : null);
        RentalDiagnostic::trace($flow, 'file-location', ['storage_root_resolved' => true,
            'storage_root' => RentalDiagnostic::fingerprint(self::root()), 'external_exists' => is_file($external),
            'legacy_exists' => is_file($legacy), 'storage_source' => $path === null ? 'none' : ($path === $external ? 'external' : 'legacy'),
            'file_exists' => $path !== null, 'file_readable' => $path !== null && is_readable($path)]);
        return $path;
    }
}
