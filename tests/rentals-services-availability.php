<?php
declare(strict_types=1);

// In-memory PDO fixtures only. No database connection, schema changes or mail.
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$_ENV['RENTALS_PREVIEW_SAMPLES'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
$check = static function (bool $ok, string $why): void {
    if (!$ok) { throw new RuntimeException($why); }
};
class ServiceAvailabilityStatement extends PDOStatement
{
    public function __construct(private readonly string $sql, private readonly bool $missing) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool { return true; }
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed {
        return str_contains($this->sql, 'information_schema.tables') ? ($this->missing ? 0 : 1) : 0;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return str_contains($this->sql, 'FROM users')
            ? ['id' => 1, 'name' => 'Fixture admin', 'email' => 'admin@example.test', 'password' => 'fixture', 'role' => 'admin'] : false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
}
class ServiceAvailabilityPDO extends PDO
{
    public function __construct(private readonly bool $missing = false, private readonly bool $failure = false) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($this->failure && str_contains($query, 'FROM rental_service_requests')) { throw new PDOException('Fixture request SELECT failure'); }
        return new ServiceAvailabilityStatement($query, $this->missing);
    }
}
$database = static function (bool $missing = false, bool $failure = false): App\Core\Database {
    $db = new App\Core\Database([]);
    (new ReflectionProperty($db, 'pdo'))->setValue($db, new ServiceAvailabilityPDO($missing, $failure));
    return $db;
};
$request = App\Core\Request::capture();
foreach ([false, true] as $missing) {
    $container->set(App\Core\Database::class, $database($missing));
    $_SESSION = ['user_id' => 1, 'rentals_password_stamp' => hash('sha256', 'fixture')];
    $response = (new App\Controllers\Rentals\RentalServiceRequestController($container))->adminList($request);
    $check($response->status() === ($missing ? 503 : 200), 'Empty rows were confused with an unavailable table.');
    $check(str_contains($response->body(), $missing ? 'temporarily unavailable' : 'No service requests match this view.'), 'Incorrect empty/unavailable state.');
}
$container->set(App\Core\Database::class, $database(false, true));
$_SESSION = ['user_id' => 1, 'rentals_password_stamp' => hash('sha256', 'fixture')];
try {
    (new App\Controllers\Rentals\RentalServiceRequestController($container))->adminList($request);
    throw new LogicException('Database failure was hidden as an empty list.');
} catch (RuntimeException $e) {
    $check($e->getPrevious() instanceof PDOException, 'Original database failure was lost.');
}
$container->set(App\Core\Database::class, $database());
$_SESSION = [];
$check((new App\Controllers\Rentals\RentalServiceRequestController($container))->adminList($request)->status() === 403, 'Admin authorization changed.');
$catalog = (new App\Models\RentalCatalog($database()))->load();
$check(!$catalog['catalogUnavailable'] && $catalog['services'] === [], 'Healthy empty catalogue was marked unavailable.');
$public = new App\Controllers\Rentals\RentalsController($container);
$response = $public->services($request);
$check($response->status() === 200 && str_contains($response->body(), 'No services are currently listed.'), 'Empty service catalogue must render a normal state.');
$view = $container->get(App\Core\View::class);
$html = $view->partial('rentals.partials.service-catalogue', ['services' => [], 'catalogUnavailable' => true]);
$check(str_contains($html, 'Services are temporarily unavailable.') && !str_contains($html, 'No services are currently listed.'), 'Catalogue failure was hidden as empty inventory.');
echo "PASS: healthy empty services/requests return HTTP 200, missing request table returns 503, SELECT errors retain PDO cause, authorization is preserved, empty and failed catalogues are distinct. No database writes.\n";
