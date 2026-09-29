<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Customers — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Customers</h1></div>
      <p>Customer accounts are shown for reference. Account details are read-only here.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-admin__list">
      <div class="rentals-admin__heading">
        <div><p class="rentals-card__meta">Read-only</p><h2>Account directory</h2></div>
      </div>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr>
        <th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Last login</th>
      </tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <td><strong><?= e((string) $row['name']) ?></strong></td>
          <td><?= e((string) $row['email']) ?></td>
          <td><?= e((string) $row['role']) ?></td>
          <td><?= e((string) ($row['last_login'] ?? 'Never')) ?></td>
        </tr><?php endforeach ?>
      </tbody></table></div>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php elseif (count($rows) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 records.</p><?php endif ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
