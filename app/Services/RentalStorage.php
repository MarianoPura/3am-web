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
            throw new RuntimeException('Rentals storage needs an absolute directory outside the project.');
        }
        $project = rtrim(str_replace('\\', '/', BASE_PATH), '/');
        if (strcasecmp($root, $project) === 0 || str_starts_with(strtolower($root), strtolower($project) . '/')) {
            throw new RuntimeException('Rentals storage must be outside the project directory.');
        }
        return $root;
    }

    public static function directory(string $relative): string
    {
        if (!in_array($relative, ['rentals/products', 'payment', 'payment/qr'], true)) {
            throw new RuntimeException('Unknown Rentals storage directory.');
        }
        $directory = self::root() . '/' . $relative;
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Rentals storage mkdir failed: ' . $directory);
            throw new RuntimeException('Rentals upload storage is not writable. Ask the server administrator to give the PHP worker access to the external micro directory.');
        }
        if (!is_writable($directory)) {
            error_log('Rentals storage directory not writable: ' . $directory);
            throw new RuntimeException('Rentals upload storage is not writable. Ask the server administrator to give the PHP worker access to the external micro directory.');
        }
        // Public images are served through application routes. Keep direct
        // access to storage denied, including when micro is under a web root.
        $guard = $directory . '/.htaccess';
        if (!is_file($guard) && @file_put_contents($guard, "Require all denied\nOptions -Indexes\n", LOCK_EX) === false) {
            error_log('Rentals storage deny-guard write failed: ' . $guard);
            throw new RuntimeException('Rentals storage protection could not be created. Check external directory permissions.');
        }
        return $directory;
    }

    public static function path(?string $reference): ?string
    {
        if ($reference === null || preg_match(self::FILE, $reference) !== 1) { return null; }
        $external = self::root() . '/' . substr($reference, strlen('micro/'));
        if (is_file($external)) { return $external; }
        // Existing images/proofs remain readable while the server administrator
        // copies legacy uploads. New writes always use the external directory.
        $legacy = BASE_PATH . '/' . $reference;
        return is_file($legacy) ? $legacy : null;
    }
}
