<?php
declare(strict_types=1);
$_ENV['MAIL_ENABLED']='false';require dirname(__DIR__).'/bootstrap.php';
$chartTimezone='Database time (UTC+08:00)';
$check=static function(bool $ok,string $message):void { if(!$ok){throw new RuntimeException($message);} };
foreach(['day'=>['2026-09-30','2026-10-01','2026-10-02'],'month'=>['2025-12-01','2026-01-01','2026-02-01'],'year'=>['2024-01-01','2025-01-01','2026-01-01']] as $aggregation=>$periods) {
    $daily=array_map(static fn($period)=>['period'=>$period,'sales'=>'1250.50','orders'=>3],$periods);
    $maxSales=1250.50;$maxOrders=3;$filters=['from'=>$periods[0],'to'=>end($periods)];
    foreach(['sales','orders'] as $chartType) {
        ob_start();require dirname(__DIR__).'/app/Views/rentals/partials/admin-time-chart.php';$html=ob_get_clean();
        $check(str_contains($html,'data-chart-period') && str_contains($html,'aria-live="polite"'),'Chart selector/accessibility missing.');
        $check(substr_count($html,'<option ')===3,'Chart lost date buckets.');
        $check(str_contains($html,'value="'.$periods[0].'"') && str_contains($html,'value="'.end($periods).'"'),'Calendar dates shifted.');
        $check(str_contains($html,$chartType==='sales'?'₱1,250.50':'3 transactions'),'Exact value missing.');
        $check(str_contains($html,'By order submission '.$aggregation),'Aggregation label missing.');
        $check(str_contains($html,$chartTimezone),'Database time basis missing.');
    }
}
$aggregation='day';$daily=[['period'=>'2026-10-07','sales'=>0,'orders'=>1]];$filters=['from'=>'2026-10-07','to'=>'2026-10-07'];$maxSales=0;$maxOrders=1;
foreach(['sales','orders'] as $chartType){ob_start();require dirname(__DIR__).'/app/Views/rentals/partials/admin-time-chart.php';$html=ob_get_clean();$check(!str_contains($html,'NAN')&&!str_contains($html,'INF'),'One-day/zero-value scale failed.');}
$bad=['id'=>['Extra'=>''],'sku'=>['Null'=>'NO','Type'=>'varchar(10)'],'rental_rate'=>['Type'=>'decimal(5,1)']];
$check(count(App\Services\RentalSchema::columnIssues('rental_items',$bad))>=4,'Schema checker lost type/nullability protections.');
echo "PASS: revenue/transactions date axes; day/month/year and year boundaries; exact selectors/values; database time basis; one-day/zero-value ranges; schema type/nullability checks.\n";
