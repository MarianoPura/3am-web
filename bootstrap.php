<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Deliberately runs with NO Composer dependencies. The packages in
 * composer.json (AWS SDK, PHPMailer, TOTP) are needed by features that do not
 * exist yet, and requiring `composer install` before the site will render at
 * all is a needless blocker on a server where Composer may not be present.
 *
 * When vendor/autoload.php does exist it is used, and the built-in autoloader
 * below stays out of the way.
 */

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\View;
use App\Core\Vite;

define('BASE_PATH', __DIR__);

// ─────────────────────────────────────────────────────────────
// 1. Autoloading
// ─────────────────────────────────────────────────────────────
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Minimal PSR-4 autoloader for App\ -> app/
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, 4));
        $file     = BASE_PATH . '/app/' . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }
    });

    require BASE_PATH . '/app/Helpers/functions.php';
}

// ─────────────────────────────────────────────────────────────
// 2. Environment
// ─────────────────────────────────────────────────────────────

/**
 * Minimal .env parser.
 *
 * Handles KEY=value, quoted values, comments and blank lines — which is all
 * this project's .env uses. Swapped for vlucas/phpdotenv automatically once
 * Composer dependencies are installed.
 */
$loadEnv = static function (string $file): void {
    if (!is_readable($file)) {
        return;
    }

    foreach ((array) file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, and only those.
        if (strlen($value) > 1
            && ($value[0] === '"' || $value[0] === "'")
            && $value[-1] === $value[0]
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
};

if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
} else {
    $loadEnv(BASE_PATH . '/.env');
}

// ─────────────────────────────────────────────────────────────
// 3. Error handling
// ─────────────────────────────────────────────────────────────
$isDebug = (bool) env('APP_DEBUG', false);

error_reporting(E_ALL);
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Manila'));

/*
 * Promote warnings and notices to exceptions — but only outside production.
 *
 * An undefined array key that silently yields null is how a page ends up
 * rendering the wrong project rather than failing loudly, so in development we
 * want it to stop the world.
 *
 * In production the same rule would be actively harmful: a deprecation notice
 * from a dependency would become an uncaught exception and take a working page
 * down over something cosmetic. There, these are logged and execution continues.
 */
$promoteToException = $isDebug || env('APP_ENV', 'production') !== 'production';

set_error_handler(static function (
    int $severity,
    string $message,
    string $file,
    int $line
) use ($promoteToException): bool {
    // Respect the current error_reporting level and the @ suppression operator.
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    if ($promoteToException && !in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    error_log(sprintf('[%s] PHP %d: %s in %s:%d', date('c'), $severity, $message, $file, $line));

    return true;
});

/*
 * Fatal errors bypass the handler above and the try/catch in the front
 * controller. Without this, an out-of-memory or a fatal in a template renders
 * a blank white page with a 200 status — which caches, and which monitoring
 * reads as healthy.
 */
register_shutdown_function(static function () use ($isDebug): void {
    $error = error_get_last();

    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    error_log(sprintf(
        '[%s] FATAL: %s in %s:%d',
        date('c'),
        $error['message'],
        $error['file'],
        $error['line']
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo $isDebug
        ? '<pre>' . htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<h1>500</h1>';
});

// ─────────────────────────────────────────────────────────────
// 4. Container and config
// ─────────────────────────────────────────────────────────────
$container = Container::instance();
$container->loadConfig(BASE_PATH . '/config');

// ─────────────────────────────────────────────────────────────
// 5. Services
// ─────────────────────────────────────────────────────────────
$container->bind(Database::class, static fn (Container $c): Database => new Database(
    $c->config('database.connections.mysql'),
    (bool) $c->config('app.debug'),
));

$container->bind(View::class, static function (Container $c): View {
    $view = new View(BASE_PATH . '/app/Views');

    // Shared with every template, so no controller has to remember to pass it.
    $view->share('site', $c->config('app'));

    return $view;
});

$container->bind(Router::class, static fn (): Router => new Router());

$container->bind(Csrf::class, static fn (): Csrf => new Csrf());

$container->bind(\App\Services\SmtpMailer::class, static fn (Container $c): \App\Services\SmtpMailer => new \App\Services\SmtpMailer(
    (array) $c->config('mail', [])
));

$container->bind(\App\Services\TrackingService::class, static fn (Container $c): \App\Services\TrackingService => new \App\Services\TrackingService(
    $c->get(Database::class)
));

$container->bind(\App\Services\InquiryStore::class, static fn (Container $c): \App\Services\InquiryStore => new \App\Services\InquiryStore(
    storagePath: BASE_PATH . '/storage/inquiries',
    siteName:    (string) $c->config('app.name'),
    db:          $c->get(Database::class),
    mailer:      $c->get(\App\Services\SmtpMailer::class),
    tracking:    $c->get(\App\Services\TrackingService::class),
    mailConfig:  (array) $c->config('mail.inquiry', []),
    company:     (array) $c->config('app.company', []),
));

$container->bind(Vite::class, static fn (Container $c): Vite => new Vite(
    buildPath: BASE_PATH . '/build',
    // Root-relative, like every other asset URL — see url() in Helpers.
    buildUrl:  url('build'),
    devServer: (bool) $c->config('app.vite_dev_server'),
));

return $container;
