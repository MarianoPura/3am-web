<?php
// Shared date axis for both persisted-order charts. Dates are formatted in PHP,
// so UTC parsing in the browser cannot shift a calendar day.
$chartId = 'rental-chart-' . $chartType;
$chartMaximum = $chartType === 'sales' ? max(1.0, $maxSales) : (float)$maxOrders;
$plotLeft=82; $plotRight=580; $plotTop=18; $plotBottom=192;
$chartX=static fn(int $i):float => count($daily)===1 ? ($plotLeft+$plotRight)/2 : $plotLeft+($plotRight-$plotLeft)*$i/(count($daily)-1);
$chartY=static fn(float $value):float => $plotBottom-($plotBottom-$plotTop)*$value/$chartMaximum;
$formatPeriod=static function(string $value,bool $short=false)use($aggregation):string {
    $date=new \DateTimeImmutable($value);
    return $date->format(match($aggregation){'year'=>'Y','month'=>$short?'M Y':'F Y',default=>$short?'M j':'M j, Y'});
};
$ticks=[];
for($i=0;$i<min(5,count($daily));$i++) { $ticks[]=count($daily)===1 ? 0 : (int)round($i*(count($daily)-1)/(min(5,count($daily))-1)); }
$chartPoints=[];$chartSelected=max(0,count($daily)-1);
foreach($daily as $i=>$row) {
    $value=(float)$row[$chartType==='sales'?'sales':'orders'];
    $chartPoints[]=round($chartX($i),2).','.round($chartY($value),2);
    if($value>0){$chartSelected=$i;}
}
$chartSummary=static fn(array $row):string => $formatPeriod((string)$row['period']).' · '.($chartType==='sales'?'₱'.number_format((float)$row['sales'],2):(int)$row['orders'].' transactions');
?>
<div class="rentals-time-chart" data-rental-time-chart>
  <svg viewBox="0 0 600 232" class="rentals-time-chart__plot" role="img" aria-labelledby="<?= e_attr($chartId) ?>-title <?= e_attr($chartId) ?>-description">
    <title id="<?= e_attr($chartId) ?>-title"><?= $chartType==='sales'?'Approved rental revenue':'Transactions' ?> by <?= e($aggregation) ?></title>
    <desc id="<?= e_attr($chartId) ?>-description"><?= e($formatPeriod((string)$filters['from'])) ?> through <?= e($formatPeriod((string)$filters['to'])) ?>. Use the date selector below for exact values.</desc>
    <?php foreach(array_unique([0,$chartType==='sales'?$chartMaximum/2:ceil($chartMaximum/2),$chartMaximum],SORT_NUMERIC) as $tick): $y=$chartY((float)$tick); ?>
      <line x1="<?= $plotLeft ?>" x2="<?= $plotRight ?>" y1="<?= $y ?>" y2="<?= $y ?>" class="rentals-time-chart__grid" />
      <text x="72" y="<?= $y+4 ?>" text-anchor="end" class="rentals-time-chart__label"><?= e($chartType==='sales'?'₱'.($tick>=1000000?round($tick/1000000,1).'m':($tick>=1000?round($tick/1000,1).'k':number_format($tick,$tick>0 && $tick<1 ? 2 : 0))):(string)(int)$tick) ?></text>
    <?php endforeach ?>
    <?php if($chartType==='sales'): ?>
      <polyline points="<?= e_attr(implode(' ',$chartPoints)) ?>" class="rentals-time-chart__line" />
    <?php endif ?>
    <?php foreach($daily as $i=>$row): $value=(float)$row[$chartType==='sales'?'sales':'orders']; $x=$chartX($i); $y=$chartY($value); ?>
      <?php if($chartType==='sales'): ?>
        <circle cx="<?= $x ?>" cy="<?= $y ?>" r="<?= count($daily)<=31?'3':'2' ?>" class="rentals-time-chart__point"><title><?= e($chartSummary($row)) ?></title></circle>
      <?php else: $barWidth=max(.5,min(32,($plotRight-$plotLeft)/max(1,count($daily))*0.7)); ?>
        <rect x="<?= $x-$barWidth/2 ?>" y="<?= $y ?>" width="<?= $barWidth ?>" height="<?= $plotBottom-$y ?>" class="rentals-time-chart__bar"><title><?= e($chartSummary($row)) ?></title></rect>
      <?php endif ?>
    <?php endforeach ?>
    <?php foreach($ticks as $index=>$i): ?>
      <text x="<?= $chartX($i) ?>" y="216" text-anchor="<?= $index===0?'start':($index===count($ticks)-1?'end':'middle') ?>" class="rentals-time-chart__label<?= $index%2===1?' rentals-time-chart__label--minor':'' ?>"><?= e($formatPeriod((string)$daily[$i]['period'],true)) ?></text>
    <?php endforeach ?>
  </svg>
  <div class="rentals-time-chart__inspect">
    <label for="<?= e_attr($chartId) ?>-date">View <?= e($aggregation) ?><select id="<?= e_attr($chartId) ?>-date" data-chart-period>
      <?php foreach($daily as $i=>$row): ?><option value="<?= e_attr((string)$row['period']) ?>" data-summary="<?= e_attr($chartSummary($row)) ?>"<?= $i===$chartSelected?' selected':'' ?>><?= e($formatPeriod((string)$row['period'])) ?></option><?php endforeach ?>
    </select></label>
    <output for="<?= e_attr($chartId) ?>-date" data-chart-value aria-live="polite"><?= e($chartSummary($daily[$chartSelected])) ?></output>
  </div>
  <p class="rentals-admin__chart-caption"><?= e((new \DateTimeImmutable((string)$filters['from']))->format('M j, Y')) ?> – <?= e((new \DateTimeImmutable((string)$filters['to']))->format('M j, Y')) ?> · By order submission <?= e($aggregation) ?> · <?= e($chartTimezone ?? 'Database time') ?></p>
</div>
