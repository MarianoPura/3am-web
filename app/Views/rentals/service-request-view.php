<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Service quotation — 3AM Rentals<?php $this->end() ?>
<?php $this->start('content') ?>
<?php $notice=$_SESSION['rentals_service_notice']??null; unset($_SESSION['rentals_service_notice']); ?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Your service request</p><h1>Quotation &amp; payment.</h1></div><p>Review your event scope, quotation and the team's updates.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell">
  <a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/service-requests')) ?>">← Back to service requests</a>
  <?php if(is_string($notice)): ?><p class="rentals-service-request-message" role="status"><?= e($notice) ?></p><?php endif ?>
  <div class="rentals-service-workflow">
    <article class="rentals-order rentals-service-request-card"><?= $this->partial('rentals.partials.service-request-details',['record'=>$record,'expanded'=>true]) ?></article>
    <div class="rentals-order rentals-service-request-card">
      <?php if(!$workflowReady): ?><p role="status">Quotation and payment details are temporarily unavailable. Contact the team for assistance.</p>
      <?php else: ?>
        <?= $this->partial('rentals.partials.service-payment-summary',['record'=>$record]) ?>
        <?php if($record['status']==='approved' && $record['quote_amount']!==null && (float)$record['quote_amount']>0 && in_array($record['payment_status'],['unpaid','rejected'],true)): ?>
          <?php if($methods===[]): ?><p>No payment method is available. Contact support before paying.</p><?php else: ?>
            <form method="post" action="<?= e_attr(url('rentals/service-requests/'.(int)$record['id'].'/payment')) ?>" enctype="multipart/form-data" class="rentals-account__form" data-service-request-form data-service-payment>
              <?= csrf_field() ?>
              <input type="hidden" name="quote_version" value="<?= (int)$record['quote_version'] ?>">
              <label class="rentals-date-field">Payment method<select name="payment_method_id" required><?php foreach($methods as $method): ?><option value="<?= (int)$method['id'] ?>"><?= e($method['name']) ?></option><?php endforeach ?></select></label>
              <?php foreach($methods as $index=>$method): ?>
                <div class="rentals-payment-instructions" data-service-pay-method="<?= (int)$method['id'] ?>"<?= $index?' hidden':'' ?>>
                  <p class="rentals-card__meta">How to pay · <?= e($method['name']) ?></p>
                  <?php if(!empty($method['qr_image_path'])): ?><figure class="rentals-payment-instructions__qr"><img src="<?= e_attr(url('rentals/payment-qr/'.(int)$method['id'])) ?>" alt="<?= e_attr($method['name']) ?> payment QR" width="240" height="240" data-service-pay-qr><p role="status" data-service-pay-qr-error hidden>QR temporarily unavailable. Use the account details below.</p></figure><?php endif ?>
                  <dl><div><dt>Account name</dt><dd><?= e($method['account_name']) ?></dd></div><div><dt>Account number</dt><dd><?= e($method['account_number']) ?></dd></div><div><dt>Amount to pay</dt><dd>₱<?= e(number_format((float)$record['quote_amount'],2)) ?></dd></div></dl>
                </div>
              <?php endforeach ?>
              <label class="rentals-date-field">Payment reference (optional)<input name="payment_reference" maxlength="190"></label>
              <label class="rentals-date-field">Payment proof<input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required><small>JPG, PNG, WebP or PDF · <?= e(number_format(\App\Services\RentalPaymentProof::maxUploadBytes()/1048576,1)) ?> MB max.</small></label>
              <button type="submit" class="rentals-btn rentals-btn--primary"><span class="rentals-submit-spinner" data-service-spinner aria-hidden="true" hidden></span><span data-service-submit-label>Submit payment proof</span></button>
            </form>
          <?php endif ?>
        <?php endif ?>
      <?php endif ?>
    </div>
  </div>
</div></section>
<?php $this->end() ?>
