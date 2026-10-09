<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Service Requests — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page"><div class="rentals-shell">
  <div class="rentals-admin-page__heading"><div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Service requests</h1></div><p>Review event scope, crew and technical requirements. Quotations are separate from equipment/payment orders.</p></div>
  <?php if (!empty($unavailable)): ?><div class="rentals-admin__notice" role="alert">Service requests are temporarily unavailable. Contact your administrator.</div><?php else: ?>
    <div class="rentals-admin__list">
      <form method="get" action="<?= e_attr(url('rentals/admin/service-requests')) ?>" class="rentals-admin__filter rentals-admin__filter--orders">
        <label><span class="sr-only">Search service requests</span><input type="search" name="q" maxlength="100" value="<?= e_attr($term) ?>" placeholder="Reference, service or customer"></label>
        <label><span class="sr-only">Request status</span><select name="status"><option value="all">All statuses</option><?php foreach (\App\Services\RentalServiceRequests::STATUSES as $key => $label): ?><option value="<?= e_attr($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
        <button type="submit" class="rentals-btn rentals-btn--dark">Filter</button>
      </form>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr><th scope="col">Reference / service</th><th scope="col">Customer</th><th scope="col">Event dates</th><th scope="col">Request status</th><th scope="col">Action</th></tr></thead><tbody>
        <?php foreach ($rows as $record): ?><tr>
          <td><strong><?= e($record['reference']) ?></strong><small><?= e($record['service_name']) ?></small></td><td><?= e($record['customer_name']) ?><small><?= e($record['customer_email']) ?></small></td>
          <td><?= e($record['event_start_date']) ?> – <?= e($record['event_end_date']) ?></td><td><span class="rentals-payment-badge rentals-payment-badge--<?= e_attr($record['status']) ?>"><?= e(\App\Services\RentalServiceRequests::STATUSES[$record['status']] ?? 'Pending') ?></span></td>
          <td><a href="<?= e_attr(url('rentals/admin/service-requests/' . (int) $record['id'])) ?>"><?= $record['status'] === 'pending' ? 'Review request' : 'View request' ?> →</a></td>
        </tr><?php endforeach ?>
      </tbody></table></div>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No service requests match this view.</p><?php endif ?>
    </div>
  <?php endif ?>
</div></section>
<?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>'rentals/admin/service-requests']) ?>
<?php $this->end() ?>
