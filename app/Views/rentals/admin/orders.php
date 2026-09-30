<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Orders — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Orders</h1></div>
      <p>Review and manage rental requests.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-admin__list">
      <div class="rentals-admin__heading">
        <div><p class="rentals-card__meta">Records</p><h2>Rental requests</h2></div>
      </div>
      <form class="rentals-admin__filter rentals-admin__filter--orders" method="get" action="<?= e_attr(url('rentals/admin/orders')) ?>">
        <label><span class="sr-only">Search orders</span><input type="search" name="q" value="<?= e_attr((string) ($term ?? '')) ?>" placeholder="Order number or customer"></label>
        <label><span class="sr-only">Filter payment status</span><select name="status"><option value="all">All statuses</option><?php foreach ([0, 1, 2] as $status): ?><option value="<?= $status ?>"<?= (string) ($statusFilter ?? 'all') === (string) $status ? ' selected' : '' ?>><?= e(\App\Services\RentalPaymentStatus::label($status)) ?></option><?php endforeach ?></select></label>
        <button class="rentals-btn rentals-btn--dark" type="submit">Filter</button>
      </form>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr>
        <th scope="col">Order / date</th><th scope="col">Customer</th><th scope="col">Amount</th><th scope="col">Payment method</th><th scope="col">Payment status</th><th scope="col">Action</th>
      </tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <td><strong><?= e((string) $row['order_number']) ?></strong><small><?= e((string) $row['created_at']) ?></small></td>
          <td><?= e((string) $row['customer_name']) ?><small><?= e((string) $row['customer_email']) ?></small></td>
          <td>₱<?= e(number_format((float) $row['total_amount'], 2)) ?></td>
          <td><?= e((string) ($row['payment_method'] ?? '—')) ?></td>
          <td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $row['payment_status']]) ?></td>
          <td><a href="<?= e_attr(url('rentals/admin/orders/' . (int) $row['id'])) ?>"><?= (int) $row['payment_status'] === 0 ? 'Review payment' : 'View order' ?> →</a></td>
        </tr><?php endforeach ?>
      </tbody></table></div>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php elseif (count($rows) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 records.</p><?php endif ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
