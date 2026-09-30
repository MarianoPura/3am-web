<?php
$draft = $draft ?? [];
?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Add Product — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Products</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?>
      <p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>>
        <?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?>
      </p>
    <?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/items')) ?>">← Back to products</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta">New record</p>
        <h2>Add product</h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/items')) ?>" class="rentals-admin__form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Basic information</legend>
          <label>Category
            <select name="category_id" required>
              <option value="">Choose category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= e_attr((string) $category['id']) ?>"<?= (string) ($draft['category_id'] ?? '') === (string) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
              <?php endforeach ?>
            </select>
          </label>
          <label>Name
            <input name="name" maxlength="190" required placeholder="e.g. Sony A7 III Camera" value="<?= e_attr((string) ($draft['name'] ?? '')) ?>">
          </label>
          <div class="rentals-admin__two">
            <label>Slug (optional)
              <input name="slug" maxlength="190" placeholder="e.g. sony-a7-iii" value="<?= e_attr((string) ($draft['slug'] ?? '')) ?>">
              <small>Used in product URL. Leave blank to generate automatically.</small>
            </label>
            <label>SKU (optional)
              <input name="sku" maxlength="80" placeholder="e.g. CAM-001" value="<?= e_attr((string) ($draft['sku'] ?? '')) ?>">
              <small>Unique inventory code.</small>
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend>Product details</legend>
          <label>Description
            <textarea name="description" rows="3" maxlength="5000" placeholder="Product features and overview..."><?= e((string) ($draft['description'] ?? '')) ?></textarea>
          </label>
          <label>Ideal use
            <input name="ideal_use" maxlength="500" placeholder="e.g. Weddings, corporate videos, studio shoots" value="<?= e_attr((string) ($draft['ideal_use'] ?? '')) ?>">
          </label>
          <label>Product image (optional)
            <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG or WebP file.</small>
          </label>
        </fieldset>
        <fieldset>
          <legend>Rental settings</legend>
          <div class="rentals-admin__two">
            <label>Type
              <select name="is_service">
                <option value="0"<?= ($draft['is_service'] ?? '0') === '0' ? ' selected' : '' ?>>Equipment</option>
                <option value="1"<?= ($draft['is_service'] ?? '') === '1' ? ' selected' : '' ?>>Service</option>
              </select>
            </label>
            <label>Availability
              <select name="availability_status">
                <?php foreach (['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'] as $status): ?>
                  <option value="<?= e_attr($status) ?>"<?= ($draft['availability_status'] ?? 'available') === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach ?>
              </select>
            </label>
          </div>
          <div class="rentals-admin__two">
            <label>Rental unit
              <input name="rental_unit" maxlength="30" value="<?= e_attr((string) ($draft['rental_unit'] ?? 'day')) ?>" placeholder="e.g. day, hour, event">
            </label>
            <label>Available quantity
              <input type="number" min="0" max="999999" step="1" name="available_quantity" required value="<?= e_attr((string) ($draft['available_quantity'] ?? '1')) ?>">
            </label>
          </div>
          <div class="rentals-admin__two">
            <label>Rate (₱)
              <input type="number" min="0" max="9999999999.99" step="0.01" name="rental_rate" required placeholder="0.00" value="<?= e_attr((string) ($draft['rental_rate'] ?? '')) ?>">
            </label>
            <label>Security deposit (₱, optional)
              <input type="number" min="0" max="9999999999.99" step="0.01" name="security_deposit" placeholder="0.00" value="<?= e_attr((string) ($draft['security_deposit'] ?? '0.00')) ?>">
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1"<?= ($draft['is_active'] ?? '1') === '1' ? ' selected' : '' ?>>Active</option>
              <option value="0"<?= ($draft['is_active'] ?? '') === '0' ? ' selected' : '' ?>>Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Add product</button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
