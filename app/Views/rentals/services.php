<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Services — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/services')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Technical and event support services for rentals, production coordination and live operations.<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Services</p>
      <h2>Technical support that keeps production moving.</h2>
      <p>Our rental support covers planning, setup, live coordination and operational continuity for events, media work and studio production.</p>
    </div>

    <div class="rentals-services-grid">
      <?php foreach ([
        ['Equipment Setup', 'Coordinate practical setup, positioning and configuration for event and studio environments.'],
        ['Production Support', 'Provide technical oversight, troubleshooting and operational continuity for live productions.'],
        ['Event Coordination', 'Support on-site presentation, flow and technical readiness before and during the event.'],
        ['Livestream Ops', 'Monitor signal paths, audio levels and control-room coordination for live broadcast workflows.'],
      ] as [$title, $body]): ?>
        <article class="rentals-service">
          <p class="rentals-card__meta">Service</p>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
