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
        <h2 data-create-product-heading>Add equipment</h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/items')) ?>" class="rentals-admin__form" data-rental-product-form enctype="multipart/form-data">
        <?= csrf_field() ?>
        <fieldset>
          <legend><span data-product-label data-equipment-text="Rental type and capacity" data-service-text="Service type">Rental type and capacity</span></legend>
          <div class="rentals-admin__two">
            <label>Rental type
              <select data-rental-type disabled>
                <option value="0"<?= ($draft['is_service'] ?? '0') === '0' ? ' selected' : '' ?>>Equipment</option>
                <option value="1"<?= ($draft['is_service'] ?? '') === '1' ? ' selected' : '' ?>>Service</option>
              </select>
              <small>Type is determined by the selected category.</small>
            </label>
            <label class="rentals-admin__quantity" data-equipment-field>Available quantity
              <input type="number" min="0" max="999999" step="1" name="available_quantity" required value="<?= e_attr((string) ($draft['available_quantity'] ?? '1')) ?>">
              <small>Maximum number of units that can be rented for overlapping dates.</small>
            </label>
          </div>
        </fieldset>
        <p class="rentals-admin__notice" data-service-field hidden>Services describe work performed by 3AM, such as event coverage with cameras and crew, live production or technical support. Describe what is included below. Customer requests are discussed and confirmed with the team; services do not use equipment stock or Add to Cart.</p>
        <fieldset>
          <legend>Basic information</legend>
          <label>Category
            <select name="category_id" required>
              <option value="">Choose category</option>
              <?php foreach ($categories as $category): ?>
                <option data-is-service="<?= (int) $category['is_service'] ?>" value="<?= e_attr((string) $category['id']) ?>"<?= (string) ($draft['category_id'] ?? '') === (string) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
              <?php endforeach ?>
            </select>
          </label>
          <label><span data-product-label data-equipment-text="Name" data-service-text="Service name">Name</span>
            <input name="name" data-product-placeholder data-equipment-placeholder="e.g. Sony A7 III Camera" data-service-placeholder="e.g. Event coverage with camera crew" maxlength="190" required placeholder="e.g. Sony A7 III Camera" value="<?= e_attr((string) ($draft['name'] ?? '')) ?>">
          </label>
        </fieldset>
        <fieldset>
          <legend><span data-product-label data-equipment-text="Product details" data-service-text="Service scope">Product details</span></legend>
          <label><span data-product-label data-equipment-text="Description" data-service-text="Coverage, crew and equipment">Description</span>
            <textarea name="description" data-product-placeholder data-equipment-placeholder="Product features and overview..." data-service-placeholder="Describe the event coverage, camera operators, lighting/audio support, crew roles, duration and deliverables." rows="3" maxlength="5000" placeholder="Product features and overview..."><?= e((string) ($draft['description'] ?? '')) ?></textarea>
          </label>
          <label><span data-product-label data-equipment-text="Ideal use" data-service-text="Event / project types">Ideal use</span>
            <input name="ideal_use" data-product-placeholder data-equipment-placeholder="e.g. Weddings, corporate videos, studio shoots" data-service-placeholder="e.g. Corporate events, weddings, livestream productions" maxlength="500" placeholder="e.g. Weddings, corporate videos, studio shoots" value="<?= e_attr((string) ($draft['ideal_use'] ?? '')) ?>">
          </label>
        </fieldset>
        <?= $this->partial('rentals.partials.admin-gallery', ['galleryItem'=>$draft, 'galleryReady'=>$galleryReady??false]) ?>
        <fieldset>
          <legend><span data-product-label data-equipment-text="Rental settings" data-service-text="Service pricing">Rental settings</span></legend>
          <div class="rentals-admin__two">

            <label data-equipment-field>Availability
              <select name="availability_status">
                <?php foreach (['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'] as $status): ?>
                  <option value="<?= e_attr($status) ?>"<?= ($draft['availability_status'] ?? 'available') === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach ?>
              </select>
            </label>
          </div>
          <div class="rentals-admin__two">
            <label><span data-product-label data-equipment-text="Rental unit" data-service-text="Pricing basis">Rental unit</span>
              <input name="rental_unit" data-product-placeholder data-equipment-placeholder="e.g. day, hour, event" data-service-placeholder="e.g. event, project, day" maxlength="30" value="<?= e_attr((string) ($draft['rental_unit'] ?? 'day')) ?>" placeholder="e.g. day, hour, event">
            </label>

          </div>
          <div class="rentals-admin__two">
            <label><span data-product-label data-equipment-text="Rate (₱)" data-service-text="Starting price (₱)">Rate (₱)</span>
              <input type="number" min="0" max="9999999999.99" step="0.01" name="rental_rate" required placeholder="0.00" value="<?= e_attr((string) ($draft['rental_rate'] ?? '')) ?>">
            </label>
            <label data-equipment-field>Security deposit (₱, optional)
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
        <button class="rentals-btn rentals-btn--primary" type="submit" data-product-label data-equipment-text="Add equipment" data-service-text="Add service">Add equipment</button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
