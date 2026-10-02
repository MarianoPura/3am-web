<?php
$recordId = (int) ($record['id'] ?? 0);
$proofReference = (string) ($record['payment_proof_path'] ?? '');
$proofAvailable = $proofReference !== '' && \App\Services\RentalPaymentProof::path($proofReference) !== null;
$proofUrl = url('rentals/admin/proof/' . $recordId);
?>
<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Order <?= e((string) ($record['order_number'] ?? '')) ?> — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Orders</h1></div>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="<?= !empty($noticeIsError) ? 'alert' : 'status' ?>"<?= !empty($noticeIsError) ? ' data-admin-save-error' : '' ?>><?php if (!empty($noticeIsError)): ?><strong>Not saved:</strong> <?php endif ?><?= e($notice) ?></p><?php endif ?>
    <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/orders')) ?>">← Back to orders</a>
    <div class="rentals-admin__editor">
      <div class="rentals-admin__editor-head">
        <p class="rentals-card__meta">Existing record</p>
        <h2>Order <?= e((string) ($record['order_number'] ?? '')) ?></h2>
      </div>
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
      <section class="rentals-admin__proof" aria-labelledby="proof-title">
        <div><p class="rentals-card__meta">Manual payment</p><h3 id="proof-title">Payment proof review</h3></div>
        <?php if ($proofAvailable): ?>
          <details class="rentals-admin__proof-detail"><summary>View uploaded proof</summary>
            <?php if (preg_match('/\.pdf(?:\.php)?$/', $proofReference)): ?><a href="<?= e_attr($proofUrl) ?>" target="_blank" rel="noopener">Open PDF proof in a new tab</a>
            <?php else: ?>
              <img src="<?= e_attr($proofUrl) ?>" alt="Uploaded payment proof for order <?= e_attr((string) $record['order_number']) ?>" data-admin-proof-image>
              <p class="rentals-admin__empty" data-admin-proof-error hidden>The proof could not be displayed. Open the protected proof link to check its response.</p>
              <a href="<?= e_attr($proofUrl) ?>" target="_blank" rel="noopener">Open proof in a new tab</a>
            <?php endif ?>
          </details>
        <?php else: ?><p class="rentals-admin__empty"><?= $proofReference === '' ? 'No payment proof has been uploaded yet.' : 'The saved payment proof file is unavailable.' ?></p><?php endif ?>
      </section>
      <?php if ((int) $record['payment_status'] === \App\Services\RentalPaymentStatus::PENDING): ?>
        <section class="rentals-admin__review" aria-labelledby="review-actions-title">
          <div><p class="rentals-card__meta">Pending review</p><h3 id="review-actions-title">Review payment</h3><p>Approve verified payment or reject this request. Rejection releases its equipment reservation.</p></div>
          <?php $canApprove = \App\Services\RentalPaymentProof::path($record['payment_proof_path'] ?? null) !== null && ($record['payment_type'] ?? '') !== 'gateway'; ?>
          <?php if (!$canApprove): ?><p class="rentals-admin__empty">A reviewable payment proof is required before approval.</p><?php endif ?>
          <form method="post" action="<?= e_attr(url('rentals/admin/orders/' . $recordId . '/review')) ?>" class="rentals-admin__proof-actions"><?= csrf_field() ?>
            <button class="rentals-btn rentals-btn--primary" name="decision" value="approved" type="submit"<?= $canApprove ? '' : ' disabled' ?>>Approve</button>
            <button class="rentals-btn rentals-btn--reject" name="decision" value="rejected" type="submit">Reject</button>
          </form>
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
    </div>
  </div>
</section>
<?php $this->end() ?>
