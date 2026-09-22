<?php
$this->extend('layouts.base');
$company = config('app.company');
?>
<?php $this->start('title') ?>Contact — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('contact')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Talk to 3AM about media, technology, events or production support.<?php $this->end() ?>
<?php $this->start('address-in-content') ?>yes<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', [
    'label' => 'Contact / 05', 'title' => 'What are you building?',
    'description' => 'Media. Technology. Possibilities.',
]) ?>
<section class="section contact-page" data-theme="light" aria-labelledby="contact-options-heading">
  <div class="shell contact-layout">
    <aside class="contact-details" data-theme="dark" aria-label="Contact information">
      <p class="mono section__label">Get in touch</p>
      <a class="contact-email" href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
      <p>We usually respond within one business day.</p>
      <div class="contact-address"><p class="mono">Find us</p><address><?= e($company['address']['street']) ?><br><?= e($company['address']['locality']) ?>, <?= e($company['address']['region']) ?></address></div>
      <p class="mono"><?= e($company['legal_name']) ?></p>
    </aside>
    <div class="contact-options">
      <h2 id="contact-options-heading" class="section__title section__title--sm">Start with your project.</h2>
      <p>Media / Production, Technology / Event Systems, or a general inquiry — one form for what you need.</p>
      <p class="contact-general">For other inquiries, <a href="mailto:<?= e_attr(config('app.contact_email')) ?>">email our team</a>.</p>
    </div>
  </div>
</section>
<?php $this->end() ?>
