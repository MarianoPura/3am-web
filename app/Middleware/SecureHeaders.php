<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Security response headers.
 *
 * Applied to every response from one place, so the policy is auditable in a
 * single file rather than scattered across vhost config and controllers.
 *
 * The CSP is nonce-based, which is why the codebase has no inline scripts:
 * GSAP timelines live in external modules and data reaches JavaScript through
 * `data-` attributes read via dataset. That constraint is deliberate — a CSP
 * with 'unsafe-inline' is decorative.
 */
final class SecureHeaders
{
    private string $nonce;

    public function __construct(
        private readonly bool $forceHttps = true,
        private readonly int $hstsMaxAge = 31536000,
        private readonly bool $isProduction = true,
        private readonly string $cdnUrl = '',
    ) {
        // One nonce per request, shared with the view layer so script tags can
        // carry it.
        $this->nonce = base64_encode(random_bytes(16));
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = [
            // Stop the browser guessing a content type — the vector behind a
            // surprising number of "harmless upload" escalations.
            'X-Content-Type-Options'  => 'nosniff',

            // Legacy equivalent of frame-ancestors, for older browsers.
            'X-Frame-Options'         => 'DENY',

            // Send the origin cross-site but the full path same-site: enough
            // for analytics attribution, without leaking admin URLs outward.
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',

            // Deny hardware this site never uses. An embedded third-party frame
            // cannot ask for a camera on our behalf.
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',

            'Cross-Origin-Opener-Policy' => 'same-origin',

            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        // HSTS only over HTTPS. Sent on a plain HTTP response it is ignored,
        // and in development it would pin localhost to HTTPS in the browser —
        // a genuinely annoying thing to undo.
        if ($this->forceHttps && $request->isSecure()) {
            $headers['Strict-Transport-Security'] =
                sprintf('max-age=%d; includeSubDomains', $this->hstsMaxAge);
            // `preload` is added only once the domain is verified — it is very
            // hard to reverse, and it would also commit every legacy app path.
        }

        return $response->withHeaders($headers);
    }

    private function contentSecurityPolicy(): string
    {
        $self = "'self'";
        $cdn  = $this->cdnUrl !== '' ? ' ' . $this->cdnUrl : '';

        $directives = [
            "default-src {$self}",

            // strict-dynamic lets a nonced module load its own imports without
            // every chunk needing a nonce. The http:/https: fallbacks are
            // ignored by browsers that understand strict-dynamic and keep older
            // ones working.
            "script-src {$self} 'nonce-{$this->nonce}' 'strict-dynamic' https:",

            // 'unsafe-inline' for styles only. Removing it would mean no style
            // attribute anywhere, which GSAP sets constantly — a nonce cannot
            // cover script-driven inline styles. Style injection is a far
            // narrower risk than script injection, so this is a considered
            // trade rather than an oversight.
            "style-src {$self} 'unsafe-inline' https://fonts.googleapis.com",

            "font-src {$self} https://fonts.gstatic.com data:",
            "img-src {$self} data: blob:{$cdn} https://i.vimeocdn.com https://i.ytimg.com",
            "media-src {$self}{$cdn}",

            // Video embeds are facade-loaded — the iframe only appears after a
            // click — but the frame-src must still permit it.
            "frame-src https://player.vimeo.com https://www.youtube-nocookie.com",

            "connect-src {$self}{$cdn}",
            "form-action {$self}",
            "base-uri {$self}",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];

        if ($this->forceHttps && $this->isProduction) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
