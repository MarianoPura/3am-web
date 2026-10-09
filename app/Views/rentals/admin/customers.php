<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Customers — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Customers</h1></div>
      <p>Find customer and administrative accounts. Account details are read-only here.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-admin__list">
      <div class="rentals-admin__heading">
        <div><p class="rentals-card__meta">Read-only</p><h2>Account directory</h2></div>
      </div>
      <form class="rentals-admin__filter rentals-admin__filter--orders" method="get" action="<?= e_attr(url('rentals/admin/customers')) ?>">
        <label><span class="sr-only">Search accounts by name or email</span><input type="search" name="q" value="<?= e_attr((string) ($term ?? '')) ?>" placeholder="Search name or email" maxlength="100"></label>
        <label><span class="sr-only">Filter account type</span><select name="role"><?php foreach (['all' => 'All accounts', 'customer' => 'Customers', 'admin' => 'Admins and Superadmins'] as $value => $label): ?><option value="<?= e_attr($value) ?>"<?= ($roleFilter ?? 'all') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
        <button class="rentals-btn rentals-btn--dark" type="submit">Filter</button>
      </form>
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
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php endif ?>
    </div>
  </div>
</section>
<?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>'rentals/admin/customers']) ?>
<?php $this->end() ?>
