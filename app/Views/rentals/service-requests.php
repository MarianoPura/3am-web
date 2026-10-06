<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>My Service Requests — 3AM Rentals<?php $this->end() ?>
<?php $this->start('content') ?>
<?php $notice = $_SESSION['rentals_service_notice'] ?? null; unset($_SESSION['rentals_service_notice']); ?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Customer account</p><h1>Service requests.</h1></div><p>Your service enquiries, event requirements and team updates in one place.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell">
  <?php if (is_string($notice)): ?><p class="rentals-support-panel" role="status"><?= e($notice) ?></p><?php endif ?>
  <?php if (!empty($unavailable)): ?>
    <div class="rentals-support-panel" role="alert"><h2>Service requests are temporarily unavailable.</h2><p>Please contact the team for assistance.</p><a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/support')) ?>">Contact support</a></div>
  <?php else: ?>
    <form method="get" action="<?= e_attr(url('rentals/service-requests')) ?>" class="rentals-service-request-filter">
      <label class="rentals-date-field">Request status<select name="status"><option value="all">All statuses</option><?php foreach (\App\Services\RentalServiceRequests::STATUSES as $key => $label): ?><option value="<?= e_attr($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label><button type="submit" class="rentals-btn rentals-btn--dark">Filter</button>
      <a class="rentals-btn" href="<?= e_attr(url('rentals/services')) ?>">Browse services</a>
    </form>
    <?php if ($rows === []): ?><div class="rentals-support-panel"><h2>No <?= $statusFilter === 'all' ? '' : e($statusFilter) . ' ' ?>service requests yet.</h2><p>Submit a service enquiry to track its review here.</p></div><?php else: ?>
      <div class="rentals-orders"><?php foreach ($rows as $record): ?><article class="rentals-order rentals-service-request-card"><?= $this->partial('rentals.partials.service-request-details', ['record' => $record]) ?></article><?php endforeach ?></div>
      <?php if (count($rows) === 200): ?><p>Showing your latest 200 matching requests.</p><?php endif ?>
    <?php endif ?>
  <?php endif ?>
</div></section>
<?php $this->end() ?>
