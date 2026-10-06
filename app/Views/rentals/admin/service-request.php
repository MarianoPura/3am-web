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
</div></section>
<?php $this->end() ?>
