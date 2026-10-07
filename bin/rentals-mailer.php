<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$command=$argv[1]??'--check';
if ($command==='--sql') { echo file_get_contents(dirname(__DIR__).'/docs/rentals-notification-ledger.sql'); exit; }
if ($command!=='--check') { fwrite(STDERR,"Use --check or --sql. Review/apply additive SQL explicitly; this tool does not run DDL.\n"); exit(1); }
$container=require dirname(__DIR__).'/bootstrap.php';
try {
    $inspection=(new App\Services\RentalSchema($container->get(App\Core\Database::class)))->inspect(['rental_notification_deliveries']);
    foreach($inspection['issues'] as $issue) { fwrite(STDERR,"Review: $issue\n"); }
    if($inspection['issues']!==[]) { exit(1); }
    echo "PASS: mailer delivery ledger and unique delivery key. No changes made.\n";
} catch(Throwable $e) { fwrite(STDERR,"Mailer schema check failed. Verify database permissions/schema privately.\n"); exit(1); }
