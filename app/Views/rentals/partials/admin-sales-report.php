<?php
$report = is_array($report ?? null) ? $report : [];
$filters = $report['filters'] ?? [];
$totals = $report['totals'] ?? [];
?>
<form method="get" class="rentals-admin__report-filter" action="<?= e_attr(url('rentals/admin/sales-report')) ?>">
  <label>From<input type="date" name="from" value="<?= e_attr((string) ($filters['from'] ?? '')) ?>"></label>
  <label>To<input type="date" name="to" value="<?= e_attr((string) ($filters['to'] ?? '')) ?>"></label>
  <label>Category<select name="category"><option value="0">All categories</option><?php foreach (($report['categories'] ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>"<?= (int) ($filters['category'] ?? 0) === (int) $row['id'] ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option><?php endforeach ?></select></label>
  <label>Item<select name="item"><option value="0">All items</option><?php foreach (($report['items'] ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>"<?= (int) ($filters['item'] ?? 0) === (int) $row['id'] ? ' selected' : '' ?>><?= e((string) $row['name']) ?></option><?php endforeach ?></select></label>
  <button class="rentals-btn rentals-btn--dark" type="submit">Apply filters</button>
</form>
<div class="rentals-admin__report-totals"><div><small>Approved orders</small><strong><?= e(number_format((int) ($totals['orders'] ?? 0))) ?></strong></div><div><small>Approved rental sales · excludes deposits</small><strong>₱<?= e(number_format((float) ($totals['approved_sales'] ?? 0), 2)) ?></strong></div></div>
<div class="rentals-admin__panel"><div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr><th>Order / date</th><th>Customer</th><th>Items / rental details</th><th>Subtotal</th><th>Deposit</th><th>Total</th><th>Status</th></tr></thead><tbody>
  <?php foreach (($report['rows'] ?? []) as $row): ?><tr><td><a href="<?= e_attr(url('rentals/admin/orders?edit=' . (int) $row['id'])) ?>"><?= e((string) $row['order_number']) ?></a><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['customer_name']) ?></td><td><?= e((string) ($row['items'] ?? '')) ?><?php foreach (($row['details'] ?? []) as $detail): ?><small><?= e((string) $detail['item_name']) ?> × <?= e((string) $detail['quantity']) ?> · <?= e((string) $detail['rental_start_date']) ?> to <?= e((string) $detail['rental_end_date']) ?> · ₱<?= e(number_format((float) $detail['unit_rate'], 2)) ?> / ₱<?= e(number_format((float) $detail['line_total'], 2)) ?></small><?php endforeach ?></td><td>₱<?= e(number_format((float) $row['subtotal'], 2)) ?></td><td>₱<?= e(number_format((float) $row['security_deposit'], 2)) ?></td><td>₱<?= e(number_format((float) $row['total_amount'], 2)) ?></td><td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $row['payment_status']]) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php if (($report['rows'] ?? []) === []): ?><p class="rentals-admin__empty">No approved sales match these filters.</p><?php elseif (count($report['rows']) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 matches; totals include all matches.</p><?php endif ?></div>
