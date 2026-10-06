<?php
$item = $item ?? ($record ?? []);
$itemId = (int) ($item['id'] ?? 0);
?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Edit Product — Rentals Admin<?php $this->end() ?>
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
        <p class="rentals-card__meta">Existing record</p>
        <h2>Edit <?= e((string) ($item['name'] ?? 'Product')) ?></h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/items/' . $itemId . '/edit')) ?>" class="rentals-admin__form" data-rental-product-form enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e_attr((string) $itemId) ?>">
        <fieldset>
          <legend><span data-product-label data-equipment-text="Rental type and capacity" data-service-text="Service type">Rental type and capacity</span></legend>
          <div class="rentals-admin__two">
            <label>Rental type
              <select data-rental-type disabled>
                <option value="0"<?= (int) ($item['is_service'] ?? 0) === 0 ? ' selected' : '' ?>>Equipment</option>
                <option value="1"<?= (int) ($item['is_service'] ?? 0) === 1 ? ' selected' : '' ?>>Service</option>
              </select>
              <small>Type is preserved on existing records to protect inventory and bookings.</small>
            </label>
            <input type="hidden" name="is_service" value="<?= e_attr((string) (int) ($item['is_service'] ?? 0)) ?>">
            <label class="rentals-admin__quantity" data-equipment-field>Available quantity
              <input type="number" min="0" max="999999" step="1" name="available_quantity" required value="<?= e_attr((string) ($item['available_quantity'] ?? '0')) ?>">
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
                <option value="<?= e_attr((string) $category['id']) ?>"<?= (int) ($item['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
              <?php endforeach ?>
            </select>
          </label>
          <label><span data-product-label data-equipment-text="Name" data-service-text="Service name">Name</span>
            <input name="name" data-product-placeholder data-equipment-placeholder="e.g. Sony A7 III Camera" data-service-placeholder="e.g. Event coverage with camera crew" maxlength="190" required value="<?= e_attr((string) ($item['name'] ?? '')) ?>">
          </label>
          <div class="rentals-admin__two">
            <label>Slug (optional)
              <input name="slug" data-product-placeholder data-equipment-placeholder="e.g. sony-a7-iii" data-service-placeholder="e.g. event-production-coverage" maxlength="190" placeholder="e.g. sony-a7-iii" value="<?= e_attr((string) ($item['slug'] ?? '')) ?>">
              <small><span data-product-label data-equipment-text="Used in product URL. Leave blank to generate automatically." data-service-text="Used in the service URL. Leave blank to generate automatically.">Used in product URL. Leave blank to generate automatically.</span></small>
            </label>
            <label><span data-product-label data-equipment-text="SKU (optional)" data-service-text="Service code (optional)">SKU (optional)</span>
              <input name="sku" data-product-placeholder data-equipment-placeholder="e.g. CAM-001" data-service-placeholder="e.g. SRV-001" maxlength="80" placeholder="e.g. CAM-001" value="<?= e_attr((string) ($item['sku'] ?? '')) ?>">
              <small><span data-product-label data-equipment-text="Unique inventory code." data-service-text="Optional reference for this service.">Unique inventory code.</span></small>
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend><span data-product-label data-equipment-text="Product details" data-service-text="Service scope">Product details</span></legend>
          <label><span data-product-label data-equipment-text="Description" data-service-text="Coverage, crew and equipment">Description</span>
            <textarea name="description" data-product-placeholder data-equipment-placeholder="Product features and overview..." data-service-placeholder="Describe the event coverage, camera operators, lighting/audio support, crew roles, duration and deliverables." rows="3" maxlength="5000"><?= e((string) ($item['description'] ?? '')) ?></textarea>
          </label>
          <label><span data-product-label data-equipment-text="Ideal use" data-service-text="Event / project types">Ideal use</span>
            <input name="ideal_use" data-product-placeholder data-equipment-placeholder="e.g. Weddings, corporate videos, studio shoots" data-service-placeholder="e.g. Corporate events, weddings, livestream productions" maxlength="500" value="<?= e_attr((string) ($item['ideal_use'] ?? '')) ?>">
          </label>
          <?php $productImage = \App\Models\RentalCatalog::imagePath($item['image_path'] ?? null); if ($productImage !== null): ?>
            <img class="rentals-admin__image-preview" src="<?= e_attr(\App\Models\RentalCatalog::imageUrl($productImage)) ?>" alt="Current product image">
          <?php endif ?>
          <label>Replace image (optional)
            <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG or WebP file.</small>
          </label>
        </fieldset>
        <fieldset>
          <legend><span data-product-label data-equipment-text="Rental settings" data-service-text="Service pricing">Rental settings</span></legend>
          <div class="rentals-admin__two">

            <label data-equipment-field>Availability
              <select name="availability_status">
                <?php foreach (['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'] as $status): ?>
                  <option value="<?= e_attr($status) ?>"<?= ($item['availability_status'] ?? 'available') === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach ?>
              </select>
            </label>
          </div>
          <div class="rentals-admin__two">
            <label><span data-product-label data-equipment-text="Rental unit" data-service-text="Pricing basis">Rental unit</span>
              <input name="rental_unit" data-product-placeholder data-equipment-placeholder="e.g. day, hour, event" data-service-placeholder="e.g. event, project, day" maxlength="30" value="<?= e_attr((string) ($item['rental_unit'] ?? 'day')) ?>">
            </label>

          </div>
          <div class="rentals-admin__two">
            <label><span data-product-label data-equipment-text="Rate (₱)" data-service-text="Starting price (₱)">Rate (₱)</span>
              <input type="number" min="0" max="9999999999.99" step="0.01" name="rental_rate" required value="<?= e_attr((string) ($item['rental_rate'] ?? '0.00')) ?>">
            </label>
            <label data-equipment-field>Security deposit (₱, optional)
              <input type="number" min="0" max="9999999999.99" step="0.01" name="security_deposit" value="<?= e_attr((string) ($item['security_deposit'] ?? '0.00')) ?>">
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1"<?= (int) ($item['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option>
              <option value="0"<?= (int) ($item['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Save changes</button>
      </form>

      <?php if ((int) ($item['is_service'] ?? 0) === 0): ?>
        <details class="rentals-admin__blackouts" id="equipment-availability" data-admin-availability data-availability-url="<?= e_attr(url('rentals/admin/items/' . $itemId . '/availability')) ?>">
          <summary>Manage availability <span aria-hidden="true">＋</span></summary>
          <p>Select a day or range to block maintenance or internal-use dates. Removing a manual block never removes a customer reservation.</p>
          <div class="rentals-admin-calendar">
            <div class="rentals-admin-calendar__head"><button type="button" data-admin-prev aria-label="Previous availability month">←</button><strong data-admin-month></strong><button type="button" data-admin-next aria-label="Next availability month">→</button></div>
            <div class="rentals-admin-calendar__weekdays" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
            <div class="rentals-admin-calendar__days" data-admin-days></div>
            <p class="rentals-admin-calendar__legend"><span>Available</span><span class="is-reserved">Customer reserved</span><span class="is-blocked">Admin blocked</span><span class="is-past">Past / unavailable</span></p>
            <p>Past dates cannot be rented. You can still select them to manage historical manual blocks.</p>
            <p data-admin-calendar-message role="status">Select dates to create a manual block.</p>
            <button type="button" class="rentals-admin-calendar__clear" data-admin-clear>Clear selection</button>
          </div>
          <form method="post" action="<?= e_attr(url('rentals/admin/items/' . $itemId . '/blackouts')) ?>" class="rentals-admin__blackout-form"><?= csrf_field() ?>
            <fieldset class="rentals-admin__availability-mode">
              <legend>Mode</legend>
              <label><input type="radio" name="availability_action" value="blocked" checked>Block dates</label>
              <label><input type="radio" name="availability_action" value="available">Remove block</label>
            </fieldset>
            <label>From<input type="date" name="start_date" required></label>
            <label>Through<input type="date" name="end_date" required></label>
            <label data-admin-block-note>Note<input name="note" maxlength="255" placeholder="Maintenance, internal use…"></label>
            <button class="rentals-btn rentals-btn--dark" type="submit" data-admin-block-submit>Block dates</button>
          </form>
          <h4>Manual blocks</h4>
          <?php if (($blackouts ?? []) === []): ?><p>No manual blocks yet.</p><?php endif ?>
          <?php foreach (($blackouts ?? []) as $blackout): ?>
            <div class="rentals-admin__blackout-row">
              <span><strong><?= e((string) $blackout['start_date']) ?> – <?= e((string) $blackout['end_date']) ?></strong> <?= e((string) ($blackout['note'] ?? '')) ?> · <?= (int) $blackout['is_active'] === 1 ? 'Blocked' : 'Unblocked' ?></span>
              <form method="post" action="<?= e_attr(url('rentals/admin/items/' . $itemId . '/blackouts/' . (int) $blackout['id'] . '/toggle')) ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= (int) $blackout['is_active'] === 1 ? 'Remove manual block' : 'Block again' ?></button>
              </form>
            </div>
          <?php endforeach ?>
        </details>
      <?php endif ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
