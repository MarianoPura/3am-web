<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Access Denied — 3AM Rentals<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">403 / Access denied</p><h1>Admin access is restricted.</h1></div>
    <p>This page is available only to Rentals administrators.</p>
  </div>
</section>
<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell"><div class="rentals-support-panel rentals-status-card">
    <h2>Your Rentals account is still available.</h2>
    <div class="rentals-inline-actions">
      <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals')) ?>">Back to Rentals</a>
      <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/account')) ?>">My Account</a>
    </div>
  </div></div>
</section>
<?php $this->end() ?>
