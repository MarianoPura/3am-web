<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Products — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Products</h1></div>
      <p>Review and manage products.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-admin__list">
      <div class="rentals-admin__heading">
        <div><p class="rentals-card__meta">Records</p><h2>Catalogue</h2></div>
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/admin/items/new')) ?>">+ Add product</a>
      </div>
      <form class="rentals-admin__filter" method="get" action="<?= e_attr(url('rentals/admin/items')) ?>">
        <label><span class="sr-only">Search products</span><input type="search" name="q" value="<?= e_attr((string) ($term ?? '')) ?>" placeholder="Search name or SKU"></label>
        <label><span class="sr-only">Filter category</span><select name="category"><option value="0">All categories</option><?php foreach ($categories as $category): ?><option value="<?= e_attr((string) $category['id']) ?>"<?= (int) ($categoryFilter ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach ?></select></label>
        <label><span class="sr-only">Filter type</span><select name="type"><?php foreach (['all' => 'All types', 'equipment' => 'Equipment', 'service' => 'Services'] as $value => $label): ?><option value="<?= e_attr($value) ?>"<?= ($typeFilter ?? 'all') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
        <button class="rentals-btn rentals-btn--dark" type="submit">Filter</button>
      </form>
      <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr>
        <th scope="col">Product</th><th scope="col">Category</th><th scope="col">Rate</th><th scope="col">Status</th><th scope="col">Action</th>
      </tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <td><strong><?= e((string) $row['name']) ?></strong><small><?= (int) $row['is_service'] === 1 ? 'Service' : 'Equipment' ?> · <?= e((string) ($row['sku'] ?? 'No SKU')) ?></small></td>
          <td><?= e((string) $row['category_name']) ?></td>
          <td>₱<?= e(number_format((float) $row['rental_rate'], 2)) ?></td>
          <td><span class="rentals-admin__status<?= (int) $row['is_active'] === 1 ? '' : ' rentals-admin__status--off' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <a href="<?= e_attr(url('rentals/admin/items/' . (int) $row['id'] . '/edit')) ?>">Edit →</a>
            <?php if ((int) $row['is_service'] === 0): ?><a href="<?= e_attr(url('rentals/admin/items/' . (int) $row['id'] . '/edit#equipment-availability')) ?>">Manage availability →</a><?php endif ?>
          </td>
        </tr><?php endforeach ?>
      </tbody></table></div>
      <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php elseif (count($rows) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 records.</p><?php endif ?>
    </div>
    <?php if ($images !== []): ?><datalist id="rental-images"><?php foreach ($images as $image): ?><option value="<?= e_attr($image) ?>"></option><?php endforeach ?></datalist><?php endif ?>
  </div>
</section>
<?php $this->end() ?>
