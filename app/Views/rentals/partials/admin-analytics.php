<?php
$insights = is_array($insights ?? null) ? $insights : [];
$filters = $insights['filters'] ?? [];
$kpis = $insights['kpis'] ?? [];
$daily = $insights['daily'] ?? [];
$chartTimezone=(string)($insights['timezone_label']??'Database time');
$maxSales = max([0.0, ...array_map(static fn (array $row): float => (float) $row['sales'], $daily)]);
$aggregation = (string) ($insights['aggregation'] ?? 'day');
$highestPeriod = $daily === [] ? null : array_values(array_filter($daily, static fn (array $row): bool => (float) $row['sales'] === $maxSales))[0];
$maxOrders = max([1, ...array_map(static fn (array $row): int => (int) $row['orders'], $daily)]);
$groups = [
    ['paymentStatuses', 'Payment status distribution', 'status', 'total'],
    ['topItems', 'Most rented equipment', 'name', 'units'],
    ['categories', 'Rental demand by category', 'name', 'units'],
    ['methods', 'Payment method usage', 'name', 'orders'],
    ['types', 'Equipment vs services', 'name', 'units'],
];
?>
<form method="get" class="rentals-admin__report-filter rentals-admin__report-filter--analytics" action="<?= e_attr(url('rentals/admin'). '#rental-analytics') ?>">
  <label>Period<select name="period"><?php foreach (['7d' => '7 days', '30d' => '30 days', 'month' => 'This month', 'year' => 'This year', 'custom' => 'Custom range'] as $key => $label): ?><option value="<?= e_attr($key) ?>"<?= ($filters['period'] ?? '30d') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
  <label>From<input type="date" name="from" value="<?= e_attr((string) ($filters['from'] ?? '')) ?>"></label>
  <label>To<input type="date" name="to" value="<?= e_attr((string) ($filters['to'] ?? '')) ?>"></label>
  <button class="rentals-btn rentals-btn--dark" type="submit">Apply filters</button>
</form>
<div class="rentals-admin__analytics-kpis">
  <article class="rentals-admin__metric"><p>Total rental sales</p><strong>₱<?= e(number_format((float) ($kpis['sales'] ?? 0), 2)) ?></strong><small>Approved subtotal; deposits excluded</small></article>
  <article class="rentals-admin__metric"><p>Total transactions</p><strong><?= e(number_format((int) ($kpis['orders'] ?? 0))) ?></strong></article>
  <article class="rentals-admin__metric"><p>Average transaction value</p><strong>₱<?= e(number_format((float) ($kpis['average_sale'] ?? 0), 2)) ?></strong><small>Approved orders</small></article>
</div>
<div class="rentals-admin__insight-grid">
  <section class="rentals-admin__panel"><p class="rentals-card__meta">Approved payments</p><h2>Rental revenue over time</h2>
    <?php if ((int) ($kpis['approved_orders'] ?? 0) === 0): ?><p class="rentals-admin__empty">No approved rental revenue in this period.</p><?php else: ?>
      <?php $chartType='sales'; require __DIR__.'/admin-time-chart.php'; ?>
      <p class="rentals-admin__chart-caption">Highest <?= e($aggregation) ?> <?= e((string)$highestPeriod['period']) ?>: ₱<?= e(number_format($maxSales,2)) ?></p>
      <p class="rentals-admin__chart-caption">Approved rental subtotals by order submission date; security deposits excluded. Dates without revenue are zero.</p>
    <?php endif ?>
  </section>
  <section class="rentals-admin__panel"><p class="rentals-card__meta">All payment states</p><h2>Transactions over time</h2>
    <?php if ((int) ($kpis['orders'] ?? 0) === 0): ?><p class="rentals-admin__empty">No transactions in this period.</p><?php else: ?>
      <?php $chartType='orders'; require __DIR__.'/admin-time-chart.php'; ?>
    <?php endif ?>
  </section>
</div>
<div class="rentals-admin__insight-grid">
  <?php foreach ($groups as [$key, $title, $nameField, $valueField]): ?>
    <?php $rows = $insights[$key] ?? []; $maximum = max([1, ...array_map(static fn (array $row): int => (int) $row[$valueField], $rows)]); ?>
    <section class="rentals-admin__panel"><p class="rentals-card__meta">Rental activity</p><h2><?= e($title) ?></h2>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No data in this period.</p><?php else: ?><div class="rentals-admin__rank-bars">
        <?php foreach ($rows as $row): ?><?php $label = $key === 'paymentStatuses' ? \App\Services\RentalPaymentStatus::label($row[$nameField]) : (string) $row[$nameField]; ?>
          <div><span><?= e($label) ?></span><div role="img" aria-label="<?= e_attr($label . ': ' . $row[$valueField]) ?>"><i style="width:<?= e_attr((string) round(100 * (int) $row[$valueField] / $maximum)) ?>%"></i></div><strong><?= (int) $row[$valueField] ?></strong></div>
        <?php endforeach ?>
      </div><?php endif ?>
    </section>
  <?php endforeach ?>
</div>
