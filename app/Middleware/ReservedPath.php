<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Reserved path guard — legacy application protection.
 *
 * WHY THIS EXISTS
 * ===============
 * 3ammediatech.com already hosts working applications (/armonyx, /trebl, ...).
 * The new site owns the apex and catches every unmatched path, which means a
 * mistake here takes down live client software rather than merely showing the
 * wrong page.
 *
 * The web server is the real enforcement layer: legacy paths are matched first
 * and never reach PHP at all. This middleware is the second line, and it exists
 * because a web-server config is a file that someone can edit at 2am without
 * having read the deployment notes. If the fallthrough rules are ever wrong,
 * the failure mode should be an honest 404 — not this site rendering a page
 * where a client's application is supposed to be.
 *
 * It runs BEFORE routing and before any database query, so a reserved path
 * costs one array lookup and nothing else.
 *
 * @see config/reserved.php
 * @see bin/legacy-smoketest.sh — verifies the enforcement actually holds
 */
final class ReservedPath
{
    /** @var list<string>|null Lazily built, then held for the request. */
    private static ?array $reserved = null;

    public function __construct(
        private readonly array $config,
        private readonly string $cachePath,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $segment = $this->firstSegment($request->path());

        if ($segment !== '' && $this->isReserved($segment)) {
            // Deliberately a plain 404 with no branding and no logging noise.
            //
            // Not a redirect: a redirect would be a guess about where the user
            // meant to go, and guessing wrong on a path that belongs to another
            // application is worse than an honest miss.
            //
            // Not a 403: that would confirm the path is meaningful here, and
            // this application genuinely has nothing at it.
            return Response::notFound()
                ->withHeader('X-Reserved-Path', '1'); // for smoke-test diagnosis
        }

        return $next($request);
    }

    /**
     * First path segment, lowercased and normalised.
     *
     * Normalisation matters more than it looks. `/Armonyx`, `/armonyx/`,
     * `/armonyx%2F..`, and `//armonyx` must all resolve to the same decision,
     * because a guard that can be stepped around with a capital letter is not
     * a guard.
     */
    private function firstSegment(string $path): string
    {
        // Decode once so that %2F and friends cannot smuggle a segment past us.
        $path = rawurldecode($path);

        // Collapse duplicate slashes and strip any leading traversal.
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        $segment = strtok($path, '/');

        if ($segment === false || $segment === '') {
            return '';
        }

        return strtolower(trim($segment));
    }

    private function isReserved(string $segment): bool
    {
        return in_array($segment, $this->reservedList(), true);
    }

    /**
     * The reserved list: the explicit config array, plus every real directory
     * in the legacy document root.
     *
     * The directory scan is what makes this self-maintaining. An application
     * deployed to /var/www/html/newapp is protected the moment it exists,
     * without anyone remembering to update config/reserved.php — which is the
     * failure mode most likely to actually occur.
     *
     * @return list<string>
     */
    private function reservedList(): array
    {
        if (self::$reserved !== null) {
            return self::$reserved;
        }

        $explicit = array_map('strtolower', $this->config['paths'] ?? []);
        $scanned  = $this->scanDocroot();

        return self::$reserved = array_values(array_unique([...$explicit, ...$scanned]));
    }

    /**
     * Scan the legacy document root for real directories, cached to disk.
     *
     * This runs on every request, so it must not stat the filesystem every
     * time. Cache TTL defaults to an hour — the legacy app list changes about
     * as often as someone deploys a new application, which is to say rarely.
     *
     * @return list<string>
     */
    private function scanDocroot(): array
    {
        $docroot = $this->config['scan_docroot'] ?? null;

        if (!is_string($docroot) || $docroot === '' || !is_dir($docroot)) {
            return [];
        }

        $ttl  = (int) ($this->config['scan_ttl'] ?? 3600);
        $file = $this->cachePath . '/reserved-paths.php';

        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $cached = @include $file;
            if (is_array($cached)) {
                return $cached;
            }
        }

        $dirs = [];
        foreach ((array) @scandir($docroot) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($docroot . '/' . $entry)) {
                $dirs[] = strtolower($entry);
            }
        }

        // Write atomically — a half-written cache file included by a concurrent
        // request would be a parse error on a live page.
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, '<?php return ' . var_export($dirs, true) . ';') !== false) {
            @rename($tmp, $file);
        }

        return $dirs;
    }

    /**
     * Is this slug safe for CMS content?
     *
     * Used by admin slug validation, so a project can never be given a slug
     * that would shadow a legacy application or one of this site's own routes.
     * Same list, second enforcement point — see the class docblock on why the
     * redundancy is deliberate.
     */
    public function isAvailableSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        if (in_array($slug, $this->reservedList(), true)) {
            return false;
        }

        return !in_array($slug, array_map('strtolower', $this->config['slugs'] ?? []), true);
    }
}
