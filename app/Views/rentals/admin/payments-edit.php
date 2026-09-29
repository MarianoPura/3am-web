<?php $recordId = (int) ($record['id'] ?? 0); ?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?><?= $recordId > 0 ? 'Edit' : 'Add' ?> Payment Method — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Payment Methods</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/payments')) ?>">← Back to payment methods</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta"><?= $recordId > 0 ? 'Existing record' : 'New record' ?></p>
        <h2><?= $recordId > 0 ? 'Edit ' . e((string) ($record['name'] ?? 'record')) : 'Add payment method' ?></h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/payments')) ?>" class="rentals-admin__form" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e_attr((string) $recordId) ?>">
        <fieldset><legend>Payment method</legend>
          <div class="rentals-admin__two"><label>Name<input name="name" maxlength="120" required value="<?= e_attr((string) ($record['name'] ?? '')) ?>"></label><label>Type<select name="type" required><option value="manual"<?= ($record['type'] ?? 'manual') === 'manual' ? ' selected' : '' ?>>Manual payment</option><option value="gateway"<?= ($record['type'] ?? '') === 'gateway' ? ' selected' : '' ?>>Payment gateway (not connected)</option><?php if ($recordId > 0 && !in_array((string) ($record['type'] ?? ''), ['manual', 'gateway'], true)): ?><option value="<?= e_attr((string) $record['type']) ?>" selected>Legacy: <?= e((string) $record['type']) ?></option><?php endif ?></select></label></div>
          <p>Gateway methods can be configured here but are not offered at checkout until an approved integration is connected.</p>
          <p>Active manual methods require an account name and number to appear at checkout.</p>
          <label>Provider<input name="provider" maxlength="120" value="<?= e_attr((string) ($record['provider'] ?? '')) ?>"></label>
          <div class="rentals-admin__two"><label>Account name<input name="account_name" maxlength="190" value="<?= e_attr((string) ($record['account_name'] ?? '')) ?>"></label><label>Account number<input name="account_number" maxlength="100" value="<?= e_attr((string) ($record['account_number'] ?? '')) ?>"></label></div>
          <?php $paymentQr = \App\Services\RentalManagedImage::publicPath($record['qr_image_path'] ?? null, 'qr'); if ($paymentQr !== null): ?><img class="rentals-admin__qr-preview" src="<?= e_attr(url('rentals/payment-qr/' . $recordId)) ?>" alt="Current payment QR code"><?php endif ?>
          <label><?= $recordId > 0 ? 'Replace QR image (optional)' : 'QR code image (optional)' ?><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp"></label>
        </fieldset>
        <fieldset><legend>Status</legend><label>Record status<select name="is_active"><option value="1"<?= (int) ($record['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) ($record['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option></select></label></fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit"><?= $recordId > 0 ? 'Save changes' : 'Add payment method' ?></button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
