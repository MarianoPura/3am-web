<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Payment Methods — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Payment Methods</h1></div>
      <p>Review and manage payment methods.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-admin__list">
      <div class="rentals-admin__heading">
        <div><p class="rentals-card__meta">Records</p><h2>Configured methods</h2></div>
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/admin/payments/new')) ?>">+ Add payment method</a>
      </div>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr>
        <th scope="col">Method</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Action</th>
      </tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <td><strong><?= e((string) $row['name']) ?></strong></td>
          <td><?= e((string) $row['type']) ?></td>
          <td><span class="rentals-admin__status<?= (int) $row['is_active'] === 1 ? '' : ' rentals-admin__status--off' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
          <td><a href="<?= e_attr(url('rentals/admin/payments/' . (int) $row['id'] . '/edit')) ?>">Edit →</a></td>
        </tr><?php endforeach ?>
      </tbody></table></div>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php elseif (count($rows) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 records.</p><?php endif ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
