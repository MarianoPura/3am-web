<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Resolves built front-end assets through the Vite manifest.
 *
 * Vite runs locally and in CI only — never on the server. The production box
 * has no Node runtime and does not need one; a release ships with build/
 * already populated, so a deploy is a file copy with no build step that can
 * fail halfway.
 *
 * Built filenames are content-hashed, which is what allows a one-year cache
 * lifetime on assets while still shipping a CSS change instantly.
 */
final class Vite
{
    /** @var array<string, array{file:string, css?:list<string>}>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly string $buildPath,
        private readonly string $buildUrl,
        private readonly bool $devServer = false,
        private readonly string $devUrl = 'http://localhost:5173',
    ) {
    }

    /**
     * URL for a source entry, e.g. asset('resources/js/app.js').
     *
     * Falls back to the unbuilt source path when no manifest exists, so that a
     * fresh checkout renders something recognisable instead of a fatal error
     * before anyone has run `npm run build`.
     */
    public function asset(string $entry): string
    {
        if ($this->devServer) {
            return $this->devUrl . '/' . ltrim($entry, '/');
        }

        $manifest = $this->manifest();

        if ($manifest === null || !isset($manifest[$entry]['file'])) {
            return rtrim($this->buildUrl, '/') . '/' . ltrim($entry, '/');
        }

        return rtrim($this->buildUrl, '/') . '/' . $manifest[$entry]['file'];
    }

    /**
     * Full tag set for an entry: the module script, plus any CSS Vite split
     * out of it. Emitting the CSS links here is what prevents a flash of
     * unstyled content on a code-split page.
     */
    public function tags(string $entry, ?string $nonce = null): string
    {
        $nonceAttr = $nonce !== null ? ' nonce="' . e_attr($nonce) . '"' : '';
        $html      = '';

        if ($this->devServer) {
            $html .= '<script type="module" src="' . e_attr($this->devUrl . '/@vite/client') . '"' . $nonceAttr . '></script>';
        }

        // manifest() is null before the first build, so guard the lookup —
        // dereferencing it would throw under the bootstrap's error handler
        // rather than falling back cleanly.
        foreach (($this->manifest() ?? [])[$entry]['css'] ?? [] as $css) {
            $html .= '<link rel="stylesheet" href="' . e_attr(rtrim($this->buildUrl, '/') . '/' . $css) . '">';
        }

        $html .= '<script type="module" src="' . e_attr($this->asset($entry)) . '"' . $nonceAttr . ' defer></script>';

        return $html;
    }

    /** @return array<string, array{file:string, css?:list<string>}>|null */
    private function manifest(): ?array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $file = $this->buildPath . '/.vite/manifest.json';

        if (!is_file($file)) {
            // Vite 4 and earlier wrote it here.
            $file = $this->buildPath . '/manifest.json';
        }

        if (!is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return $this->manifest = is_array($decoded) ? $decoded : null;
    }
}
