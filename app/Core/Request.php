<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable snapshot of the incoming HTTP request.
 *
 * Built once in the front controller from the superglobals, after which nothing
 * in the application reads $_GET, $_POST or $_SERVER directly. That single rule
 * is what makes the request testable, and it is why input() below is the only
 * door user input comes through.
 */
final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $basePath,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
    ) {
    }

    public static function capture(string $basePath = ''): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Honour method spoofing from a hidden _method field, so that forms can
        // express PUT/PATCH/DELETE. Only ever from a POST — accepting it on a
        // GET would make a link capable of performing a destructive action.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        return new self(
            method:   $method,
            path:     self::resolvePath($_SERVER['REQUEST_URI'] ?? '/', $basePath),
            basePath: $basePath,
            query:    $_GET,
            body:     $_POST,
            server:   $_SERVER,
            cookies:  $_COOKIE,
            files:    $_FILES,
        );
    }

    /**
     * Application-relative path, with the query string and any mount prefix
     * removed, normalised to a leading slash and no trailing slash.
     *
     * The base path matters because the site runs at a subdirectory during
     * development (/web) before moving to the apex. Routes are written once,
     * against the apex, and this strips the mount point so they match in both
     * places.
     */
    private static function resolvePath(string $uri, string $basePath): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Decode once. Doing it twice would let %252e%252e become '..' after a
        // check that already looked at the decoded form — the classic double
        // decode traversal.
        $path = rawurldecode($path);

        // Collapse duplicate slashes so //admin and /admin are one path.
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim($path, '/');

        return $path;
    }

    // ─────────────────────────────────────────────────────────
    // Basics
    // ─────────────────────────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** State-changing methods — the set the CSRF middleware guards. */
    public function isMutating(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    // ─────────────────────────────────────────────────────────
    // Input
    // ─────────────────────────────────────────────────────────

    /**
     * A single input value, body first then query.
     *
     * Returns only scalars and null. An unexpected array — `?id[]=1`, which is
     * how a surprising number of type-juggling bugs start — yields the default
     * rather than propagating an array into code that expects a string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_scalar($value) || $value === null ? $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** Explicitly an array — for multi-selects and checkbox groups. */
    public function array(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    // ─────────────────────────────────────────────────────────
    // Client
    // ─────────────────────────────────────────────────────────

    /**
     * Client IP.
     *
     * Reads REMOTE_ADDR only. X-Forwarded-For is deliberately ignored: it is
     * attacker-controlled unless a trusted proxy is known to overwrite it, and
     * this value feeds rate limiting, where trusting a spoofable header would
     * let anyone bypass the limit by inventing a new IP per request.
     *
     * When the apex eventually sits behind CloudFront or an ALB, revisit this
     * and validate against the proxy's published ranges — not before.
     */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || (int) ($this->server['SERVER_PORT'] ?? 80) === 443
            || strtolower($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? 'localhost';
    }

    /** Full URL of the current request, for canonical tags and form actions. */
    public function fullUrl(): string
    {
        return ($this->isSecure() ? 'https://' : 'http://')
             . $this->host()
             . $this->basePath
             . ($this->path === '/' ? '' : $this->path);
    }
}
