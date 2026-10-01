<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Add Payment Method — Rentals Admin<?php $this->end() ?>
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
        <p class="rentals-card__meta">New record</p>
        <h2>Add payment method</h2>
      </div>
      <form method="post" action="<?= e_attr(url('rentals/admin/payments')) ?>" class="rentals-admin__form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Payment method details</legend>
          <div class="rentals-admin__two">
            <label>Name
              <input name="name" maxlength="120" required placeholder="e.g. GCash or Bank Transfer">
            </label>
            <label>Type
              <select name="type" required>
                <option value="manual" selected>Manual payment</option>
                <option value="gateway">Payment gateway (not connected)</option>
              </select>
            </label>
          </div>
          <p>Manual methods display bank or wallet account information directly to customers at checkout.</p>
          <label>Provider (optional)
            <input name="provider" maxlength="120" placeholder="e.g. BDO, BPI, GCash, Maya">
          </label>
          <div class="rentals-admin__two">
            <label>Account name (optional)
              <input name="account_name" maxlength="190" placeholder="e.g. 3AM Media Tech Inc.">
            </label>
            <label>Account number (optional)
              <input name="account_number" maxlength="100" placeholder="e.g. 0917-XXX-XXXX or 001-XXXX-XXXX">
            </label>
          </div>
        </fieldset>
        <?= $this->partial('rentals.partials.admin-payment-image') ?>
        <fieldset>
          <legend>Status</legend>
          <label>Record status
            <select name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Inactive</option>
            </select>
          </label>
        </fieldset>
        <button class="rentals-btn rentals-btn--primary" type="submit">Add payment method</button>
      </form>
    </div>
  </div>
</section>
<?php $this->end() ?>
