<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Request Received — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/confirmation/' . $status_token)) ?><?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$reviewStatus = (int) $order['payment_status'];
$hasProof = (bool) $order['has_proof'];
$reviewMessage = match ($reviewStatus) {
    \App\Services\RentalPaymentStatus::APPROVED => 'Your rental request and payment review have been approved. Contact the Rentals team to arrange the next steps.',
    \App\Services\RentalPaymentStatus::REJECTED => 'Your rental request and payment review were rejected. Contact Rentals support, or sign in to your account to submit a replacement proof if needed.',
    default => $hasProof ? 'Your payment proof was submitted and is under review. The Rentals team will notify you after review.' : 'Your rental request is under review. Contact the Rentals team for the next steps.',
};
?>
<section class="rentals-section rentals-confirmation" aria-labelledby="rentals-confirmation-title">
  <div class="rentals-shell">
    <article class="rentals-support-panel rentals-confirmation__panel">
      <p class="rentals-kicker">Request received</p>
      <h1 id="rentals-confirmation-title">3AM has your rental request.</h1>
      <dl class="rentals-confirmation__facts">
        <div><dt>Reference</dt><dd><?= e((string) $order['order_number']) ?></dd></div>
        <div><dt>Status</dt><dd><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $reviewStatus]) ?></dd></div>
      </dl>
      <p class="rentals-confirmation__message" role="status"><?= e($reviewMessage) ?></p>
      <?= $this->partial('rentals.partials.status-qr', ['statusToken' => $status_token]) ?>
      <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals')) ?>">Return to Rentals</a>
    </article>
  </div>
</section>
<?php $this->end() ?>
<?php $this->start('scripts') ?><script src="<?= e_attr(versioned('js/vendor/qrcode.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><script src="<?= e_attr(versioned('js/rentals-qr.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script><?php $this->end() ?>
