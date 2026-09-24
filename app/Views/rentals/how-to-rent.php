<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>How to Rent — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/how-to-rent')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Learn how to request rental equipment and production support from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">How to rent</p><h1>A direct path from brief to confirmation.</h1></div>
    <p>Start with what the production needs. The 3AM team will review the fit, timing and support requirements with you.</p>
  </div>
</section>

<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">How to rent</p>
      <h2>A practical, direct rental process.</h2>
      <p>Start with the brief, review the equipment fit, then send your request so the 3AM team can confirm availability and coordination.</p>
    </div>

    <div class="rentals-steps-grid">
      <?php foreach ([
        ['1. Share the brief', 'Tell us what you are making, where it needs to happen and how the equipment will be used.'],
        ['2. Review the fit', 'Match the production type, schedule and technical requirements with the right rental package.'],
        ['3. Submit the request', 'Send your rental inquiry and include dates, venue information and any special requirements.'],
        ['4. Confirm coordination', 'The team reviews availability, support needs and next steps before final confirmation.'],
      ] as $stepIndex => [$title, $body]): ?>
        <article class="rentals-step">
          <p class="rentals-step__number"><?= e(str_pad((string) ($stepIndex + 1), 2, '0', STR_PAD_LEFT)) ?></p>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>
    <div class="rentals-inline-actions"><a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse rental items</a></div>
  </div>
</section>
<?php $this->end() ?>
