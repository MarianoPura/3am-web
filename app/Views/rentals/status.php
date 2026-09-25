<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Status — 3AM<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">3AM Rentals</p><h1>Rental status.</h1></div><p>Keep this private link to check your payment review.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell"><article class="rentals-support-panel rentals-status-card">
  <p class="rentals-card__meta">Order <?= e((string) $order['order_number']) ?></p>
  <h2><?= e(\App\Services\RentalPaymentStatus::label($order['payment_status'])) ?></h2>
  <p>Customer: <?= e((string) $order['customer_name']) ?></p>
  <p>Payment method: <?= e((string) ($order['payment_method'] ?? 'Unavailable')) ?></p>
  <p><?= (int) $order['payment_status'] === \App\Services\RentalPaymentStatus::APPROVED ? 'Your payment has been approved.' : ((int) $order['payment_status'] === \App\Services\RentalPaymentStatus::REJECTED ? 'Your payment was rejected. Please contact Rentals support.' : 'Your proof has been submitted. Please wait for review.') ?></p>
  <?= $this->partial('rentals.partials.status-qr', ['statusToken' => $statusToken]) ?>
  <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/support')) ?>">Rental support</a>
</article></div></section>
<?php $this->end() ?>
<?php $this->start('scripts') ?><script src="<?= e_attr(versioned('js/vendor/qrcode.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><script src="<?= e_attr(versioned('js/rentals-qr.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><?php $this->end() ?>
