<?php
declare(strict_types=1);

// Explicit CLI diagnostic: SELECT/SHOW only, no credentials or customer fields.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$container = require dirname(__DIR__) . '/bootstrap.php';
$db = $container->get(App\Core\Database::class);
try {
    $report = (new App\Services\RentalSchema($db))->inspect([
        'users', 'rental_categories', 'rental_items', 'rental_service_requests',
        'payment_methods', 'rental_notification_deliveries',
    ]);
    $report['database'] = $db->selectValue('SELECT DATABASE()');
    $report['server_version'] = $db->selectValue('SELECT VERSION()');
    $report['service_10_relationship'] = $db->selectOne(
        'SELECT i.id, i.category_id, i.is_active AS item_active, i.availability_status,
                c.id AS matched_category_id, c.is_service, c.is_active AS category_active
         FROM rental_items i LEFT JOIN rental_categories c ON c.id=i.category_id WHERE i.id=?', [10]
    );
    $report['service_10_eligible'] = (new App\Services\RentalServiceRequests($db))->service(10) !== null;
    $report['php_version'] = PHP_VERSION;
    $report['missing_extensions'] = array_values(array_filter(
        ['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'],
        static fn (string $extension): bool => !extension_loaded($extension)
    ));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit($report['issues'] === [] && $report['missing_extensions'] === [] ? 0 : 1);
} catch (Throwable $e) {
    // Driver codes identify schema/permission failures without SQL values or DSNs.
    $cause = $e;
    while ($cause->getPrevious() !== null) { $cause = $cause->getPrevious(); }
    $info = $cause instanceof PDOException ? $cause->errorInfo : null;
    fwrite(STDERR, 'Service diagnostic failed: ' . get_class($cause)
        . ' SQLSTATE=' . ($info[0] ?? 'unavailable') . ' driver_code=' . ($info[1] ?? 'unavailable') . "\n");
    exit(2);
}
