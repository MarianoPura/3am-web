<?php
$this->extend('layouts.base');
$company = config('app.company');
?>
<?php $this->start('title') ?>About — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('about')) ?><?php $this->end() ?>
<?php $this->start('description') ?>3AM Media, Technology and Ventures Inc. — media production, technology and event systems in Quezon City, Philippines.<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', [
    'label' => 'About / 04', 'title' => 'Three channels. One team.',
    'description' => $company['legal_name'],
]) ?>
<section class="section about-story" data-theme="light" aria-labelledby="about-heading">
  <div class="shell editorial-split">
    <div class="about-story__image" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => 'channel.ventures']) ?>
      <p class="mono image-note">Media. Technology. Ventures.</p>
    </div>
    <div>
      <p class="mono section__label">Est. <?= e($company['founded']) ?> · <?= e($company['address']['locality']) ?></p>
      <h2 id="about-heading" class="section__title">One team handles everything.</h2>
      <p class="section__lede">One team handles everything — from the camera to the screen your audience is watching on. No handoffs, no gaps where the stream can drop.</p>
      <p class="about-story__body">Production, technology and event systems under one roof — so the camera, the encoder, the LED wall and the platform it all lands on are handled by the same team.</p>
      <a class="text-link" href="<?= e_attr(url('services')) ?>">Explore our services <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</section>
<section class="section about-channels" data-theme="dark" aria-labelledby="about-channels-heading">
  <div class="shell">
    <p class="mono section__label">What we do</p><h2 id="about-channels-heading" class="section__title">Three channels, one signal.</h2>
    <div class="about-pillars">
      <?php foreach (config('app.channels') as $key => $channel): ?>
        <article><span class="channel__mark" aria-hidden="true"></span><h3><?= e($channel['label']) ?></h3><p class="about-pillar__line"><?= e($channel['line']) ?></p><p><?= e($channel['body']) ?></p><a class="text-link" href="<?= e_attr(url('services') . '#' . $key) ?>">Explore <?= e($channel['label']) ?> <span aria-hidden="true">↗</span></a></article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<section class="section" data-theme="light" aria-labelledby="approach-heading">
  <div class="shell"><p class="mono section__label">Our approach</p><h2 id="approach-heading" class="section__title">Built to stay on air.</h2>
    <div class="proof">
      <?php foreach (config('app.proof') as $fact): ?><article class="proof__item"><h3 class="proof__label"><?= e($fact['label']) ?></h3><p class="proof__body"><?= e($fact['body']) ?></p></article><?php endforeach ?>
    </div>
    <a class="btn btn--ghost" href="<?= e_attr(url('projects')) ?>">See our work</a>
  </div>
</section>
<?= $this->partial('partials.project-cta') ?>
<?php $this->end() ?>