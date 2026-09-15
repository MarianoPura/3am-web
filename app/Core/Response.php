<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response.
 *
 * Nothing in this application echoes directly. Controllers and middleware
 * return a Response, and exactly one place — send() — writes to the output
 * buffer. That is what makes the middleware pipeline able to inspect and modify
 * a response on the way out (security headers, caching), and what stops a stray
 * echo in a model from corrupting a redirect.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name:string, value:string, options:array}> */
    private array $cookies = [];

    private function __construct(
        private string $body = '',
        private int $status = 200,
    ) {
    }

    // ─────────────────────────────────────────────────────────
    // Constructors
    // ─────────────────────────────────────────────────────────

    public static function make(string $body = '', int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function html(string $html, int $status = 200): self
    {
        return (new self($html, $status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function text(string $text, int $status = 200): self
    {
        return (new self($text, $status))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public static function xml(string $xml, int $status = 200): self
    {
        return (new self($xml, $status))
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * 302 by default. Pass 301 only where the move is genuinely permanent —
     * browsers cache a 301 aggressively and a mistaken one is very hard to
     * take back.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return self::html($body, 404);
    }

    public static function forbidden(string $body = 'Forbidden'): self
    {
        return self::html($body, 403);
    }

    public static function serverError(string $body = 'Server Error'): self
    {
        return self::html($body, 500);
    }

    /** 429, with the Retry-After the spec expects so clients can back off. */
    public static function tooManyRequests(int $retryAfterSeconds): self
    {
        return self::html('Too Many Requests', 429)
            ->withHeader('Retry-After', (string) $retryAfterSeconds);
    }

    // ─────────────────────────────────────────────────────────
    // Mutators (fluent, mutate in place — a response is built, then sent once)
    // ─────────────────────────────────────────────────────────

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Queue a cookie.
     *
     * Secure/HttpOnly/SameSite default on rather than off. A cookie that needs
     * to be readable by JavaScript has to say so explicitly, which makes it
     * visible in review — the right way round for a default.
     */
    public function withCookie(
        string $name,
        string $value,
        int $expiresIn = 0,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        $this->cookies[] = [
            'name'    => $name,
            'value'   => $value,
            'options' => [
                'expires'  => $expiresIn > 0 ? time() + $expiresIn : 0,
                'path'     => '/',
                'secure'   => (bool) config('app.force_https', true),
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ],
        ];

        return $this;
    }

    /**
     * Cache headers for a public, cacheable page.
     *
     * stale-while-revalidate lets a CDN serve the cached copy instantly while
     * refreshing behind the scenes, which is most of the benefit of a cache
     * without the staleness being visible to a visitor.
     */
    public function cacheFor(int $seconds, int $staleWhileRevalidate = 60): self
    {
        return $this->withHeader(
            'Cache-Control',
            sprintf('public, max-age=%d, stale-while-revalidate=%d', $seconds, $staleWhileRevalidate)
        );
    }

    /** Explicitly uncacheable — anything user-specific or behind auth. */
    public function noCache(): self
    {
        return $this->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma'        => 'no-cache',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    // ─────────────────────────────────────────────────────────
    // Output
    // ─────────────────────────────────────────────────────────

    public function send(): void
    {
        if (headers_sent($file, $line)) {
            // Almost always a stray echo or a byte before an opening <?php tag.
            // Say exactly where, because this is otherwise a miserable hunt.
            throw new \RuntimeException(
                sprintf('Headers already sent by %s:%d — output began before the response was built.', $file, $line)
            );
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], $cookie['options']);
        }

        // 204 and 304 must not carry a body; sending one confuses proxies.
        if ($this->status !== 204 && $this->status !== 304) {
            echo $this->body;
        }
    }
}
