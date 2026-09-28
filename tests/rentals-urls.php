<?php
declare(strict_types=1);

// No database writes or .env edits. Exercise both root and mounted config.
$container = require dirname(__DIR__) . '/bootstrap.php';
$savedEnv = $_ENV;
$savedServer = $_SERVER;
$check = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
try {
    foreach (['', '/web', '/web-dev/3am-web'] as $mount) {
        $_ENV['APP_BASE_PATH'] = $mount;
        $_ENV['APP_URL'] = 'https://example.test' . $mount;
        $container->loadConfig(BASE_PATH . '/config');
        $check(config('app.url') === 'https://example.test' . $mount, 'APP_URL duplicated mount.');
        foreach (['rentals', 'rentals/account', 'rentals/cart', 'rentals/checkout', 'rentals/orders',
            'rentals/admin', 'rentals/admin/items?new=1', 'rentals/logout', 'rentals/items#equipment'] as $route) {
            $expected = $mount . '/' . $route;
            $check(url($route) === $expected && url('/' . $route) === $expected, 'Relative URL failed: ' . $route);
            $check(url(url($route)) === $expected, 'Already-mounted URL duplicated: ' . $route);
            $check(absolute_url(url($route)) === 'https://example.test' . $expected, 'Absolute URL duplicated: ' . $route);
            $response = App\Core\Response::redirect(url(url($route)));
            $check($response->headers()['Location'] === $expected, 'Response redirect failed.');
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = $expected;
            $check(App\Core\Request::capture($mount)->path() === '/' . strtok($route, '?#'), 'Request mount removal failed.');
        }
        if ($mount !== '') {
            $check(url($mount . '?q=1') === $mount . '?q=1', 'Mounted root query duplicated.');
            $_SERVER['REQUEST_URI'] = $mount . '-other/rentals';
            $check(App\Core\Request::capture($mount)->path() === $mount . '-other/rentals', 'Request stripped a partial prefix.');
        }
    }
    $_ENV['APP_BASE_PATH'] = '/';
    $_ENV['APP_URL'] = 'https://example.test/';
    $container->loadConfig(BASE_PATH . '/config');
    $check(config('app.base_path') === '' && absolute_url('rentals') === 'https://example.test/rentals', 'Root slash config failed.');
    echo "PASS: root/mounted URLs, already-mounted redirects, absolute URLs, query/fragment preservation and Request path boundaries.\n";
} finally {
    $_ENV = $savedEnv; $_SERVER = $savedServer;
    $container->loadConfig(BASE_PATH . '/config');
}
