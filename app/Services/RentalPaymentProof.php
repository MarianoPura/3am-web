<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use RuntimeException;

/** Private proof storage in the deployed web root's micro/payment directory. */
final class RentalPaymentProof
{
    private const LEGACY_GUARD = '<?php http_response_code(404); exit; __halt_compiler();';
    private const MAGIC = '3AMPROOF1';
    private const TYPES = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
    ];

    public static function store(?array $upload): string
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose a JPG, PNG, WebP or PDF payment proof.');
        }
        $source = $upload['tmp_name'] ?? null;
        $size = (int) ($upload['size'] ?? 0);
        if (!is_string($source) || !is_uploaded_file($source) || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Payment proof must be a valid file no larger than 5 MB.');
        }
        $extension = self::TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($source)] ?? null;
        if ($extension === null) { throw new RuntimeException('Use a JPG, PNG, WebP or PDF payment proof.'); }
        $directory = BASE_PATH . '/micro/payment';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Payment proof storage is unavailable.');
        }
        $plain = @file_get_contents($source);
        if ($plain === false || strlen($plain) !== $size) { throw new RuntimeException('Payment proof could not be read.'); }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) { throw new RuntimeException('Payment proof could not be secured.'); }
        $relative = 'micro/payment/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $path = BASE_PATH . '/' . $relative;
        $stream = @fopen($path, 'xb');
        if ($stream === false) { throw new RuntimeException('Payment proof could not be saved.'); }
        $closed = false;
        try {
            $content = self::MAGIC . $nonce . $tag . $ciphertext;
            if (fwrite($stream, $content) !== strlen($content)) { throw new RuntimeException('Payment proof could not be saved.'); }
            fclose($stream);
            $closed = true;
            @chmod($path, 0600);
        } catch (\Throwable $e) {
            if (!$closed) { fclose($stream); }
            @unlink($path);
            throw $e;
        }
        return $relative;
    }

    public static function path(?string $reference): ?string
    {
        if ($reference !== null && preg_match('#^micro/payment/[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$#', $reference) === 1) {
            $path = BASE_PATH . '/' . $reference;
        } elseif ($reference !== null && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)\.php$/', $reference) === 1) {
            $path = BASE_PATH . '/storage/rentals/payment-proofs/' . $reference;
        } else { return null; }
        return is_file($path) ? $path : null;
    }

    public static function remove(?string $reference): void
    {
        $path = self::path($reference);
        if ($path !== null) { @unlink($path); }
    }

    public static function response(?string $reference): Response
    {
        $path = self::path($reference);
        if ($path === null) { return Response::notFound(); }
        $bytes = @file_get_contents($path);
        if ($bytes === false) { return Response::notFound(); }
        if (str_starts_with($bytes, self::LEGACY_GUARD)) {
            $plain = substr($bytes, strlen(self::LEGACY_GUARD));
        } elseif (str_starts_with($bytes, self::MAGIC)) {
            $offset = strlen(self::MAGIC);
            $plain = openssl_decrypt(substr($bytes, $offset + 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA,
                substr($bytes, $offset, 12), substr($bytes, $offset + 12, 16));
            if ($plain === false) { return Response::notFound(); }
        } else { return Response::notFound(); }
        $extension = preg_match('/\.(jpg|png|webp|pdf)(?:\.php)?$/', (string) $reference, $match) ? $match[1] : '';
        $mime = array_search($extension, self::TYPES, true);
        return Response::make($plain)
            ->withHeader('Content-Type', $mime === false ? 'application/octet-stream' : $mime)
            ->withHeader('Content-Disposition', 'inline; filename="payment-proof.' . $extension . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')->noCache();
    }

    private static function key(): string
    {
        if (!in_array(config('app.env'), ['local', 'testing'], true)) {
            $appKey = (string) config('app.key');
            if (strlen($appKey) < 32) {
                throw new RuntimeException('A stable application key is required for payment proof storage.');
            }
            return hash_hmac('sha256', '3am-rentals-proof-v1', $appKey, true);
        }
        $path = BASE_PATH . '/storage/rentals/payment-key.php';
        if (is_file($path)) {
            $hex = require $path;
            if (!is_string($hex) || preg_match('/^[a-f0-9]{64}$/', $hex) !== 1) {
                throw new RuntimeException('Payment proof key is invalid.');
            }
            return hex2bin($hex);
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Payment proof key storage is unavailable.');
        }
        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            if (is_file($path)) { return self::key(); }
            throw new RuntimeException('Payment proof key storage is unavailable.');
        }
        $hex = bin2hex(random_bytes(32));
        fwrite($stream, '<?php return ' . var_export($hex, true) . ';');
        fclose($stream);
        @chmod($path, 0600);
        return hex2bin($hex);
    }
}
