<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Review Rental Request — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/checkout')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Review your rental request and customer information for 3AM Rentals.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$summary = is_array($summary ?? null) ? $summary : ['items' => [], 'subtotal' => 0, 'security_deposit' => 0, 'total' => 0, 'preview' => false];
$items = is_array($summary['items'] ?? null) ? $summary['items'] : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$customer = is_array($customer ?? null) ? $customer : [];
?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">Rental request</p><h1>Review your selection.</h1></div>
    <p>Confirm your rental dates and customer details before the 3AM team reviews availability.</p>
  </div>
</section>
<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <?php if (($error ?? null) !== null): ?>
      <div class="rentals-support-panel" role="alert" style="margin-bottom:1.25rem; border-left:3px solid var(--rentals-accent);">
        <?= e($error) ?>
        <?php if (str_contains((string) $error, 'authenticated customer account')): ?>
          <p style="margin-top:0.75rem;"><a href="<?= e_attr(url('rentals/account')) ?>">Sign in or create your customer account</a> to continue.</p>
        <?php elseif (str_contains((string) $error, 'cart contains')): ?>
          <p style="margin-top:0.75rem;">Remove unavailable items from your cart before submitting.</p>
        <?php endif ?>
      </div>
    <?php endif ?>

    <?php if ($items === []): ?>
      <div class="rentals-support-panel">
        <p class="rentals-card__meta">Nothing to review</p>
        <h2>Your cart is empty.</h2>
        <p>Add rental equipment before continuing.</p>
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse equipment</a>
      </div>
    <?php else: ?>
      <div class="rentals-cart-layout">
        <div class="rentals-support-panel">
          <p class="rentals-card__meta">Selected equipment</p>
          <?php foreach ($items as $item): ?>
            <article style="padding:1rem 0; border-bottom:1px solid var(--rentals-border);">
              <h3 style="margin:0 0 .35rem;"><?= e($item['name'] ?? 'Rental item') ?></h3>
              <p>Quantity: <?= e((string) ($item['quantity'] ?? 1)) ?></p>
              <p>Rental window: <?= e((string) ($item['rental_start_date'] ?? '')) ?> to <?= e((string) ($item['rental_end_date'] ?? '')) ?></p>
              <?php if (($item['is_sample'] ?? false) === true): ?><p class="rentals-card__meta">Preview only — cannot be submitted.</p><?php endif ?>
            </article>
          <?php endforeach ?>
          <div style="padding-top:1rem;">
            <p>Subtotal: <strong><?= e(number_format((float) $summary['subtotal'], 2)) ?></strong></p>
            <p>Security deposit: <strong><?= e(number_format((float) $summary['security_deposit'], 2)) ?></strong></p>
            <p>Total: <strong><?= e(number_format((float) $summary['total'], 2)) ?></strong></p>
          </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="rentals-support-panel" data-rental-checkout>
          <?= csrf_field() ?>
          <p class="rentals-card__meta">Customer details</p>
          <p>Your payment will remain Pending until the 3AM team reviews your proof.</p>
          <label class="rentals-date-field" style="margin-top:1rem;">Name<input type="text" name="customer_name" required value="<?= e_attr($customer['name'] ?? '') ?>"></label>
          <label class="rentals-date-field" style="margin-top:1rem;">Account email<input type="email" name="customer_email" readonly value="<?= e_attr($customer['email'] ?? '') ?>"></label>
          <label class="rentals-date-field" style="margin-top:1rem;">Phone<input type="tel" name="customer_phone" value="<?= e_attr($customer['phone'] ?? '') ?>"></label>
          <?php if ($paymentMethods !== []): ?>
            <label class="rentals-date-field" style="margin-top:1rem;">Payment method<select name="payment_method_id" required><option value="" disabled<?= (int) ($customer['payment_method_id'] ?? 0) === 0 ? ' selected' : '' ?>>Select a payment method</option><?php foreach ($paymentMethods as $method): ?><option value="<?= e_attr((string) $method['id']) ?>"<?= (int) ($customer['payment_method_id'] ?? 0) === (int) $method['id'] ? ' selected' : '' ?>><?= e($method['name']) ?></option><?php endforeach ?></select></label>
            <?php foreach ($paymentMethods as $method): ?>
              <div class="rentals-payment-instructions" data-payment-instructions="<?= (int) $method['id'] ?>" hidden>
                <p class="rentals-card__meta">How to pay · <?= e((string) $method['name']) ?></p>
                <?php if ($method['type'] === 'manual'): ?>
                  <dl>
                    <?php if (trim((string) ($method['provider'] ?? '')) !== ''): ?><div><dt>Provider / bank</dt><dd><?= e((string) $method['provider']) ?></dd></div><?php endif ?>
                    <div><dt>Account name</dt><dd><?= e((string) $method['account_name']) ?></dd></div>
                    <div><dt>Account number</dt><dd><?= e((string) $method['account_number']) ?></dd></div>
                    <div><dt>Amount to pay</dt><dd>₱<?= e(number_format((float) $summary['total'], 2)) ?></dd></div>
                  </dl>
                  <p>Send the amount to this account, then upload your proof of payment below.</p>
                  <?php $qrPath = \App\Services\RentalManagedImage::publicPath($method['qr_image_path'] ?? null, 'qr'); if ($qrPath !== null): ?><button class="rentals-btn rentals-btn--dark" type="button" data-payment-qr-open data-qr-src="<?= e_attr(url($qrPath)) ?>" data-qr-name="<?= e_attr((string) $method['name']) ?>">View / Scan QR</button><?php endif ?>
                <?php else: ?><p>Local test method only. No real payment is collected.</p><?php endif ?>
              </div>
            <?php endforeach ?>
            <label class="rentals-date-field" style="margin-top:1rem;">Payment reference (optional)<input name="payment_reference" maxlength="190" value="<?= e_attr((string) ($customer['payment_reference'] ?? '')) ?>"></label>
            <label class="rentals-date-field" style="margin-top:1rem;">Proof of payment · JPG, PNG, WebP or PDF (5 MB max)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
          <?php else: ?>
            <p role="status" style="margin-top:1rem;">No payment method is currently available. Please <a href="<?= e_attr(url('rentals/support')) ?>">contact Rental Support</a>; requests cannot be submitted yet.</p>
          <?php endif ?>
          <label class="rentals-date-field" style="margin-top:1rem;">Notes<textarea name="notes" rows="4"><?= e($customer['notes'] ?? '') ?></textarea></label>
          <button type="submit" class="rentals-btn rentals-btn--primary" style="margin-top:1rem;"<?= $paymentMethods === [] || $summary['preview'] ? ' disabled' : '' ?>>Submit rental request</button>
        </form>
        <dialog class="rentals-payment-qr-dialog" data-payment-qr-dialog aria-labelledby="rentals-payment-qr-title"><button type="button" data-payment-qr-close aria-label="Close QR code">×</button><h2 id="rentals-payment-qr-title" data-payment-qr-title></h2><img data-payment-qr-image alt="Payment QR code"><a data-payment-qr-link target="_blank" rel="noopener">Open QR image</a></dialog>
      </div>
    <?php endif ?>
  </div>
</section>
<?php $this->end() ?>
