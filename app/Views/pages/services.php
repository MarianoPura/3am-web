<?php
$this->extend('layouts.base');
$capabilities = config('app.capabilities');
$tech = config('app.section_technology');
$ventures = config('app.ventures_section');
?>
<?php $this->start('title') ?>Services — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('services')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Media production, technology and event systems under one roof.<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', [
    'label' => 'Services / 01', 'title' => 'The full chain, in house.',
    'description' => 'Production, technology and event systems under one roof — so the camera, the encoder, the LED wall and the platform it all lands on are handled by the same team.',
]) ?>
<?php $serviceNumber = 1; foreach ($capabilities as $key => $group): ?>
<section class="section service-section service-section--<?= e_attr($key) ?><?= $key === 'events' ? ' service-section--reverse' : '' ?>" id="<?= e_attr($key) ?>" data-theme="<?= $key === 'technology' ? 'dark' : 'light' ?>" aria-labelledby="<?= e_attr($key) ?>-heading">
  <div class="shell">
    <?php if ($key !== 'technology'): ?>
    <div class="service-layout<?= $key === 'events' ? ' service-layout--reverse' : '' ?>">
      <div class="service-layout__copy">
        <div class="service-section__meta">
          <p class="mono section__label"><?= e($group['label']) ?></p>
          <span class="service-section__rule" aria-hidden="true"></span>
          <span class="mono service-section__index" aria-hidden="true"><?= e(str_pad((string) $serviceNumber, 2, '0', STR_PAD_LEFT)) ?></span>
        </div>
        <h2 id="<?= e_attr($key) ?>-heading" class="section__title"><?= e($key === 'media' ? config('app.section_media.headline') : $group['label']) ?></h2>
        <p class="section__lede"><?= e($group['summary']) ?></p>
        <ul class="service-list">
          <?php foreach (array_unique(array_merge($group['items'], $key === 'media' ? config('app.section_media.tags') : [])) as $item): ?><li><?= e($item) ?></li><?php endforeach ?>
        </ul>

        <p class="service-why"><?= e($group['why']) ?></p>
      </div>
        <div class="service-layout__image" data-reveal>
          <?= $this->partial('partials.frame', ['slot' => $key === 'media' ? 'channel.media' : 'venture.events']) ?>
          <p class="mono image-note"><?= e($key === 'media' ? config('app.section_media.subhead') : 'Rooms, stages and technical production') ?></p>
        </div>

    </div>
    <?php else: ?>
      <?= $this->partial('partials.technology-panel', [
          'summary' => $group['summary'],
          'index' => $serviceNumber,
      ]) ?>
      <p class="service-why"><?= e($group['why']) ?></p>
      <div class="delivery-example">
        <blockquote class="scenario">
          <p>A livestream is not one thing. It is a chain, and every link is a place it can fail. On a hybrid conference, if the camera crew, the LED vendor and the platform team are three different companies, a dropped frame becomes a three-way finger-pointing exercise while the room waits.</p>
          <p class="scenario__punch">Here, one team owns the whole chain.</p>
        </blockquote>
        <?= $this->partial('partials.frame', ['slot' => 'systems.control_room']) ?>
      </div>
    <?php endif ?>
  </div>
</section>
<?php $serviceNumber++; endforeach ?>
<section class="section service-section service-section--ventures" id="ventures" data-theme="light" data-service-index="04" aria-labelledby="ventures-heading">
  <div class="shell">
    <div class="service-section__meta service-section__meta--standalone">
      <p class="mono section__label">Ventures</p>
      <span class="service-section__rule" aria-hidden="true"></span>
      <span class="mono service-section__index" aria-hidden="true">04</span>
    </div>
    <h2 id="ventures-heading" class="section__title"><?= e($ventures['headline']) ?></h2>
    <p class="section__lede"><?= e($ventures['subhead']) ?></p>
    <?= $this->partial('partials.venture-cards') ?>
  </div>
</section>
<?php $this->end() ?>
