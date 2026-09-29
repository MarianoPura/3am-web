<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Analytics — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Analytics</h1></div>
      <p>Track approved rental sales and equipment demand over time.</p>
    </div>
    <?= $this->partial('rentals.partials.admin-analytics', ['insights' => $insights]) ?>
  </div>
</section>
<?php $this->end() ?>
