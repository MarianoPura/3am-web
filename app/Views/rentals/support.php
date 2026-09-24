<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Support — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/support')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Get support for rental planning, production requirements and equipment coordination from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">Rental support</p><h1>Plan the right setup.</h1></div>
    <p>Share the production requirements, venue information and preferred timing so the team can help shape the rental request.</p>
  </div>
</section>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-cta-band rentals-cta-band--split">
      <div><p class="rentals-kicker">Start with the brief</p><h2>Need help planning your rental?</h2><p>Use the inquiry form to tell 3AM what you are making and where it needs to happen.</p></div>
      <div class="rentals-inline-actions">
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('/start?type=Rentals')) ?>">Start an inquiry</a>
      </div>
    </div>
  </div>
</section>
<?php $this->end() ?>
