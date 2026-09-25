<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Orders — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/orders')) ?><?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$orders = is_array($orders ?? null) ? $orders : [];
$notice = $_SESSION['rentals_notice'] ?? null;
unset($_SESSION['rentals_notice']);
?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Customer account</p><h1>Your rental orders.</h1></div><p>Review submitted requests and their payment status.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell">
  <?php if (is_string($notice)): ?><p class="rentals-support-panel" role="status"><?= e($notice) ?></p><?php endif ?>
  <?php if ($orders === []): ?>
    <div class="rentals-support-panel"><h2>No rental orders yet.</h2><p>Your submitted rental requests will appear here.</p><a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse equipment</a></div>
  <?php else: ?>
    <div class="rentals-services-grid">
      <?php foreach ($orders as $order): ?>
        <article class="rentals-support-panel">
          <p class="rentals-card__meta"><?= e((string) $order['order_number']) ?></p>
          <h2><?= e(\App\Services\RentalPaymentStatus::label($order['payment_status'])) ?></h2>
          <p>Payment method: <?= e((string) ($order['payment_method'] ?? 'Method unavailable')) ?></p>
          <?php if (!empty($order['status_token'])): ?><p><a href="<?= e_attr(url('rentals/order-status/' . $order['status_token'])) ?>">View status link →</a></p><?php endif ?>
          <p>Submitted: <?= e((string) $order['created_at']) ?></p>
          <?php foreach (($order['details'] ?? []) as $detail): ?><p><?= e((string) $detail['item_name']) ?> × <?= e((string) $detail['quantity']) ?>, <?= e((string) $detail['rental_start_date']) ?> to <?= e((string) $detail['rental_end_date']) ?></p><?php endforeach ?>
          <p>Subtotal: ₱<?= e(number_format((float) $order['subtotal'], 2)) ?><br>Security deposit: ₱<?= e(number_format((float) $order['security_deposit'], 2)) ?><br>Total: ₱<?= e(number_format((float) $order['total_amount'], 2)) ?></p>
          <?php if (($order['payment_proof_path'] ?? '') !== ''): ?>
            <p><a href="<?= e_attr(url('rentals/orders/' . (int) $order['id'] . '/proof')) ?>" target="_blank" rel="noopener">View uploaded proof</a></p>
          <?php endif ?>
          <?php if (($order['payment_type'] ?? '') !== 'gateway' && (int) $order['payment_status'] !== \App\Services\RentalPaymentStatus::APPROVED): ?>
            <form method="post" enctype="multipart/form-data" action="<?= e_attr(url('rentals/orders/' . (int) $order['id'] . '/proof')) ?>" class="rentals-proof-upload">
              <?= csrf_field() ?>
              <label>Payment reference (optional)<input name="payment_reference" maxlength="190" value="<?= e_attr((string) ($order['payment_reference'] ?? '')) ?>"></label>
              <label>Payment proof · JPG, PNG, WebP or PDF, up to 5 MB<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
              <button class="rentals-btn rentals-btn--primary" type="submit"><?= ($order['payment_proof_path'] ?? '') !== '' ? 'Replace proof' : 'Submit proof' ?></button>
            </form>
          <?php endif ?>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div></section>
<?php $this->end() ?>
