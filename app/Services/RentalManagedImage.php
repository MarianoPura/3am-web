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

    public static function maxRequestBytes(): int
    {
        return self::iniBytes((string) ini_get('post_max_size'));
    }

    /** Honor hosting limits as well as the application's 5 MB ceiling. */
    public static function maxUploadBytes(): int
    {
        $limits = [5 * 1024 * 1024];
        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $post = self::maxRequestBytes();
        if ($upload > 0) { $limits[] = $upload; }
        // Leave room for the remaining product fields and multipart headers.
        if ($post > 0) { $limits[] = max(1, $post - 64 * 1024); }
        return min($limits);
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^([0-9]+)([KMG]?)$/iD', $value, $parts)) { return 0; }
        $factor = match (strtoupper($parts[2])) { 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1 };
        return (int) $parts[1] * $factor;
    }

    public static function store(?array $upload, string $kind): ?string
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!isset(self::DIRECTORIES[$kind])) { throw new RuntimeException('Unknown image storage type.'); }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image: the file exceeds this server\'s upload limit. Choose a smaller JPG, PNG or WebP.',
                UPLOAD_ERR_PARTIAL => 'Image: the upload was interrupted. Reselect the image and try again.',
                default => 'Image: the file could not be received. Try again or save without an image.',
            });
        }
        $source = $upload['tmp_name'] ?? null;
        $size = (int) ($upload['size'] ?? 0);
        if (!is_string($source) || !is_uploaded_file($source) || $size < 1 || $size > self::maxUploadBytes()) {
            throw new \InvalidArgumentException('Image: choose a valid image within the upload limit shown on the form.');
        }
        if (!class_exists(\finfo::class)) {
            error_log('Rentals image upload unavailable: PHP Fileinfo extension is missing.');
            throw new \InvalidArgumentException('Image uploads are unavailable right now. You can save without an image and add it later.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $extension = self::TYPES[$mime] ?? null;
        if ($extension === null || @getimagesize($source) === false) {
            throw new \InvalidArgumentException('Choose a JPG, PNG or WebP image.');
        }
        try { $directory = RentalStorage::directory(substr(self::DIRECTORIES[$kind], strlen('micro/'))); }
        catch (RuntimeException $e) {
            error_log('Rentals image upload storage failed: ' . $e->getMessage());
            throw new \InvalidArgumentException('The image could not be saved right now. You can save without an image and add it later.', 0, $e);
        }
        $relative = self::DIRECTORIES[$kind] . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!@move_uploaded_file($source, $directory . '/' . basename($relative))) {
            error_log('Rentals image upload failed: move_uploaded_file could not write to ' . $directory);
            throw new \InvalidArgumentException('The image could not be saved right now. You can save without an image and add it later.');
        }
        @chmod($directory . '/' . basename($relative), 0640);
        return $relative;
    }

    public static function remove(?string $path, string $kind): void
    {
        if (self::publicPath($path, $kind) !== null) { @unlink((string) RentalStorage::path($path)); }
    }

    public static function publicPath(?string $path, string $kind): ?string
    {
        if (!isset(self::DIRECTORIES[$kind]) || $path === null ||
            preg_match('#^' . preg_quote(self::DIRECTORIES[$kind], '#') . '/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $path) !== 1
            || RentalStorage::path($path) === null) { return null; }
        return $path;
    }
}
