<?php $statusName = \App\Services\RentalServiceRequests::STATUSES[$record['status']] ?? 'Pending'; ?>
<div class="rentals-order__heading"><div><p class="rentals-card__meta">Service request</p><h2><?= e($record['service_name']) ?></h2><small><?= e($record['reference']) ?> · <?= e($record['created_at']) ?></small></div><span class="rentals-payment-badge rentals-payment-badge--<?= e_attr(strtolower($statusName)) ?>"><?= e($statusName) ?></span></div>
<dl class="rentals-order__summary">
  <div><dt>Event dates</dt><dd><?= e($record['event_start_date']) ?> – <?= e($record['event_end_date']) ?></dd></div>
  <div><dt>Event location</dt><dd><?= e($record['location']) ?></dd></div>
  <?php if (!empty($showCustomer)): ?><div><dt>Customer</dt><dd><?= e($record['customer_name']) ?><br><?= e($record['customer_email']) ?><br><?= e($record['customer_phone']) ?></dd></div><?php endif ?>
  <div><dt>Review</dt><dd><?= $record['reviewed_at'] ? 'Reviewed ' . e($record['reviewed_at']) : 'Awaiting team review' ?></dd></div>
</dl>
<details class="rentals-order__details"<?= !empty($expanded) ? ' open' : '' ?>><summary>Service requirements</summary><p class="rentals-service-request-text"><?= nl2br(e($record['details'])) ?></p></details>
<?php if (trim((string) ($record['customer_message'] ?? '')) !== ''): ?><div class="rentals-service-request-message"><strong>Message from the team</strong><p><?= nl2br(e($record['customer_message'])) ?></p></div><?php endif ?>
<p class="rentals-service-request-note"><?= e(match($record['status']) {
    'approved'=>'Your enquiry is approved. View quotation/payment details for the next step.',
    'rejected'=>'The team cannot proceed with this request. Contact support to discuss alternatives.',
    'completed'=>'This service has been completed. Thank you for working with 3AM.',
    'cancelled'=>'This service was cancelled. Contact the team about any payment/refund arrangements.',
    default=>'The team is reviewing your service requirements. No payment is requested yet.',
}) ?></p>
