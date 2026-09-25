<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Rentals Admin — 3AM<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$section = (string) ($section ?? 'dashboard');
$rows = is_array($rows ?? null) ? $rows : [];
$record = is_array($record ?? null) ? $record : null;
$categories = is_array($categories ?? null) ? $categories : [];
$images = is_array($images ?? null) ? $images : [];
$details = is_array($details ?? null) ? $details : [];
$metrics = is_array($metrics ?? null) ? $metrics : [];
$recordId = (int) ($record['id'] ?? 0);
$showEditor = (bool) ($showEditor ?? false);
$sectionNames = ['dashboard' => 'Dashboard', 'analytics' => 'Analytics', 'sales-report' => 'Sales Report',
    'payment-report' => 'Payment Report', 'items' => 'Products', 'categories' => 'Categories',
    'orders' => 'Orders', 'payments' => 'Payment Methods', 'customers' => 'Customers'];
$sectionName = $sectionNames[$section] ?? 'Rentals Admin';
$listTitle = ['items' => 'Catalogue', 'categories' => 'Category list', 'orders' => 'Rental requests',
    'payments' => 'Configured methods', 'customers' => 'Account directory'][$section] ?? $sectionName;
$sectionUrl = url('rentals/admin/' . $section);
$newRecordLabel = ['items' => 'product', 'categories' => 'category', 'payments' => 'payment method'][$section] ?? 'record';
?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1><?= e($sectionName) ?></h1></div>
      <?php if ($section === 'dashboard'): ?><p>Manage rental products, requests, and checkout options from one place.</p>
      <?php elseif ($section === 'analytics'): ?><p>Track approved rental sales and equipment demand over time.</p>
      <?php elseif ($section === 'customers'): ?><p>Customer accounts are shown for reference. Account details are read-only here.</p>
      <?php elseif (!$showEditor): ?><p>Review and manage <?= e(strtolower($sectionName)) ?>.</p><?php endif ?>
    </div>

    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>

    <?php if ($section === 'dashboard'): ?>
      <?= $this->partial('rentals.partials.admin-dashboard', ['metrics' => $metrics, 'recentOrders' => $recentOrders ?? [], 'upcomingRentals' => $upcomingRentals ?? []]) ?>

    <?php elseif ($section === 'analytics'): ?>
      <?= $this->partial('rentals.partials.admin-analytics', ['insights' => $insights ?? []]) ?>

    <?php elseif ($section === 'sales-report'): ?>
      <?= $this->partial('rentals.partials.admin-sales-report', ['report' => $report ?? []]) ?>

    <?php elseif ($section === 'payment-report'): ?>
      <?= $this->partial('rentals.partials.admin-payment-report', ['report' => $report ?? []]) ?>

    <?php elseif ($showEditor): ?>
      <a class="rentals-admin__back-link" href="<?= e_attr($sectionUrl) ?>">← Back to <?= e(strtolower($sectionName)) ?></a>
      <div class="rentals-admin__editor">
        <div class="rentals-admin__editor-head">
          <p class="rentals-card__meta"><?= $recordId > 0 ? 'Existing record' : 'New record' ?></p>
          <h2><?= $section === 'orders' ? 'Order ' . e((string) ($record['order_number'] ?? '')) : ($recordId > 0 ? 'Edit ' . e((string) ($record['name'] ?? 'record')) : 'Add ' . e($newRecordLabel)) ?></h2>
        </div>
        <?php if ($section === 'orders' && $record !== null): ?>
          <div class="rentals-admin__order-facts">
            <dl>
              <div><dt>Customer</dt><dd><?= e((string) $record['customer_name']) ?><small><?= e((string) $record['customer_email']) ?><?php if (($record['customer_phone'] ?? '') !== ''): ?> · <?= e((string) $record['customer_phone']) ?><?php endif ?></small></dd></div>
              <div><dt>Payment method</dt><dd><?= e((string) ($record['payment_method'] ?? 'Not selected')) ?></dd></div>
              <div><dt>Payment status</dt><dd><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $record['payment_status']]) ?><?php if (($record['payment_reference'] ?? '') !== ''): ?><small>Reference: <?= e((string) $record['payment_reference']) ?></small><?php endif ?></dd></div>
              <div><dt>Submitted</dt><dd><?= e((string) $record['created_at']) ?></dd></div>
              <div><dt>Reviewed</dt><dd><?= e((string) ($record['payment_reviewed_at'] ?? 'Not yet')) ?></dd></div>
              <div><dt>Subtotal</dt><dd>₱<?= e(number_format((float) $record['subtotal'], 2)) ?></dd></div>
              <div><dt>Security deposit</dt><dd>₱<?= e(number_format((float) $record['security_deposit'], 2)) ?></dd></div>
              <div class="rentals-admin__order-total"><dt>Total</dt><dd>₱<?= e(number_format((float) $record['total_amount'], 2)) ?></dd></div>
            </dl>
            <?php if (($record['notes'] ?? '') !== ''): ?><p><strong>Customer notes:</strong> <?= e((string) $record['notes']) ?></p><?php endif ?>
          </div>
          <?php if (($record['payment_proof_path'] ?? '') !== ''): ?>
            <section class="rentals-admin__proof" aria-labelledby="proof-title">
              <div><p class="rentals-card__meta">Manual payment</p><h3 id="proof-title">Payment proof review</h3></div>
              <details class="rentals-admin__proof-detail"><summary>View uploaded proof</summary>
                <?php $proofUrl = url('rentals/admin/proof/' . $recordId); ?>
                <?php if (preg_match('/\.pdf(?:\.php)?$/', (string) $record['payment_proof_path'])): ?><a href="<?= e_attr($proofUrl) ?>" target="_blank" rel="noopener">Open PDF proof in a new tab</a>
                <?php else: ?><img src="<?= e_attr($proofUrl) ?>" loading="lazy" alt="Uploaded payment proof for order <?= e_attr((string) $record['order_number']) ?>"><?php endif ?>
              </details>
              <?php if ((int) $record['payment_status'] === \App\Services\RentalPaymentStatus::PENDING): ?><form method="post" action="<?= e_attr(url('rentals/admin/orders/' . $recordId . '/review')) ?>" class="rentals-admin__proof-actions"><?= csrf_field() ?>
                <button class="rentals-btn rentals-btn--primary" name="decision" value="approved" type="submit">Approve proof</button>
                <button class="rentals-btn rentals-btn--secondary" name="decision" value="rejected" type="submit">Reject proof</button>
              </form><?php endif ?>
            </section>
          <?php endif ?>
          <h3>Rented items</h3>
          <div class="rentals-admin__order-lines">
            <?php foreach ($details as $line): ?>
              <div class="rentals-admin__order-line">
                <strong><?= e((string) $line['item_name']) ?></strong>
                <span><?= e((string) $line['quantity']) ?> × ₱<?= e(number_format((float) $line['unit_rate'], 2)) ?> = ₱<?= e(number_format((float) $line['line_total'], 2)) ?></span>
                <small><?= e((string) ($line['rental_start_date'] ?? 'Date pending')) ?> → <?= e((string) ($line['rental_end_date'] ?? 'Date pending')) ?></small>
              </div>
            <?php endforeach ?>
          </div>
        <?php else: ?>
          <form method="post" action="<?= e_attr($sectionUrl) ?>" class="rentals-admin__form" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e_attr((string) $recordId) ?>">
            <?php if ($section === 'items'): ?>
              <fieldset><legend>Basic information</legend>
                <label>Category<select name="category_id" required><option value="">Choose category</option><?php foreach ($categories as $category): ?><option value="<?= e_attr((string) $category['id']) ?>"<?= (int) ($record['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach ?></select></label>
                <label>Name<input name="name" maxlength="190" required value="<?= e_attr((string) ($record['name'] ?? '')) ?>"></label>
                <div class="rentals-admin__two"><label>Slug<input name="slug" maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" required value="<?= e_attr((string) ($record['slug'] ?? '')) ?>"></label><label>SKU<input name="sku" maxlength="80" value="<?= e_attr((string) ($record['sku'] ?? '')) ?>"></label></div>
              </fieldset>
              <fieldset><legend>Product details</legend>
                <label>Description<textarea name="description" rows="3"><?= e((string) ($record['description'] ?? '')) ?></textarea></label>
                <label>Ideal use<input name="ideal_use" maxlength="500" value="<?= e_attr((string) ($record['ideal_use'] ?? '')) ?>"></label>
                <?php $productImage = \App\Models\RentalCatalog::imagePath($record['image_path'] ?? null); if ($productImage !== null): ?><img class="rentals-admin__image-preview" src="<?= e_attr(url($productImage)) ?>" alt="Current product image"><?php endif ?>
                <label><?= $recordId > 0 ? 'Replace image (optional)' : 'Upload product image (optional)' ?><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label>
              </fieldset>
              <fieldset><legend>Rental settings</legend>
                <div class="rentals-admin__two"><label>Type<select name="is_service"><option value="0"<?= (int) ($record['is_service'] ?? 0) === 0 ? ' selected' : '' ?>>Equipment</option><option value="1"<?= (int) ($record['is_service'] ?? 0) === 1 ? ' selected' : '' ?>>Service</option></select></label><label>Availability<select name="availability_status"><?php foreach (['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'] as $status): ?><option value="<?= e_attr($status) ?>"<?= ($record['availability_status'] ?? 'available') === $status ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach ?></select></label></div>
                <div class="rentals-admin__two"><label>Rental unit<input name="rental_unit" maxlength="30" value="<?= e_attr((string) ($record['rental_unit'] ?? 'day')) ?>"></label><label>Available quantity<input type="number" min="0" max="999999" name="available_quantity" required value="<?= e_attr((string) ($record['available_quantity'] ?? '0')) ?>"></label></div>
                <div class="rentals-admin__two"><label>Rate (₱)<input type="number" min="0" step="0.01" name="rental_rate" required value="<?= e_attr((string) ($record['rental_rate'] ?? '0.00')) ?>"></label><label>Security deposit (₱)<input type="number" min="0" step="0.01" name="security_deposit" required value="<?= e_attr((string) ($record['security_deposit'] ?? '0.00')) ?>"></label></div>
              </fieldset>
            <?php elseif ($section === 'categories'): ?>
              <fieldset><legend>Basic information</legend>
                <label>Name<input name="name" maxlength="120" required value="<?= e_attr((string) ($record['name'] ?? '')) ?>"></label>
                <label>Slug<input name="slug" maxlength="150" pattern="[a-z0-9]+(-[a-z0-9]+)*" required value="<?= e_attr((string) ($record['slug'] ?? '')) ?>"></label>
              </fieldset>
              <fieldset><legend>Category details</legend>
                <label>Description<textarea name="description" rows="3"><?= e((string) ($record['description'] ?? '')) ?></textarea></label>
                <label>Existing local image path<input name="image_path" list="rental-images" value="<?= e_attr((string) ($record['image_path'] ?? '')) ?>"></label>
              </fieldset>
            <?php elseif ($section === 'payments'): ?>
              <fieldset><legend>Payment method</legend>
                <div class="rentals-admin__two"><label>Name<input name="name" maxlength="120" required value="<?= e_attr((string) ($record['name'] ?? '')) ?>"></label><label>Type<select name="type" required><option value="manual"<?= ($record['type'] ?? 'manual') === 'manual' ? ' selected' : '' ?>>Manual payment</option><option value="gateway"<?= ($record['type'] ?? '') === 'gateway' ? ' selected' : '' ?>>Payment gateway (not connected)</option><?php if ($recordId > 0 && !in_array((string) ($record['type'] ?? ''), ['manual', 'gateway'], true)): ?><option value="<?= e_attr((string) $record['type']) ?>" selected>Legacy: <?= e((string) $record['type']) ?></option><?php endif ?></select></label></div>
                <p>Gateway methods can be configured here but are not offered at checkout until an approved integration is connected.</p>
                <p>Active manual methods require an account name and number to appear at checkout.</p>
                <label>Provider<input name="provider" maxlength="120" value="<?= e_attr((string) ($record['provider'] ?? '')) ?>"></label>
                <div class="rentals-admin__two"><label>Account name<input name="account_name" maxlength="190" value="<?= e_attr((string) ($record['account_name'] ?? '')) ?>"></label><label>Account number<input name="account_number" maxlength="100" value="<?= e_attr((string) ($record['account_number'] ?? '')) ?>"></label></div>
                <?php $paymentQr = \App\Services\RentalManagedImage::publicPath($record['qr_image_path'] ?? null, 'qr'); if ($paymentQr !== null): ?><img class="rentals-admin__qr-preview" src="<?= e_attr(url($paymentQr)) ?>" alt="Current payment QR code"><?php endif ?>
                <label><?= $recordId > 0 ? 'Replace QR image (optional)' : 'QR code image (optional)' ?><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp"></label>
              </fieldset>
            <?php endif ?>
            <fieldset><legend>Status</legend><label>Record status<select name="is_active"><option value="1"<?= (int) ($record['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($record['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></label></fieldset>
            <button class="rentals-btn rentals-btn--primary" type="submit"><?= $recordId > 0 ? 'Save changes' : 'Add ' . e($newRecordLabel) ?></button>
          </form>
          <?php if ($section === 'items' && $recordId > 0): ?>
            <div class="rentals-admin__blackouts"><h3>Manual date blocks</h3><p>Block maintenance or internal-use dates. Customer reservations remain active even if you unblock a period.</p>
              <form method="post" action="<?= e_attr(url('rentals/admin/items/' . $recordId . '/blackouts')) ?>" class="rentals-admin__blackout-form"><?= csrf_field() ?>
                <label>From<input type="date" name="start_date" required></label><label>Through<input type="date" name="end_date" required></label><label>Note<input name="note" maxlength="255" placeholder="Maintenance, internal use…"></label><button class="rentals-btn rentals-btn--dark" type="submit">Block dates</button>
              </form>
              <?php foreach (($blackouts ?? []) as $blackout): ?><div class="rentals-admin__blackout-row"><span><strong><?= e((string) $blackout['start_date']) ?> – <?= e((string) $blackout['end_date']) ?></strong> <?= e((string) ($blackout['note'] ?? '')) ?> · <?= (int) $blackout['is_active'] === 1 ? 'Blocked' : 'Unblocked' ?></span><form method="post" action="<?= e_attr(url('rentals/admin/items/' . $recordId . '/blackouts/' . (int) $blackout['id'] . '/toggle')) ?>"><?= csrf_field() ?><button type="submit"><?= (int) $blackout['is_active'] === 1 ? 'Make available' : 'Block again' ?></button></form></div><?php endforeach ?>
            </div>
          <?php endif ?>
        <?php endif ?>
      </div>

    <?php else: ?>
      <div class="rentals-admin__list">
        <div class="rentals-admin__heading">
          <div><p class="rentals-card__meta"><?= $section === 'customers' ? 'Read-only' : 'Records' ?></p><h2><?= e($listTitle) ?></h2></div>
          <?php if (in_array($section, ['items', 'categories', 'payments'], true)): ?><a class="rentals-btn rentals-btn--primary" href="<?= e_attr($sectionUrl . '?new=1') ?>">+ Add <?= $section === 'items' ? 'product' : ($section === 'payments' ? 'payment method' : 'category') ?></a><?php endif ?>
        </div>
        <?php if ($section === 'items'): ?>
          <form class="rentals-admin__filter" method="get" action="<?= e_attr($sectionUrl) ?>">
            <label><span class="sr-only">Search products</span><input type="search" name="q" value="<?= e_attr((string) ($term ?? '')) ?>" placeholder="Search name or SKU"></label>
            <label><span class="sr-only">Filter category</span><select name="category"><option value="0">All categories</option><?php foreach ($categories as $category): ?><option value="<?= e_attr((string) $category['id']) ?>"<?= (int) ($categoryFilter ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach ?></select></label>
            <label><span class="sr-only">Filter type</span><select name="type"><?php foreach (['all' => 'All types', 'equipment' => 'Equipment', 'service' => 'Services'] as $value => $label): ?><option value="<?= e_attr($value) ?>"<?= ($typeFilter ?? 'all') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
            <button class="rentals-btn rentals-btn--dark" type="submit">Filter</button>
          </form>
        <?php elseif ($section === 'orders'): ?>
          <form class="rentals-admin__filter rentals-admin__filter--orders" method="get" action="<?= e_attr($sectionUrl) ?>">
            <label><span class="sr-only">Search orders</span><input type="search" name="q" value="<?= e_attr((string) ($term ?? '')) ?>" placeholder="Order number or customer"></label>
            <label><span class="sr-only">Filter payment status</span><select name="status"><option value="all">All statuses</option><?php foreach ([0, 1, 2] as $status): ?><option value="<?= $status ?>"<?= (string) ($statusFilter ?? 'all') === (string) $status ? ' selected' : '' ?>><?= e(\App\Services\RentalPaymentStatus::label($status)) ?></option><?php endforeach ?></select></label>
            <button class="rentals-btn rentals-btn--dark" type="submit">Filter</button>
          </form>
        <?php endif ?>
        <div class="rentals-admin__table-wrap"><table class="rentals-admin__table"><thead><tr>
          <?php if ($section === 'orders'): ?><th scope="col">Order / date</th><th scope="col">Customer</th><th scope="col">Amount</th><th scope="col">Payment method</th><th scope="col">Payment status</th><th scope="col">Action</th>
          <?php elseif ($section === 'items'): ?><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Rate</th><th scope="col">Status</th><th scope="col">Action</th>
          <?php elseif ($section === 'categories'): ?><th scope="col">Category</th><th scope="col">Slug</th><th scope="col">Status</th><th scope="col">Action</th>
          <?php elseif ($section === 'payments'): ?><th scope="col">Method</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Action</th>
          <?php else: ?><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Last login</th><?php endif ?>
        </tr></thead><tbody>
          <?php foreach ($rows as $row): ?><tr>
            <?php if ($section === 'orders'): ?>
              <td><strong><?= e((string) $row['order_number']) ?></strong><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['customer_name']) ?><small><?= e((string) $row['customer_email']) ?></small></td><td>₱<?= e(number_format((float) $row['total_amount'], 2)) ?></td><td><?= e((string) ($row['payment_method'] ?? '—')) ?></td><td><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $row['payment_status']]) ?></td>
            <?php elseif ($section === 'items'): ?>
              <td><strong><?= e((string) $row['name']) ?></strong><small><?= (int) $row['is_service'] === 1 ? 'Service' : 'Equipment' ?> · <?= e((string) ($row['sku'] ?? 'No SKU')) ?></small></td><td><?= e((string) $row['category_name']) ?></td><td>₱<?= e(number_format((float) $row['rental_rate'], 2)) ?></td><td><span class="rentals-admin__status<?= (int) $row['is_active'] === 1 ? '' : ' rentals-admin__status--off' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <?php elseif ($section === 'categories'): ?>
              <td><strong><?= e((string) $row['name']) ?></strong></td><td><?= e((string) $row['slug']) ?></td><td><span class="rentals-admin__status<?= (int) $row['is_active'] === 1 ? '' : ' rentals-admin__status--off' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <?php elseif ($section === 'payments'): ?>
              <td><strong><?= e((string) $row['name']) ?></strong></td><td><?= e((string) $row['type']) ?></td><td><span class="rentals-admin__status<?= (int) $row['is_active'] === 1 ? '' : ' rentals-admin__status--off' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <?php else: ?>
              <td><strong><?= e((string) $row['name']) ?></strong></td><td><?= e((string) $row['email']) ?></td><td><?= e((string) $row['role']) ?></td><td><?= e((string) ($row['last_login'] ?? 'Never')) ?></td>
            <?php endif ?>
            <?php if ($section !== 'customers'): ?><td><a href="<?= e_attr($sectionUrl . '?edit=' . (int) $row['id']) ?>"><?= $section === 'orders' ? 'View order' : 'Edit' ?> →</a></td><?php endif ?>
          </tr><?php endforeach ?>
        </tbody></table></div>
        <?php if ($rows === []): ?><p class="rentals-admin__empty">No records match this view.</p><?php elseif (count($rows) === 200): ?><p class="rentals-admin__limit">Showing the latest 200 records.</p><?php endif ?>
      </div>
    <?php endif ?>
    <?php if ($images !== []): ?><datalist id="rental-images"><?php foreach ($images as $image): ?><option value="<?= e_attr($image) ?>"></option><?php endforeach ?></datalist><?php endif ?>
  </div>
</section>
<?php $this->end() ?>
