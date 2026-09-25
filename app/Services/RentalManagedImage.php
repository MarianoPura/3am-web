<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Public product and payment QR images; only feature-owned files may be removed. */
final class RentalManagedImage
{
    private const DIRECTORIES = [
        'product' => 'micro/rentals/products',
        'qr' => 'micro/payment/qr',
    ];
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function store(?array $upload, string $kind): ?string
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!isset(self::DIRECTORIES[$kind]) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Image upload failed. Choose a JPG, PNG or WebP under 5 MB.');
        }
        $source = $upload['tmp_name'] ?? null;
        $size = (int) ($upload['size'] ?? 0);
        if (!is_string($source) || !is_uploaded_file($source) || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Choose a valid image under 5 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $extension = self::TYPES[$mime] ?? null;
        if ($extension === null || @getimagesize($source) === false) {
            throw new \InvalidArgumentException('Choose a JPG, PNG or WebP image.');
        }
        $directory = BASE_PATH . '/' . self::DIRECTORIES[$kind];
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Image storage is unavailable.');
        }
        $relative = self::DIRECTORIES[$kind] . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!@move_uploaded_file($source, BASE_PATH . '/' . $relative)) {
            throw new RuntimeException('Image could not be saved.');
        }
        @chmod(BASE_PATH . '/' . $relative, 0644);
        return $relative;
    }

    public static function remove(?string $path, string $kind): void
    {
        if (self::publicPath($path, $kind) !== null) { @unlink(BASE_PATH . '/' . $path); }
    }

    public static function publicPath(?string $path, string $kind): ?string
    {
        if (!isset(self::DIRECTORIES[$kind]) || $path === null ||
            preg_match('#^' . preg_quote(self::DIRECTORIES[$kind], '#') . '/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $path) !== 1
            || !is_file(BASE_PATH . '/' . $path)) { return null; }
        return $path;
    }
}
