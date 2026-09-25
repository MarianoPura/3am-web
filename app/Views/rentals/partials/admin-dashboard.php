<?php
$metrics = is_array($metrics ?? null) ? $metrics : [];
$recentOrders = is_array($recentOrders ?? null) ? $recentOrders : [];
$upcomingRentals = is_array($upcomingRentals ?? null) ? $upcomingRentals : [];
?>
<?php if (in_array(config('app.env'), ['local', 'testing'], true)): ?>
  <p class="rentals-admin__empty">Local [TEST] records are excluded from Dashboard totals.</p>
<?php endif ?>
<div class="rentals-admin__bento">
  <?php foreach ($metrics as $label => $value): ?>
    <?php $money = in_array($label, ['Total rental sales', 'Average transaction value'], true); ?>
    <article class="rentals-admin__metric<?= $label === 'Total rental sales' ? ' rentals-admin__metric--feature' : '' ?>">
      <p><?= e($label) ?></p>
      <strong><?= $money ? '₱' . e(number_format((float) $value, 2)) : e(number_format((int) $value)) ?></strong>
      <?php if ($label === 'Total rental sales'): ?><small>Approved rental subtotal · deposits excluded</small><?php endif ?>
    </article>
  <?php endforeach ?>
</div>
<div class="rentals-admin__overview-grid">
  <section class="rentals-admin__panel" aria-labelledby="recent-orders-heading">
    <div class="rentals-admin__heading"><div><p class="rentals-card__meta">Latest activity</p><h2 id="recent-orders-heading">Recent orders</h2></div><a href="<?= e_attr(url('rentals/admin/orders')) ?>">View all →</a></div>
    <?php if ($recentOrders === []): ?><p class="rentals-admin__empty">No orders yet.</p><?php else: ?>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr><th>Order</th><th>Customer</th><th>Method</th><th>Status</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($recentOrders as $order): ?><tr><td><a href="<?= e_attr(url('rentals/admin/orders?edit=' . (int) $order['id'])) ?>"><?= e((string) $order['order_number']) ?></a><small><?= e((string) $order['created_at']) ?></small></td><td><?= e((string) $order['customer_name']) ?></td><td><?= e((string) ($order['payment_method'] ?? '—')) ?></td><td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $order['payment_status']]) ?></td><td>₱<?= e(number_format((float) $order['total_amount'], 2)) ?></td></tr><?php endforeach ?>
      </tbody></table></div>
    <?php endif ?>
  </section>
  <section class="rentals-admin__panel" aria-labelledby="upcoming-rentals-heading">
    <div class="rentals-admin__heading"><div><p class="rentals-card__meta">Scheduled equipment</p><h2 id="upcoming-rentals-heading">Upcoming rentals</h2></div></div>
    <?php if ($upcomingRentals === []): ?><p class="rentals-admin__empty">No upcoming rentals.</p><?php else: ?>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr><th>Item</th><th>Customer</th><th>Dates</th><th>Qty</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($upcomingRentals as $row): ?><tr><td><a href="<?= e_attr(url('rentals/admin/orders?edit=' . (int) $row['id'])) ?>"><?= e((string) $row['item_name']) ?></a></td><td><?= e((string) $row['customer_name']) ?></td><td><?= e((string) $row['rental_start_date']) ?> – <?= e((string) $row['rental_end_date']) ?></td><td><?= (int) $row['quantity'] ?></td><td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $row['payment_status']]) ?></td></tr><?php endforeach ?>
      </tbody></table></div>
    <?php endif ?>
  </section>
</div>
<div class="rentals-admin__quick-actions"><a href="<?= e_attr(url('rentals/admin/items')) ?>">Manage products <span aria-hidden="true">↗</span></a><a href="<?= e_attr(url('rentals/admin/orders')) ?>">Review orders <span aria-hidden="true">↗</span></a></div>
