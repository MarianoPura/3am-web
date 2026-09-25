<?php
$report = is_array($report ?? null) ? $report : [];
$filters = $report['filters'] ?? [];
$totals = $report['totals'] ?? [];
?>
<form method="get" class="rentals-admin__report-filter" action="<?= e_attr(url('rentals/admin/payment-report')) ?>">
  <label>From<input type="date" name="from" value="<?= e_attr((string) ($filters['from'] ?? '')) ?>"></label>
  <label>To<input type="date" name="to" value="<?= e_attr((string) ($filters['to'] ?? '')) ?>"></label>
  <label>Payment status<select name="payment_status"><option value="">All statuses</option><?php foreach ([0, 1, 2] as $value): ?><option value="<?= $value ?>"<?= (string) ($filters['payment_status'] ?? '') === (string) $value ? ' selected' : '' ?>><?= e(\App\Services\RentalPaymentStatus::label($value)) ?></option><?php endforeach ?></select></label>
  <label>Method<select name="method"><option value="0">All methods</option><?php foreach (($report['methods'] ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>"<?= (int) ($filters['method'] ?? 0) === (int) $row['id'] ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option><?php endforeach ?></select></label>
  <button class="rentals-btn rentals-btn--dark" type="submit">Apply filters</button>
</form>
<div class="rentals-admin__report-totals"><div><small>Matching orders</small><strong><?= e(number_format((int) ($totals['orders'] ?? 0))) ?></strong></div><div><small>Rental amount</small><strong>₱<?= e(number_format((float) ($totals['rental_amount'] ?? 0), 2)) ?></strong></div><div><small>Security deposits</small><strong>₱<?= e(number_format((float) ($totals['security_deposits'] ?? 0), 2)) ?></strong></div><div><small>Total collected</small><strong>₱<?= e(number_format((float) ($totals['total_collected'] ?? 0), 2)) ?></strong></div></div>
<div class="rentals-admin__panel"><div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr><th>Order / date</th><th>Customer</th><th>Method</th><th>Reference</th><th>Payment status</th><th>Reviewed at</th><th>Amount</th></tr></thead><tbody>
  <?php foreach (($report['rows'] ?? []) as $row): ?><tr><td><a href="<?= e_attr(url('rentals/admin/orders?edit=' . (int) $row['id'])) ?>"><?= e((string) $row['order_number']) ?></a><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['customer_name']) ?></td><td><?= e((string) ($row['payment_method'] ?? 'Unassigned')) ?></td><td><?= e((string) ($row['payment_reference'] ?? '—')) ?></td><td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $row['payment_status']]) ?></td><td><?= e((string) ($row['payment_reviewed_at'] ?? '—')) ?></td><td>₱<?= e(number_format((float) $row['total_amount'], 2)) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php if (($report['rows'] ?? []) === []): ?><p class="rentals-admin__empty">No payments match these filters.</p><?php elseif (count($report['rows']) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 matches; totals include all matches.</p><?php endif ?></div>
