<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Orders — 3AM<?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>'rentals/orders']) ?>
<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/orders')) ?><?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>'rentals/orders']) ?>
<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$orders = is_array($orders ?? null) ? $orders : [];
$notice = $_SESSION['rentals_notice'] ?? null;
unset($_SESSION['rentals_notice']);
?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Customer account</p><h1>My Rentals.</h1></div><p>Your rental requests, dates and payment updates in one place.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell">
  <?php if (is_string($notice)): ?><p class="rentals-support-panel" role="status"><?= e($notice) ?></p><?php endif ?>
  <?php if ($orders === []): ?>
    <div class="rentals-support-panel"><h2>No rental orders yet.</h2><p>Your submitted rental requests will appear here.</p><a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse equipment</a></div>
  <?php else: ?>
    <div class="rentals-orders">
      <?php foreach ($orders as $order): ?>
        <?php
        $lines = $order['details'] ?? [];
        $starts = array_filter(array_column($lines, 'rental_start_date'));
        $ends = array_filter(array_column($lines, 'rental_end_date'));
        $dateLabel = $starts && $ends ? min($starts) . ' – ' . max($ends) : 'Dates pending';
        ?>
        <article class="rentals-order">
          <div class="rentals-order__heading"><div><p class="rentals-card__meta">Order number</p><h2><?= e((string) $order['order_number']) ?></h2><small>Submitted <?= e((string) $order['created_at']) ?></small></div><?= $this->partial('rentals.partials.payment-status-badge', ['status' => $order['payment_status']]) ?></div>
          <dl class="rentals-order__summary">
            <div><dt>Rental dates</dt><dd><?= e($dateLabel) ?></dd></div>
            <div><dt>Items</dt><dd><?= e(implode(', ', array_map(static fn (array $line): string => $line['item_name'] . ' ×' . $line['quantity'], $lines))) ?></dd></div>
            <div><dt>Payment method</dt><dd><?= e((string) ($order['payment_method'] ?? 'Method unavailable')) ?></dd></div>
            <div><dt>Total</dt><dd class="rentals-order__amount">₱<?= e(number_format((float) $order['total_amount'], 2)) ?></dd></div>
          </dl>
          <details class="rentals-order__details"><summary>View order details <span aria-hidden="true">＋</span></summary>
            <div class="rentals-order__table-wrap"><table><caption class="sr-only">Rented items for <?= e((string) $order['order_number']) ?></caption><thead><tr><th scope="col">Item</th><th scope="col">Quantity</th><th scope="col">Dates</th><th scope="col">Rate</th><th scope="col">Line total</th></tr></thead><tbody>
              <?php foreach ($lines as $detail): ?><tr><td><?= e((string) $detail['item_name']) ?></td><td><?= (int) $detail['quantity'] ?></td><td><?= e((string) $detail['rental_start_date']) ?> – <?= e((string) $detail['rental_end_date']) ?></td><td>₱<?= e(number_format((float) $detail['unit_rate'], 2)) ?></td><td>₱<?= e(number_format((float) $detail['line_total'], 2)) ?></td></tr><?php endforeach ?>
            </tbody></table></div>
            <dl class="rentals-order__totals"><div><dt>Rental subtotal</dt><dd>₱<?= e(number_format((float) $order['subtotal'], 2)) ?></dd></div><div><dt>Security deposit</dt><dd>₱<?= e(number_format((float) $order['security_deposit'], 2)) ?></dd></div><div><dt>Total</dt><dd>₱<?= e(number_format((float) $order['total_amount'], 2)) ?></dd></div></dl>
            <?php if (($order['payment_reference'] ?? '') !== ''): ?><p>Payment reference: <?= e((string) $order['payment_reference']) ?></p><?php endif ?>
          <?php if (($order['payment_proof_path'] ?? '') !== ''): ?>
            <p><a href="<?= e_attr(url('rentals/orders/' . (int) $order['id'] . '/proof')) ?>" target="_blank" rel="noopener">View uploaded proof</a></p>
          <?php endif ?>
          <?php if (($order['payment_type'] ?? '') !== 'gateway' && (int) $order['payment_status'] !== \App\Services\RentalPaymentStatus::APPROVED): ?>
            <form method="post" enctype="multipart/form-data" action="<?= e_attr(url('rentals/orders/' . (int) $order['id'] . '/proof')) ?>" class="rentals-proof-upload">
              <?= csrf_field() ?>
              <label>Payment reference (optional)<input name="payment_reference" maxlength="190" value="<?= e_attr((string) ($order['payment_reference'] ?? '')) ?>"></label>
              <label>Payment proof · JPG, PNG, WebP or PDF, up to <?= e(number_format(\App\Services\RentalPaymentProof::maxUploadBytes() / 1048576, 2)) ?> MB<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
              <button class="rentals-btn rentals-btn--primary" type="submit"><?= ($order['payment_proof_path'] ?? '') !== '' ? 'Replace proof' : 'Submit proof' ?></button>
            </form>
          <?php endif ?>
          </details>
          <div class="rentals-order__footer"><?php if (!empty($order['status_token'])): ?><a href="<?= e_attr(url('rentals/order-status/' . $order['status_token'])) ?>">View secure status →</a><?php endif ?><span><?= (int) $order['payment_status'] === 0 ? 'Payment pending review' : ((int) $order['payment_status'] === 2 ? 'Request rejected · upload a new proof in order details to retry' : 'Payment approved') ?></span></div>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div></section>
<?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>'rentals/orders']) ?>
<?php $this->end() ?>
