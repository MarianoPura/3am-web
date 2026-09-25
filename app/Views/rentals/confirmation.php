<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Request Received — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/checkout')) ?><?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-support-panel">
      <p class="rentals-kicker">Request received</p>
      <h1>3AM has your rental request.</h1>
      <p>Reference: <strong><?= e($order_number ?? '') ?></strong></p>
      <p>Your payment proof was submitted. The Rentals team will review it. Payment status: Pending.</p>
      <p><a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/order-status/' . $status_token)) ?>">View order status</a></p>
      <?= $this->partial('rentals.partials.status-qr', ['statusToken' => $status_token]) ?>
      <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals')) ?>">Return to Rentals</a>
    </div>
  </div>
</section>
<?php $this->end() ?>
<?php $this->start('scripts') ?><script src="<?= e_attr(versioned('js/vendor/qrcode.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><script src="<?= e_attr(versioned('js/rentals-qr.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><?php $this->end() ?>
