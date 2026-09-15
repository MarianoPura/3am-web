<?php

declare(strict_types=1);

/**
 * Front controller — the single entry point for every request.
 *
 * DEPLOYMENT NOTE
 * ===============
 * This application is installed inside the document root at
 * /var/www/html/web/, so that it serves at 3ammediatech.com/web with no vhost
 * changes — the same way /armonyx does.
 *
 * The consequence is that app/, config/, storage/ and .env sit beside this file
 * and ARE addressable by URL. They are protected by the deny rules in
 * ./.htaccess plus a per-directory .htaccess in each, rather than by living
 * outside the served tree.
 *
 * That protection only holds while Apache honours .htaccess. If AllowOverride
 * is ever set to None for /var/www/html, the source becomes public. The curl
 * checks documented at the top of ./.htaccess verify this, and are worth
 * re-running after any server change.
 */

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Middleware\ReservedPath;
use App\Middleware\SecureHeaders;

/** @var Container $container */
$container = require __DIR__ . '/bootstrap.php';

// ─────────────────────────────────────────────────────────────
// Session
//
// ★ COLLISION HAZARD ★
// The legacy applications share this domain. If they use the default PHPSESSID
// at cookie path '/' and this site does too, the cookies overwrite each other
// and users get logged out of /armonyx unpredictably — an intermittent bug that
// would be very hard to trace back here. Distinct name AND distinct store.
// ─────────────────────────────────────────────────────────────
$sessionPath = BASE_PATH . '/storage/sessions';

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0770, true);
}

session_name((string) env('SESSION_NAME', 'TAM_SESS'));
session_save_path($sessionPath);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => (config('app.base_path') ?: '/'),
    'domain'   => '',
    'secure'   => (bool) config('app.force_https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// ─────────────────────────────────────────────────────────────
// Request
// ─────────────────────────────────────────────────────────────
$request = Request::capture((string) config('app.base_path'));

// ─────────────────────────────────────────────────────────────
// Middleware pipeline
//
// Order matters and is deliberate:
//   SecureHeaders — outermost, so headers land on every response including
//                   errors and 404s from the guard below.
//   ReservedPath  — before routing and before any DB query, so a legacy path
//                   costs one array lookup.
//   Csrf          — before the handler, so no state change happens unverified.
//
// CSRF is a pipeline stage rather than a per-handler call precisely so a new
// POST route is protected whether or not anyone remembered it existed.
// ─────────────────────────────────────────────────────────────
$secureHeaders = new SecureHeaders(
    forceHttps:   (bool) config('app.force_https'),
    hstsMaxAge:   (int) config('app.hsts_max_age'),
    isProduction: config('app.env') === 'production',
    cdnUrl:       (string) config('media.cdn_url', ''),
);

$container->set(SecureHeaders::class, $secureHeaders);

// Templates need the CSP nonce to mark script tags as trusted. The policy uses
// 'strict-dynamic', so an allowlisted CDN host is not sufficient on its own —
// without the nonce the GSAP tags are silently refused.
$container->get(View::class)->share('nonce', $secureHeaders->nonce());

$pipeline = [
    $secureHeaders,
    new ReservedPath(config('reserved'), BASE_PATH . '/storage/cache'),
];

// ─────────────────────────────────────────────────────────────
// Routes
// ─────────────────────────────────────────────────────────────
/** @var Router $router */
$router = $container->get(Router::class);
require BASE_PATH . '/routes/web.php';

// ─────────────────────────────────────────────────────────────
// Dispatch
// ─────────────────────────────────────────────────────────────
$dispatch = static function (Request $request) use ($router, $container): Response {
    $match = $router->match($request);

    if ($match === null) {
        // Distinguish "no such URL" from "wrong verb for this URL". The second
        // is a real debugging aid when a form posts to a GET-only route.
        if ($router->matchesOtherMethod($request)) {
            return Response::html('Method Not Allowed', 405);
        }

        return render_error($container, 404);
    }

    // Verify CSRF for state-changing requests, once, here.
    if ($request->isMutating() && !$container->get(Csrf::class)->verify($request)) {
        return render_error($container, 419, 'Your session expired. Please go back and try again.');
    }

    [$class, $method] = $match['handler'];

    return (new $class($container))->{$method}($request, ...array_values($match['params']));
};

/** Compose the pipeline inside-out around the dispatcher. */
$handler = array_reduce(
    array_reverse($pipeline),
    static fn (callable $next, object $middleware): callable
        => static fn (Request $r): Response => $middleware->handle($r, $next),
    $dispatch
);

function render_error(Container $container, int $status, string $message = ''): Response
{
    try {
        return $container->get(View::class)->response('pages.error', [
            'status'  => $status,
            'message' => $message,
        ], $status);
    } catch (Throwable) {
        // The error page itself failed. Fall back to something that cannot.
        return Response::html('<h1>' . $status . '</h1>', $status);
    }
}

try {
    $handler($request)->send();
} catch (Throwable $e) {
    error_log(sprintf(
        '[%s] %s in %s:%d%s%s',
        date('c'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    if (config('app.debug')) {
        // Debug output is escaped. An exception message can contain user input,
        // and rendering it raw would turn the error page into an XSS vector —
        // on the one page where nobody is looking for one.
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<pre style="font:13px/1.5 monospace;padding:2rem;background:#0B1622;color:#F5F7FA">';
        echo e(get_class($e) . ': ' . $e->getMessage()) . "\n\n";
        echo e($e->getFile() . ':' . $e->getLine()) . "\n\n";
        echo e($e->getTraceAsString());
        echo '</pre>';
        exit;
    }

    render_error($container, 500)->send();
}
