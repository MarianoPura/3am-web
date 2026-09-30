<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Payment Report — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Payment Report</h1></div>
      <p>Review and manage payment report.</p>
    </div>
    <?= $this->partial('rentals.partials.admin-payment-report', ['report' => $report]) ?>
  </div>
</section>
<?php $this->end() ?>
