<?php

declare(strict_types=1);

/**
 * Global helpers.
 *
 * Kept deliberately small. Everything here is either an escaping function —
 * which must be short enough that using it is never a chore — or a lookup that
 * would otherwise need a container fetch inside a template.
 *
 * ESCAPING RULE FOR THIS CODEBASE
 * ===============================
 * There is no template engine doing auto-escaping for us, so escaping is a
 * discipline enforced in review. The rule is absolute and has no exceptions
 * worth arguing for:
 *
 *     Never echo a variable without an escaper.
 *
 * The escaper depends on where the value lands. HTML text, an HTML attribute,
 * a URL parameter, and a JavaScript literal have different dangerous characters,
 * and e() is only correct for the first. Using the wrong one is the most common
 * way an "escaped" template still ends up with an XSS hole.
 */

use App\Core\Container;

if (!function_exists('e')) {
    /**
     * Escape for HTML text content. The default; use this unless the value is
     * landing somewhere other than between two tags.
     *
     * ENT_QUOTES  — escapes both quote styles, so this is also safe inside a
     *               quoted attribute.
     * ENT_SUBSTITUTE — invalid UTF-8 becomes U+FFFD instead of an empty string.
     *               Without it, malformed input silently erases the whole value,
     *               which has historically been a way to defeat escaping.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    /**
     * Escape for an HTML attribute value.
     *
     * Identical output to e() for well-formed markup, but exists as a separate
     * call so the intent is visible at the call site — and so that always
     * quoting attributes stays a conscious habit. An unquoted attribute is
     * exploitable with nothing but a space character.
     */
    function e_attr(mixed $value): string
    {
        return e($value);
    }
}

if (!function_exists('e_url')) {
    /**
     * Escape a value being placed into a URL (path segment or query value).
     *
     * rawurlencode, then HTML-escape, because the result is usually landing in
     * an href attribute and needs to survive both contexts.
     */
    function e_url(mixed $value): string
    {
        return e(rawurlencode((string) ($value ?? '')));
    }
}

if (!function_exists('e_js')) {
    /**
     * Escape for a JavaScript literal.
     *
     * JSON-encodes with the tag- and quote-safe flags, producing a value that
     * can be dropped directly into a script block without being able to close
     * it. HEX_TAG is the one that matters: without it a value containing
     * `</script>` terminates the block regardless of any quoting.
     *
     * Prefer passing data through a `data-` attribute and reading it with
     * dataset in the JS module — that keeps the CSP nonce policy simple and
     * avoids the question entirely. Use this only when inlining is unavoidable.
     */
    function e_js(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}

if (!function_exists('env')) {
    /**
     * Read an environment variable with a typed default.
     *
     * Only ever called from config/*.php. Application code reads config(), not
     * env() — so that config can be cached, and so a missing .env fails at boot
     * rather than randomly at the point of use.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Dot-notation config lookup: config('app.url'), config('media.disk').
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Container::instance()->config($key, $default);
    }
}

if (!function_exists('app')) {
    /** @template T of object @param class-string<T>|null $id @return ($id is null ? Container : T) */
    function app(?string $id = null): mixed
    {
        $container = Container::instance();

        return $id === null ? $container : $container->get($id);
    }
}

if (!function_exists('url')) {
    /**
     * Root-relative URL for an application path: /web/css/app.css
     *
     * Relative rather than absolute on purpose. An absolute URL depends on
     * APP_URL being set correctly, and when it is not, every stylesheet, script
     * and link on the site points at the wrong host — which then fails silently
     * as blocked mixed content on an HTTPS page. A root-relative URL cannot
     * have that failure mode: it is correct whatever the host, works on both
     * schemes, and survives the move from /web to the apex with a config change
     * and nothing else.
     *
     * Use absolute_url() for the handful of places the spec demands a full URL.
     */
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.base_path', ''), '/');

        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Fully-qualified URL: https://3ammediatech.com/web/work/example
     *
     * Required — and only used — where a relative URL is invalid or ambiguous:
     * canonical tags, Open Graph and Twitter cards, JSON-LD, the XML sitemap,
     * and links inside transactional email.
     *
     * Falls back to the current request's scheme and host when APP_URL is not
     * configured, so these degrade to something correct rather than to
     * "localhost".
     */
    function absolute_url(string $path = ''): string
    {
        $configured = rtrim((string) config('app.url', ''), '/');

        // Treat the unconfigured default as absent rather than as a real host.
        if ($configured !== '' && !str_starts_with($configured, 'http://localhost')) {
            $origin = $configured;

            // app.url already carries the base path; url() adds it too.
            $basePath = rtrim((string) config('app.base_path', ''), '/');
            if ($basePath !== '' && str_ends_with($origin, $basePath)) {
                $origin = substr($origin, 0, -strlen($basePath));
            }
        } else {
            $scheme = (($_SERVER['HTTPS'] ?? '') === 'on'
                || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443
                || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                ? 'https' : 'http';

            $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return rtrim($origin, '/') . url($path);
    }
}

if (!function_exists('site_media')) {
    /**
     * Resolve a path from config/assets.php to a usable URL.
     *
     * Accepts three forms, so whoever edits the registry does not have to know
     * which one applies:
     *
     *   null / ''                      → null  (slot keeps its placeholder)
     *   'https://…' or '//…'           → used unchanged
     *   'media/showreel.jpg'           → site-relative, cache-busted by mtime
     *
     * Local paths go through versioned() so replacing a file shows the new one
     * immediately instead of waiting out a browser cache — the exact problem
     * that made an earlier deploy look broken.
     */
    function site_media(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        // Absolute URL, protocol-relative, or a data URI — leave alone.
        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
            return $path;
        }

        return versioned($path);
    }
}

if (!function_exists('versioned')) {
    /**
     * Root-relative URL with a cache-busting version stamp:
     *   /web/css/app.css?v=1757502134
     *
     * The stamp is the file's modification time, so it changes exactly when the
     * file does — an edit is live immediately, and an unchanged file keeps its
     * URL and stays cached.
     *
     * This exists because css/app.css and js/app.js are not content-hashed yet.
     * Without it, a long cache lifetime makes an edit invisible to returning
     * visitors, which is indistinguishable from a failed deploy — and cost this
     * project an afternoon.
     *
     * Retired once Vite emits hashed filenames; asset() takes over then.
     */
    function versioned(string $path): string
    {
        $url  = url($path);
        $file = BASE_PATH . '/' . ltrim($path, '/');

        $mtime = is_file($file) ? filemtime($file) : false;

        return $mtime === false ? $url : $url . '?v=' . $mtime;
    }
}

if (!function_exists('asset')) {
    /**
     * URL for a built front-end asset, resolved through the Vite manifest.
     *
     * Filenames are content-hashed, so the manifest lookup is what lets us set
     * a one-year cache lifetime on assets and still ship changes instantly.
     */
    function asset(string $entry): string
    {
        return Container::instance()->get(App\Core\Vite::class)->asset($entry);
    }
}

if (!function_exists('media_url')) {
    /**
     * URL for an uploaded media file on the CDN.
     *
     * Media is served from cdn.3ammediatech.com (CloudFront → S3) rather than
     * the apex, deliberately: putting CloudFront in front of the apex would
     * place the legacy applications behind a cache they were never tested
     * against. See the plan, §14.2.
     */
    function media_url(string $path): string
    {
        return rtrim((string) config('media.cdn_url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Container::instance()->get(App\Core\Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Hidden CSRF input. Every form in this application includes it.
     *
     * Verification happens in the middleware pipeline rather than per-handler,
     * so a POST route without a token is rejected whether or not the developer
     * remembered anything — but the field still has to be in the form, and this
     * is how it gets there.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Previously submitted value, for redisplaying a form after a validation
     * failure. Escaped by the caller, not here — the caller knows whether the
     * value is landing in text or in an attribute.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('str_slug')) {
    /**
     * URL-safe slug.
     *
     * Note the reserved-path check is NOT here — slugs are validated against
     * config/reserved.php at the point of saving, where a collision can be
     * reported to the person typing it. Silently mangling a slug to avoid a
     * collision would produce a URL nobody expects.
     */
    function str_slug(string $value, string $separator = '-'): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';

        return trim($value, $separator);
    }
}

if (!function_exists('str_limit')) {
    /** Truncate on a word boundary — used for meta descriptions and excerpts. */
    function str_limit(string $value, int $limit = 155, string $end = '…'): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit);
        $lastSpace = mb_strrpos($truncated, ' ');

        return rtrim($lastSpace !== false ? mb_substr($truncated, 0, $lastSpace) : $truncated, ' ,.;:') . $end;
    }
}
