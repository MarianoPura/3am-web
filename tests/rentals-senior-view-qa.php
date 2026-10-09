<?php
declare(strict_types=1);

// Default: offline template regression. --database adds rollback-only HOME PC fixtures.
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$_SESSION = [];
$container = require dirname(__DIR__) . '/bootstrap.php';
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$render = static function (string $template, array $data = []): string {
    $view = new App\Core\View(BASE_PATH . '/app/Views');
    return $view->render($template, $data);
};
$request = static function (array $query): App\Core\Request {
    $_GET = $query;
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = url('rentals/admin/customers') . '?' . http_build_query($query);
    return App\Core\Request::capture((string) config('app.base_path'));
};
$pagination = ['total' => 50, 'page' => 1, 'pages' => 2, 'perPage' => 25];
$views = [
    'rentals.admin.items' => 'Products — Rentals Admin',
    'rentals.admin.orders' => 'Orders — Rentals Admin',
    'rentals.admin.customers' => 'Customers — Rentals Admin',
    'rentals.admin.service-requests' => 'Service Requests — Rentals Admin',
    'rentals.orders' => 'Rental Orders — 3AM',
    'rentals.service-requests' => 'My Service Requests — 3AM Rentals',
];
foreach ($views as $template => $title) {
    $html = $render($template, [
        'rows' => [], 'orders' => [], 'categories' => [], 'images' => [],
        'pagination' => $pagination, 'term' => '', 'statusFilter' => 'all',
    ]);
    $assert(preg_match('/<title>(.*?)<\/title>/s', $html, $match) === 1
        && html_entity_decode($match[1], ENT_QUOTES, 'UTF-8') === $title,
        'Pagination contaminated title: ' . $template);
    $assert(substr_count($html, 'class="rentals-pagination"') === 1,
        'Pagination must appear once in page content: ' . $template);
    if ($template === 'rentals.orders') {
        $assert(preg_match('/<link rel="canonical" href="([^"<>]+)"/', $html, $canonical) === 1
            && html_entity_decode($canonical[1], ENT_QUOTES, 'UTF-8') === absolute_url('rentals/orders'),
            'Customer Orders canonical URL contains pagination or unexpected markup.');
    }
}

$_GET = ['role' => 'admin', 'q' => 'Audit <Role>', 'page' => 1];
$html = $render('rentals.admin.customers', ['rows' => [], 'term' => 'Audit <Role>',
    'roleFilter' => 'admin', 'pagination' => $pagination]);
$assert(str_contains($html, '<option value="admin" selected>Admins and Superadmins</option>')
    && str_contains($html, 'value="Audit &lt;Role&gt;"'), 'Customer filter selection/search is missing or unescaped.');
$assert(preg_match('/href="([^"<>]+)" rel="next"/', $html, $next) === 1, 'Customer directory next-page link missing.');
parse_str((string) parse_url(html_entity_decode($next[1], ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY), $query);
$assert($query === ['role' => 'admin', 'q' => 'Audit <Role>', 'page' => '2'], 'Directory pagination lost role or search filters.');
$assert(!preg_match('/<input[^>]+name="page"/', $html), 'Submitting a new directory filter must reset the page.');

$html = (new App\Core\View(BASE_PATH . '/app/Views'))->partial('rentals.partials.admin-dashboard');
$assert(substr_count($html, 'href="' . e_attr(url('rentals/admin/orders')) . '"') === 1
    && str_contains($html, 'View all'), 'Dashboard duplicates Orders list action or removes View all.');
$assert(str_contains($html, 'Manage products'), 'Distinct Manage Products shortcut was removed.');

foreach (['customer' => 'Customer', 'admin' => 'Admin', 'superadmin' => 'Superadmin'] as $role => $label) {
    $html = $render('rentals.account', ['user' => [
        'name' => 'QA <Account>', 'email' => 'account@example.test', 'role' => $role,
        'created_at' => '2026-10-01 10:00:00', 'last_login' => '2026-10-09 12:00:00',
        'password' => 'fixture-hash-must-never-render', 'status_token' => 'fixture-secret-must-never-render',
    ]]);
    $assert(str_contains($html, 'QA &lt;Account&gt;') && str_contains($html, '<dt>Account type</dt><dd>' . $label . '</dd>')
        && str_contains($html, '2026-10-01 10:00:00') && str_contains($html, '2026-10-09 12:00:00'),
        'Account overview lacks escaped facts or correct account type.');
    $assert(!str_contains($html, 'fixture-hash-must-never-render') && !str_contains($html, 'fixture-secret-must-never-render'),
        'Account overview exposes security-only data.');
}

if (in_array('--database', $argv, true)) {
    $connection = config('database.connections.mysql');
    if (config('app.env') !== 'local' || $connection['host'] !== '127.0.0.1'
        || (int) $connection['port'] !== 3306 || $connection['database'] !== 'd3am_rentals_new'
        || config('mail.enabled') !== false) {
        throw new RuntimeException('STOP: HOME PC target and disabled email are required.');
    }
    $db = $container->get(App\Core\Database::class);
    $assert($db->selectValue('SELECT DATABASE()') === 'd3am_rentals_new', 'Connected database identity mismatch.');
    $db->beginTransaction();
    try {
        $key = 'directory-' . bin2hex(random_bytes(6));
        $admins = $customers = [];
        for ($i = 0; $i < 28; ++$i) {
            $role = $i < 26 ? ($i % 2 === 0 ? 'admin' : 'superadmin') : 'customer';
            $id = $db->insert('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)', [
                '[TEST] ' . $key . ' ' . $i, $key . '-' . $i . '@example.test',
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $role,
            ]);
            if ($role === 'customer') { $customers[] = $id; } else { $admins[] = $id; }
        }
        $_SESSION = ['user_id' => $admins[0]];
        $controller = new App\Controllers\Rentals\RentalAdminController($container);
        $html = $controller->customers($request(['q' => $key, 'role' => 'admin']))->body();
        $assert(str_contains($html, 'of 26') && str_contains($html, 'Page 1 of 2'), 'Admin/Superadmin SQL filter count or pagination failed.');
        $assert(substr_count($html, '<tr>') === 26, 'Admin page must have one header plus 25 account rows.');
        $assert(!str_contains($html, $key . '-26@example.test') && !str_contains($html, $key . '-27@example.test'),
            'Admin role filter includes Customer accounts.');
        $second = $controller->customers($request(['q' => $key, 'role' => 'admin', 'page' => 999999]))->body();
        $assert(str_contains($second, 'Page 2 of 2') && substr_count($second, '<tr>') === 2,
            'Excessive directory page did not clamp to the last page.');
        $html = $controller->customers($request(['q' => $key, 'role' => 'customer']))->body();
        $assert(str_contains($html, 'of 2') && substr_count($html, '<tr>') === 3
            && str_contains($html, $key . '-26@example.test') && str_contains($html, $key . '-27@example.test'),
            'Customer SQL filter failed or leaked administrative rows.');
        $html = $controller->customers($request(['q' => $key, 'role' => "admin' OR 1=1 --", 'page' => ['bad']]))->body();
        $assert(str_contains($html, 'of 28') && str_contains($html, 'Page 1 of 2'), 'Invalid role/page did not safely fall back.');
        $html = $controller->customers($request(['q' => $key . "' OR 1=1 --", 'role' => 'all']))->body();
        $assert(str_contains($html, 'No records match this view.'), 'Search input altered the SQL predicate.');
        $_SESSION = ['user_id' => $customers[0]];
        $response = $controller->customers($request(['q' => $key, 'role' => 'all']));
        $assert($response->status() === 403 && str_contains($response->body(), 'Admin access is restricted.')
            && !str_contains($response->body(), '<h2>Account directory</h2>'), 'Customer can read the account directory.');
        // The allowed header displays the Customer's own name, which also
        // contains the fixture prefix. Check forbidden account data precisely.
        for ($i = 0; $i < 26; ++$i) {
            $assert(!str_contains($response->body(), $key . '-' . $i . '@example.test'),
                'Customer denial exposed an administrative account email.');
        }
    } finally {
        $db->rollback();
        $_SESSION = [];
    }
    echo "PASS: HOME PC account-directory SQL filters, role grouping, counts, limits, page fallback, search binding and Customer denial; fixtures rolled back.\n";
}
echo 'PASS: ' . $checks . " assertions; six clean titles, valid Orders canonical, single pagination, preserved filters, dashboard actions and private account facts.\n";
