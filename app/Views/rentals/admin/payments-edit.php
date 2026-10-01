<?php
$payment = $payment ?? ($record ?? []);
$paymentId = (int) ($payment['id'] ?? 0);
?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Edit Payment Method — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Payment Methods</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?>
      <p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>>
        <?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?>
      </p>
    <?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/payments')) ?>">← Back to payment methods</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta">Existing record</p>
        <h2>Edit <?= e((string) ($payment['name'] ?? 'Payment Method')) ?></h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/payments/' . $paymentId . '/edit')) ?>" class="rentals-admin__form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e_attr((string) $paymentId) ?>">
        <fieldset>
          <legend>Payment method details</legend>
          <div class="rentals-admin__two">
            <label>Name
              <input name="name" maxlength="120" required value="<?= e_attr((string) ($payment['name'] ?? '')) ?>">
            </label>
            <label>Type
              <select name="type" required>
                <option value="manual"<?= ($payment['type'] ?? 'manual') === 'manual' ? ' selected' : '' ?>>Manual payment</option>
                <option value="gateway"<?= ($payment['type'] ?? '') === 'gateway' ? ' selected' : '' ?>>Payment gateway (not connected)</option>
              </select>
            </label>
          </div>
          <p>Manual methods display bank or wallet account information directly to customers at checkout.</p>
          <label>Provider (optional)
            <input name="provider" maxlength="120" value="<?= e_attr((string) ($payment['provider'] ?? '')) ?>">
          </label>
          <div class="rentals-admin__two">
            <label>Account name (optional)
              <input name="account_name" maxlength="190" value="<?= e_attr((string) ($payment['account_name'] ?? '')) ?>">
            </label>
            <label>Account number (optional)
              <input name="account_number" maxlength="100" value="<?= e_attr((string) ($payment['account_number'] ?? '')) ?>">
            </label>
          </div>
        </fieldset>
        <?= $this->partial('rentals.partials.admin-payment-image', ['payment' => $payment]) ?>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1"<?= (int) ($payment['is_active'] ?? 1) === 1 ? ' selected' : '' ?>>Active</option>
              <option value="0"<?= (int) ($payment['is_active'] ?? 1) === 0 ? ' selected' : '' ?>>Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Save changes</button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
