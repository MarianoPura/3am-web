<?php
declare(strict_types=1);

// Local-only QA server. Simulates a mounted Apache application without .env edits.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$mount = rtrim((string) getenv('RENTALS_QA_MOUNT'), '/');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($mount !== '' && $path !== $mount && !str_starts_with($path, $mount . '/')) {
    http_response_code(404); exit;
}
$relative = $mount === '' ? $path : substr($path, strlen($mount));
$_ENV['APP_BASE_PATH'] = $mount;
$_ENV['APP_ENV'] = 'testing';
// Automated HTTP QA must never use credentials from the local .env to send mail.
$_ENV['MAIL_ENABLED'] = 'false';
$_ENV['APP_URL'] = 'http://127.0.0.1:' . (int) ($_SERVER['SERVER_PORT'] ?? 8000);
$_ENV['APP_DEBUG'] = 'false';
$_ENV['FORCE_HTTPS'] = 'false';
$_SERVER['SCRIPT_NAME'] = $mount . '/index.php';
if ($relative === '/_qa-session-error') {
    $_ENV['APP_ENV'] = 'production';
    $_ENV['APP_DEBUG'] = 'true';
    session_set_save_handler(new class extends SessionHandler {
        public function open(string $path, string $name): bool { return false; }
    }, true);
} elseif ($relative === '/_qa-error') {
    // Exercise the real production catch path with a failed read, including
    // APP_DEBUG accidentally enabled. Never create/drop/alter a database.
    $_ENV['APP_ENV'] = 'production';
    $_ENV['APP_DEBUG'] = 'true';
    $_ENV['DB_DATABASE'] = 'rentals_qa_nonexistent_database';
    $_SERVER['REQUEST_URI'] = $mount . '/rentals/payment-qr/1';
} elseif (preg_match('#(^|/)\.|^/(?:app|config|routes|storage|bin|docs|vendor|node_modules|tests|database|resources)(?:/|$)|^/micro/payment(?:/|$)#', $relative)
    || str_contains($relative, '..') || preg_match('#^/(?:bootstrap\.php|composer\.)#', $relative)) {
    http_response_code(403); exit;
} elseif (is_file($root . $relative) && strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'php') {
    $mime = match (strtolower(pathinfo($relative, PATHINFO_EXTENSION))) {
        'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml',
        'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($root . $relative); exit;
}
require $root . '/index.php';
