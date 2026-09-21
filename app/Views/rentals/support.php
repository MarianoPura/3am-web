<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Support — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/support')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Get support for rental planning, production requirements and equipment coordination from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-cta-band">
      <p class="rentals-kicker">Support</p>
      <h2>Need help planning your rental?</h2>
      <p>Use the inquiry form or reach out to the 3AM team with your production requirements, venue information and preferred timing.</p>
      <div class="rentals-inline-actions">
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('/start?type=Rentals')) ?>">Start an inquiry</a>
      </div>
    </div>
  </div>
</section>
<?php $this->end() ?>
