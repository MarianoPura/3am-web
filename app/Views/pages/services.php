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
<nav class="section-jump" data-theme="light" aria-label="Service sections">
  <div class="shell"><a href="#media">Media</a><a href="#technology">Technology</a><a href="#events">Events</a><a href="#ventures">Ventures</a></div>
</nav>
<?php foreach ($capabilities as $key => $group): ?>
<section class="section service-section" id="<?= e_attr($key) ?>" data-theme="light" aria-labelledby="<?= e_attr($key) ?>-heading">
  <div class="shell">
    <?php if ($key !== 'technology'): ?>
    <div class="service-layout">
      <div class="service-layout__copy">
        <p class="mono section__label"><?= e($group['label']) ?></p>
        <h2 id="<?= e_attr($key) ?>-heading" class="section__title"><?= e($key === 'media' ? config('app.section_media.headline') : $group['label']) ?></h2>
        <p class="section__lede"><?= e($group['summary']) ?></p>
        <ul class="service-list">
          <?php foreach (array_unique(array_merge($group['items'], $key === 'media' ? config('app.section_media.tags') : [])) as $item): ?><li><?= e($item) ?></li><?php endforeach ?>
        </ul>

        <p class="service-why"><?= e($group['why']) ?></p>
          <a class="btn" href="<?= e_attr(url('start/' . ($key === 'media' ? 'media' : 'ventures'))) ?>"><?= $key === 'media' ? 'Start a media project' : 'Inquire about events' ?></a>
      </div>
        <div class="service-layout__image" data-reveal>
          <?= $this->partial('partials.frame', ['slot' => $key === 'media' ? 'channel.media' : 'venture.events']) ?>
          <p class="mono image-note"><?= e($key === 'media' ? config('app.section_media.subhead') : 'Rooms, stages and technical production') ?></p>
        </div>

    </div>
    <?php else: ?>
      <?= $this->partial('partials.technology-panel', ['summary' => $group['summary']]) ?>
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
<?php endforeach ?>
<section class="section" id="ventures" data-theme="light" aria-labelledby="ventures-heading">
  <div class="shell">
    <p class="mono section__label">Ventures</p>
    <h2 id="ventures-heading" class="section__title"><?= e($ventures['headline']) ?></h2>
    <p class="section__lede"><?= e($ventures['subhead']) ?></p>
    <?= $this->partial('partials.venture-cards') ?>
  </div>
</section>
<?= $this->partial('partials.project-cta') ?>
<?php $this->end() ?>
