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
        $flow = 'proof-upload';
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        RentalDiagnostic::trace($flow, 'received', ['upload_error' => $error]);
        if ($upload === null || $error !== UPLOAD_ERR_OK) {
            RentalDiagnostic::failure($flow, 'php-upload', ['upload_error' => $error]);
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Payment proof exceeds the upload limit. Choose a smaller file.',
                UPLOAD_ERR_PARTIAL => 'Payment proof upload was interrupted. Please try again.',
                default => 'Choose a JPG, PNG, WebP or PDF payment proof.',
            });
        }
        $source = $upload['tmp_name'] ?? null;
        $size = (int) ($upload['size'] ?? 0);
        RentalDiagnostic::trace($flow, 'upload-validation', ['upload_bytes' => $size, 'size_valid' => $size >= 1 && $size <= self::maxUploadBytes(),
            'temp_uploaded' => is_string($source) && is_uploaded_file($source), 'temp_readable' => is_string($source) && is_readable($source)]);
        if (!is_string($source) || !is_uploaded_file($source) || $size < 1 || $size > self::maxUploadBytes()) {
            RentalDiagnostic::failure($flow, 'upload-validation', ['validation' => 'failed']);
            throw new RuntimeException('Choose a valid payment proof within the upload limit shown on the form.');
        }
        if (!class_exists(\finfo::class) || !function_exists('openssl_encrypt')) {
            RentalDiagnostic::failure($flow, 'extensions', ['error' => 'configuration']);
            throw new RuntimeException('Payment proof uploads are unavailable right now. Please try again later.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $extension = self::TYPES[$mime] ?? null;
        RentalDiagnostic::trace($flow, 'mime', ['mime' => $extension === null ? 'unknown' : $mime]);
        if ($extension === null) { RentalDiagnostic::failure($flow, 'mime-validation', ['validation' => 'failed']); throw new RuntimeException('Use a JPG, PNG, WebP or PDF payment proof.'); }
        try { $directory = RentalStorage::directory('payment', $flow); }
        catch (RuntimeException $e) {
            RentalDiagnostic::exception($flow, 'storage', $e);
            throw new RuntimeException('Payment proof could not be saved right now. Please try again later.', 0, $e);
        }
        $plain = @file_get_contents($source);
        if ($plain === false || strlen($plain) !== $size) { RentalDiagnostic::failure($flow, 'temp-read', ['temp_readable' => false]); throw new RuntimeException('Payment proof could not be read.'); }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', self::key($flow), OPENSSL_RAW_DATA, $nonce, $tag);
        RentalDiagnostic::trace($flow, 'encrypt', ['encrypt' => $ciphertext === false ? 'failed' : 'success']);
        if ($ciphertext === false) { RentalDiagnostic::failure($flow, 'encrypt', ['encrypt' => 'failed']); throw new RuntimeException('Payment proof could not be secured.'); }
        $relative = 'micro/payment/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $directory . '/' . basename($relative);
        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            RentalDiagnostic::failure($flow, 'file-create', ['file_create' => 'failed']);
            throw new RuntimeException('Payment proof could not be saved.');
        }
        RentalDiagnostic::trace($flow, 'file-create', ['file_create' => 'success']);
        $closed = false;
        try {
            $content = self::MAGIC . $nonce . $tag . $ciphertext;
            if (fwrite($stream, $content) !== strlen($content)) {
                RentalDiagnostic::failure($flow, 'file-write', ['file_write' => 'failed']);
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
        RentalDiagnostic::trace($flow, 'file-saved', ['file_write' => 'success', 'file_exists' => is_file($path)]);
        return $relative;
    }

    public static function path(?string $reference, string $flow = 'payment-proof'): ?string
    {
        if ($reference !== null && preg_match('#^micro/payment/[a-f0-9]{32}\.(?:jpg|png|webp|pdf)$#', $reference) === 1) {
            $path = RentalStorage::path($reference, $flow);
        } elseif ($reference !== null && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|pdf)\.php$/', $reference) === 1) {
            $path = BASE_PATH . '/storage/rentals/payment-proofs/' . $reference;
            RentalDiagnostic::trace($flow, 'legacy-file', ['storage_source' => 'legacy', 'file_exists' => is_file($path), 'file_readable' => is_readable($path)]);
        } else { return null; }
        return $path !== null && is_file($path) ? $path : null;
    }

    public static function remove(?string $reference): void
    {
        $path = self::path($reference);
        if ($path !== null) { @unlink($path); }
    }

    public static function response(?string $reference, string $flow = 'payment-proof'): Response
    {
        RentalDiagnostic::trace($flow, 'proof-reference', ['db_path' => empty($reference) ? 'missing' : 'present']);
        $path = self::path($reference, $flow);
        if ($path === null) { RentalDiagnostic::failure($flow, 'file-missing', ['file_exists' => false, 'response_status' => 404]); return Response::notFound()->noCache(); }
        $bytes = @file_get_contents($path);
        if ($bytes === false) { RentalDiagnostic::failure($flow, 'file-read', ['file_readable' => false, 'response_status' => 404]); return Response::notFound()->noCache(); }
        RentalDiagnostic::trace($flow, 'format', ['file_readable' => true, 'format' => str_starts_with($bytes, self::MAGIC) ? 'encrypted' : (str_starts_with($bytes, self::LEGACY_GUARD) ? 'guarded' : 'invalid')]);
        if (str_starts_with($bytes, self::LEGACY_GUARD)) {
            $plain = substr($bytes, strlen(self::LEGACY_GUARD));
        } elseif (str_starts_with($bytes, self::MAGIC)) {
            $offset = strlen(self::MAGIC);
            if (strlen($bytes) < $offset + 28) {
                RentalDiagnostic::failure($flow, 'encrypted-length', ['format' => 'invalid', 'decrypt' => 'failed', 'response_status' => 422]);
                return Response::text('Payment proof could not be opened.', 422)->noCache();
            }
            $plain = openssl_decrypt(substr($bytes, $offset + 28), 'aes-256-gcm', self::key($flow), OPENSSL_RAW_DATA,
                substr($bytes, $offset, 12), substr($bytes, $offset + 12, 16));
            if ($plain === false) {
                RentalDiagnostic::failure($flow, 'decrypt', ['decrypt' => 'failed', 'response_status' => 422]);
                return Response::text('Payment proof could not be opened.', 422)->noCache();
            }
        } else { RentalDiagnostic::failure($flow, 'format', ['format' => 'invalid', 'response_status' => 404]); return Response::notFound()->noCache(); }
        $extension = preg_match('/\.(jpg|png|webp|pdf)(?:\.php)?$/', (string) $reference, $match) ? $match[1] : '';
        $mime = array_search($extension, self::TYPES, true);
        RentalDiagnostic::trace($flow, 'response', ['decrypt' => str_starts_with($bytes, self::MAGIC) ? 'success' : 'skipped', 'mime' => $mime === false ? 'application/octet-stream' : $mime, 'response_status' => 200]);
        return Response::make($plain)
            ->withHeader('Content-Type', $mime === false ? 'application/octet-stream' : $mime)
            ->withHeader('Content-Disposition', 'inline; filename="payment-proof.' . $extension . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')->noCache();
    }

    private static function key(string $flow = 'payment-proof'): string
    {
        if (!in_array(config('app.env'), ['local', 'testing'], true)) {
            $appKey = (string) config('app.key');
            RentalDiagnostic::trace($flow, 'key', ['app_key' => $appKey === '' ? 'missing' : 'configured', 'app_key_length' => strlen($appKey) >= 32 ? 'valid' : 'invalid']);
            if (strlen($appKey) < 32) {
                RentalDiagnostic::failure($flow, 'key-configuration', ['error' => 'configuration']);
                throw new RuntimeException('A stable application key is required for payment proof storage.');
            }
            return hash_hmac('sha256', '3am-rentals-proof-v1', $appKey, true);
        }
        RentalDiagnostic::trace($flow, 'local-key', ['app_key' => is_file(BASE_PATH . '/storage/rentals/payment-key.php') ? 'configured' : 'missing']);
        $path = BASE_PATH . '/storage/rentals/payment-key.php';
        if (is_file($path)) {
            $hex = require $path;
            if (!is_string($hex) || preg_match('/^[a-f0-9]{64}$/', $hex) !== 1) {
                throw new RuntimeException('Payment proof key is invalid.');
            }
            return hex2bin($hex);
        }
        if (RentalDiagnostic::readOnly()) { throw new RuntimeException('Local proof key is absent; read-only diagnostic cannot create it.'); }
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Payment proof key storage is unavailable.');
        }
        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            if (is_file($path)) { return self::key($flow); }
            throw new RuntimeException('Payment proof key storage is unavailable.');
        }
        $hex = bin2hex(random_bytes(32));
        fwrite($stream, '<?php return ' . var_export($hex, true) . ';');
        fclose($stream);
        @chmod($path, 0600);
        return hex2bin($hex);
    }
}
