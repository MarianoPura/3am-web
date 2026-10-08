<?php
declare(strict_types=1);
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
require dirname(__DIR__) . '/bootstrap.php';
$fields = ['phone' => 'QA phone', 'location' => 'QA venue', 'details' => 'QA scope',
    'start_date' => date('Y-m-d', strtotime('+5 days')), 'end_date' => date('Y-m-d', strtotime('+6 days'))];
foreach (['start_date', 'end_date'] as $field) {
    foreach (["2099-01-\0" . '01', '2099-02-30', '2099-1-01', '10000-01-01', 'not-a-date', ''] as $bad) {
        $errors = App\Services\RentalServiceRequests::errors(array_replace($fields, [$field => $bad]));
        if (!isset($errors[$field])) { throw new RuntimeException('Malformed date accepted: ' . $field); }
    }
}
if (App\Services\RentalServiceRequests::errors($fields) !== []) { throw new RuntimeException('Valid dates rejected.'); }
echo "PASS: malformed/null-byte dates return field validation errors; valid dates accepted. No database writes.\n";
