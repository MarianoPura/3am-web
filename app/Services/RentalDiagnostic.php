<?php
declare(strict_types=1);

namespace App\Services;

use Throwable;

/** Small failure-only logger. Never pass form bodies, SQL, secrets or file bytes. */
final class RentalDiagnostic
{
    private static ?string $id = null;
    private static array $context = [];
    private static array $pending = [];
    private static array $reported = [];
    private static bool $writing = false;
    private const ROUTE_WORDS = ['rentals', 'admin', 'items', 'categories', 'payments', 'orders',
        'proof', 'product-image', 'payment-qr', 'cart', 'add', 'update', 'remove', 'clear', 'availability',
        'blackouts', 'toggle', 'edit', 'new', 'checkout', 'account', 'login', 'register', 'logout',
        'order-status', 'analytics', 'sales-report', 'payment-report', 'customers', 'services',
        'how-to-rent', 'support', 'reserve', 'review', 'micro', 'payment', 'products'];
    private const VALUES = ['yes', 'no', 'ok', 'failed', 'success', 'pending', 'skipped', 'not-needed',
        'present', 'missing', 'invalid', 'valid', 'configured', 'external', 'legacy', 'none',
        'recognized', 'encrypted', 'guarded', 'unknown', 'guest', 'customer', 'admin', 'superadmin',
        'allowed', 'denied', 'database', 'validation', 'duplicate', 'configuration', 'unexpected',
        'warning', 'fatal', 'php', 'product', 'qr', 'payment', 'rentals/products', 'payment/qr',
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/octet-stream'];
    private const FIELDS = ['item_id', 'order_id', 'record_id', 'requested_quantity', 'available_quantity',
        'upload_error', 'upload_bytes', 'result_days', 'result_count', 'response_status', 'severity', 'line',
        'auth', 'role', 'csrf', 'db_path', 'db_path_saved', 'order_saved', 'db_operation', 'validation',
        'duplicate_name', 'qr_column', 'qr_path', 'route_generated', 'storage_root_resolved',
        'storage_root', 'storage_source', 'storage_directory', 'directory_exists', 'directory_writable',
        'mkdir', 'deny_guard', 'move_uploaded_file', 'file_exists', 'external_exists', 'legacy_exists',
        'file_readable', 'temp_uploaded', 'temp_readable', 'size_valid', 'format', 'app_key',
        'app_key_length', 'decrypt', 'encrypt', 'file_create', 'file_write', 'filename_valid',
        'reference_valid', 'mime', 'date_range_valid', 'reservation_query', 'blackout_query',
        'availability_check', 'reservation_conflict', 'block_operation', 'error', 'exception', 'sqlstate', 'source', 'item_found', 'start_date', 'end_date'];

    public static function readOnly(): bool
    {
        return defined('RENTALS_DIAGNOSTIC_READ_ONLY') && RENTALS_DIAGNOSTIC_READ_ONLY === true;
    }

    public static function context(string $method, string $path, ?string $controller = null, ?string $action = null): void
    {
        $parts = explode('/', (string) parse_url($path, PHP_URL_PATH));
        $start = array_search('rentals', $parts, true);
        if ($start === false) { $start = array_search('micro', $parts, true); }
        $parts = $start === false ? [] : array_slice($parts, $start, 12);
        self::$context = [
            'method' => in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'CLI'], true) ? $method : 'OTHER',
            'route' => $parts === [] ? '/other' : '/' . implode('/', array_map(static fn ($part) =>
                in_array($part, self::ROUTE_WORDS, true) ? $part : (ctype_digit($part) ? ':id' : ':value'), $parts)),
        ];
        if ($controller !== null && preg_match('/^[A-Za-z\\\\]+$/D', $controller)) { self::$context['controller'] = $controller; }
        if ($action !== null && preg_match('/^[A-Za-z]+$/D', $action)) { self::$context['action'] = $action; }
    }

    public static function active(): bool
    {
        return str_starts_with(self::$context['route'] ?? '', '/rentals')
            || str_starts_with(self::$context['route'] ?? '', '/micro');
    }

    public static function trace(string $flow, string $stage, array $fields = []): void
    {
        if (self::readOnly()) { return; }
        self::$pending[] = [$flow, $stage, self::safe($fields)];
        if (count(self::$pending) > 64) { array_shift(self::$pending); }
    }

    public static function failure(string $flow, string $stage, array $fields = []): void
    {
        if (self::readOnly() || self::$writing) { return; }
        $fields = self::safe($fields);
        // A catalog may validate the same missing image several times while rendering.
        if ($flow === 'image-reference') {
            $signature = hash('sha256', $flow . $stage . serialize($fields));
            if (isset(self::$reported[$signature])) { self::$pending = []; return; }
            self::$reported[$signature] = true;
            if (count(self::$reported) > 64) { array_shift(self::$reported); }
        }
        self::$writing = true;
        try {
            foreach (self::$pending as [$pendingFlow, $pendingStage, $pendingFields]) {
                self::write($pendingFlow, $pendingStage, $pendingFields);
            }
            self::$pending = [];
            self::write($flow, $stage, $fields);
        } finally { self::$writing = false; }
    }

    public static function exception(string $flow, string $stage, Throwable $exception, array $fields = []): void
    {
        $cause = $exception;
        while ($cause->getPrevious() !== null) { $cause = $cause->getPrevious(); }
        $fields['exception'] = get_class($cause);
        $fields['source'] = self::source($cause->getFile());
        $fields['line'] = $cause->getLine();
        $fields['error'] = $cause instanceof \PDOException ? 'database' :
            ($exception instanceof \InvalidArgumentException ? 'validation' : 'unexpected');
        if ($cause instanceof \PDOException) { $fields['sqlstate'] = (string) ($cause->errorInfo[0] ?? $cause->getCode()); }
        self::failure($flow, $stage, $fields);
    }

    public static function source(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $root = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/';
        return str_starts_with($file, $root) ? substr($file, strlen($root)) : 'unknown';
    }

    public static function fingerprint(string $path): string
    {
        return substr(hash('sha256', str_replace('\\', '/', $path)), 0, 12);
    }

    private static function safe(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, self::FIELDS, true)) { continue; }
            if (is_bool($value)) { $result[$key] = $value ? 'yes' : 'no'; }
            elseif (is_int($value)) { $result[$key] = $value; }
            elseif (is_string($value) && (in_array($value, self::VALUES, true)
                || ($key === 'storage_root' && preg_match('/^[a-f0-9]{12}$/D', $value))
                || (in_array($key, ['start_date', 'end_date'], true) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value))
                || ($key === 'source' && preg_match('#^(?:app/[A-Za-z0-9/_-]+|bootstrap|index)\.php$#D', $value))
                || ($key === 'exception' && preg_match('/^[A-Za-z\\\\]+$/D', $value))
                || ($key === 'sqlstate' && preg_match('/^[A-Z0-9]{5}$/D', $value)))) { $result[$key] = $value; }
        }
        return $result;
    }

    private static function write(string $flow, string $stage, array $fields): void
    {
        // Flow and stage are code-owned; reject accidental user-controlled strings.
        if (!preg_match('/^[a-z][a-z0-9-]{0,48}$/D', $flow) || !preg_match('/^[a-z][a-z0-9-]{0,48}$/D', $stage)) { return; }
        self::$id ??= bin2hex(random_bytes(4));
        $parts = [];
        foreach (['stage' => $stage] + self::$context + $fields as $key => $value) { $parts[] = $key . '=' . $value; }
        @error_log('[Rentals][req:' . self::$id . '][' . $flow . '] ' . implode(' ', $parts));
    }
}
