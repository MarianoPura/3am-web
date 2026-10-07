<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Review Service Request — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<?php $notice = $_SESSION['rentals_service_notice'] ?? null; unset($_SESSION['rentals_service_notice']); ?>
<section class="rentals-admin-page"><div class="rentals-shell">
  <div class="rentals-admin-page__heading"><div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Service request</h1></div></div>
  <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/service-requests')) ?>">← Back to service requests</a>
  <?php if (is_string($notice)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>
  <article class="rentals-order rentals-service-request-card"><?= $this->partial('rentals.partials.service-request-details', ['record' => $record, 'showCustomer' => true, 'expanded' => true]) ?></article>
  <?php if ($record['status'] === 'pending'): ?>
    <div class="rentals-admin__editor rentals-service-request-review"><h2>Review the service enquiry</h2><p>Approve to proceed with coordination, or reject if the team cannot accommodate the request. This does not approve a payment.</p>
      <form method="post" action="<?= e_attr(url('rentals/admin/service-requests/' . (int) $record['id'] . '/review')) ?>" class="rentals-admin__form">
        <?= csrf_field() ?><label>Message to customer (optional)<textarea name="customer_message" rows="3" maxlength="1000" placeholder="This message will be visible in the customer's request history."></textarea></label>
        <div class="rentals-inline-actions"><button type="submit" name="decision" value="approved" class="rentals-btn rentals-btn--primary">Approve request</button><button type="submit" name="decision" value="rejected" class="rentals-btn rentals-btn--dark">Reject request</button></div>
      </form>
    </div>
  <?php endif ?>

  <?php if(!empty($workflowReady)): ?>
    <section class="rentals-admin__editor rentals-service-request-review">
      <?= $this->partial('rentals.partials.service-payment-summary',['record'=>$record]) ?>
      <?php if($record['status']==='approved' && $record['payment_status']==='unpaid' && !$record['payment_proof_path']): ?>
        <h2>Set quotation</h2>
        <form method="post" action="<?= e_attr(url('rentals/admin/service-requests/'.(int)$record['id'].'/manage')) ?>" class="rentals-admin__form">
          <?= csrf_field() ?><input type="hidden" name="action" value="quote">
          <label>Quoted amount (₱)<input name="quote_amount" type="number" required min="0" max="9999999999.99" step="0.01" value="<?= e_attr((string)$record['quote_amount']) ?>"></label>
          <label>Scope / quotation notes<textarea name="quote_notes" maxlength="2000" rows="4"><?= e((string)$record['quote_notes']) ?></textarea><small>Visible to the customer. Quote changes are locked once payment proof is submitted.</small></label>
          <button class="rentals-btn rentals-btn--primary" type="submit">Save quotation</button>
        </form>
      <?php endif ?>
      <?php if($record['status']==='approved' && $record['payment_status']==='pending' && $record['payment_proof_path']): ?>
        <h2>Review service payment</h2>
        <form method="post" action="<?= e_attr(url('rentals/admin/service-requests/'.(int)$record['id'].'/manage')) ?>" class="rentals-admin__form">
          <?= csrf_field() ?><input type="hidden" name="action" value="payment">
          <label>Payment message to customer<textarea name="payment_message" maxlength="1000" rows="3"></textarea></label>
          <div class="rentals-inline-actions"><button class="rentals-btn rentals-btn--primary" name="decision" value="approved">Approve payment</button><button class="rentals-btn rentals-btn--dark" name="decision" value="rejected">Reject payment</button></div>
        </form>
      <?php endif ?>
      <?php if(in_array($record['status'],['pending','approved'],true)): ?>
        <h2>Close service request</h2><p>Complete after the event ends and payment is approved (or no payment is required). Cancellation preserves payment records; any refund must be coordinated separately.</p>
        <form method="post" action="<?= e_attr(url('rentals/admin/service-requests/'.(int)$record['id'].'/manage')) ?>" class="rentals-admin__form">
          <?= csrf_field() ?><input type="hidden" name="action" value="close">
          <label>Closing message to customer<textarea name="customer_message" maxlength="1000" rows="3"></textarea></label>
          <div class="rentals-inline-actions">
            <?php if($record['status']==='approved' && $record['event_end_date']<=date('Y-m-d') && $record['quote_amount']!==null && ((float)$record['quote_amount']===0.0 || $record['payment_status']==='approved')): ?><button class="rentals-btn rentals-btn--primary" name="decision" value="completed">Mark completed</button><?php endif ?>
            <button class="rentals-btn rentals-btn--dark" name="decision" value="cancelled">Cancel service</button>
          </div>
        </form>
      <?php endif ?>
    </section>
  <?php else: ?><p class="rentals-admin__notice">Quotation/payment tracking requires the Services deployment update. Existing request reviews remain available.</p><?php endif ?>

</div></section>
<?php $this->end() ?>
