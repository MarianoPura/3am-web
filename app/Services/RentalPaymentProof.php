<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use RuntimeException;

/** Encrypted payment proofs in external micro/payment storage. */
final class RentalPaymentProof
{
    private const LEGACY_GUARD = '<?php http_response_code(404); exit; __halt_compiler();';
    private const MAGIC = '3AMPROOF1';
    private const TYPES = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
    ];

    public static function maxUploadBytes(): int
    {
        return RentalManagedImage::maxUploadBytes();
    }

    public static function store(?array $upload): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($upload === null || $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Payment proof exceeds the upload limit. Choose a smaller file.',
                UPLOAD_ERR_PARTIAL => 'Payment proof upload was interrupted. Please try again.',
                default => 'Choose a JPG, PNG, WebP or PDF payment proof.',
            });
        }
        $source = $upload['tmp_name'] ?? null;
        $size = (int) ($upload['size'] ?? 0);
        if (!is_string($source) || !is_uploaded_file($source) || $size < 1 || $size > self::maxUploadBytes()) {
            throw new RuntimeException('Choose a valid payment proof within the upload limit shown on the form.');
        }
        if (!class_exists(\finfo::class) || !function_exists('openssl_encrypt')) {
            if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals payment proof upload requires PHP Fileinfo and OpenSSL.'); }
            throw new RuntimeException('Payment proof uploads are unavailable right now. Please try again later.');
        }
        $extension = self::TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($source)] ?? null;
        if ($extension === null) { throw new RuntimeException('Use a JPG, PNG, WebP or PDF payment proof.'); }
        try { $directory = RentalStorage::directory('payment'); }
        catch (RuntimeException $e) {
            if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals operation could not be completed.'); }
            throw new RuntimeException('Payment proof could not be saved right now. Please try again later.', 0, $e);
        }
        $plain = @file_get_contents($source);
        if ($plain === false || strlen($plain) !== $size) { throw new RuntimeException('Payment proof could not be read.'); }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) { throw new RuntimeException('Payment proof could not be secured.'); }
        $relative = 'micro/payment/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $directory . '/' . basename($relative);
        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals payment proof file create failed in: ' . $directory); }
            throw new RuntimeException('Payment proof could not be saved.');
        }
        $closed = false;
        try {
            $content = self::MAGIC . $nonce . $tag . $ciphertext;
            if (fwrite($stream, $content) !== strlen($content)) {
                if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals payment proof file write failed in: ' . $directory); }
                throw new RuntimeException('Payment proof could not be saved.');
            }
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
            $path = RentalStorage::path($reference);
        } elseif ($reference !== null && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)\.php$/', $reference) === 1) {
            $path = BASE_PATH . '/storage/rentals/payment-proofs/' . $reference;
        } else { return null; }
        return $path !== null && is_file($path) ? $path : null;
    }

    public static function remove(?string $reference): void
    {
        $path = self::path($reference);
        if ($path !== null) { @unlink($path); }
    }

    public static function response(?string $reference): Response
    {
        $path = self::path($reference);
        if ($path === null) { return Response::notFound()->noCache(); }
        $bytes = @file_get_contents($path);
        if ($bytes === false) { return Response::notFound()->noCache(); }
        if (str_starts_with($bytes, self::LEGACY_GUARD)) {
            $plain = substr($bytes, strlen(self::LEGACY_GUARD));
        } elseif (str_starts_with($bytes, self::MAGIC)) {
            $offset = strlen(self::MAGIC);
            if (strlen($bytes) < $offset + 28) {
                if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals payment proof could not be decrypted: encrypted file is incomplete.'); }
                return Response::text('Payment proof could not be opened.', 422)->noCache();
            }
            $plain = openssl_decrypt(substr($bytes, $offset + 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA,
                substr($bytes, $offset, 12), substr($bytes, $offset + 12, 16));
            if ($plain === false) {
                if (!defined('RENTALS_DIAGNOSTIC_READ_ONLY') || RENTALS_DIAGNOSTIC_READ_ONLY !== true) { error_log('Rentals payment proof could not be decrypted: authentication failed.'); }
                return Response::text('Payment proof could not be opened.', 422)->noCache();
            }
        } else { return Response::notFound()->noCache(); }
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
        if (defined('RENTALS_DIAGNOSTIC_READ_ONLY') && RENTALS_DIAGNOSTIC_READ_ONLY === true) {
            throw new RuntimeException('Local proof key is absent; read-only check cannot create it.');
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
