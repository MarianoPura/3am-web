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
    <section class="rentals-admin__panel rentals-dashboard-services"><div class="rentals-admin__heading"><h2>Service requests</h2><a href="<?= e_attr(url('rentals/admin/service-requests')) ?>">Review services →</a></div><div class="rentals-dashboard-services__counts">
      <?php $counts=array_column($serviceSummary??[],'total','status'); foreach(\App\Services\RentalServiceRequests::STATUSES as $key=>$label): ?><a href="<?= e_attr(url('rentals/admin/service-requests').'?status='.$key) ?>"><span><?= e($label) ?></span><strong><?= (int)($counts[$key]??0) ?></strong></a><?php endforeach ?>
    </div></section>
    <section id="rental-analytics" class="rentals-dashboard-analytics"><h2>Rental analytics</h2><?= $this->partial('rentals.partials.admin-analytics',['insights'=>$insights]) ?></section>
  </div>
</section>
<?php $this->end() ?>
