<?php $this->extend('rentals.layouts.admin'); ?>
<?php $this->start('title') ?>Dashboard — Rentals Admin<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-admin-page">
  <div class="rentals-shell">
    <div class="rentals-admin-page__heading">
      <div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Dashboard</h1></div>
      <p>Manage rental products, requests, and checkout options from one place.</p>
    </div>
    <?php if (is_string($notice ?? null)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>
    <?= $this->partial('rentals.partials.admin-dashboard', ['metrics' => $metrics, 'recentOrders' => $recentOrders, 'upcomingRentals' => $upcomingRentals]) ?>
  </div>
</section>
<?php $this->end() ?>
