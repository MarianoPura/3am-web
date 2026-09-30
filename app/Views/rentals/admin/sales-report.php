<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Sales Report — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Sales Report</h1></div>
      <p>Review and manage sales report.</p>
    </div>
    <?= $this->partial('rentals.partials.admin-sales-report', ['report' => $report]) ?>
  </div>
</section>
<?php $this->end() ?>
