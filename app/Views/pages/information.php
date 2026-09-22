<?php $this->extend('layouts.base'); ?>
<?php $this->start('title') ?>Information — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('information')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Learn about the media production, technology, event systems and production support 3AM works with.<?php $this->end() ?>
<?php $this->start('content') ?>

<?php
$informationCards = [
    ['Media production', 'Video, photography and production support for different project requirements.', 'media.work1'],
    ['Technology & event systems', 'Technical solutions and systems supporting events and productions.', 'channel.technology'],
    ['Livestream production', 'Multi-camera, switching, monitoring and live production workflows.', 'systems.control_room'],
    ['Production systems', 'Technical resources that support production environments.', 'venture.rentals'],
    ['Event support', 'Technical and production support for events, conferences and presentations.', 'venture.events'],
    ['Creative environments', 'Spaces and resources used to support creative and production work.', 'venture.studio'],
];
?>

<section class="section information-feature" data-theme="light" aria-labelledby="information-feature-heading">
  <div class="shell information-feature__layout">
    <div class="information-feature__copy">
      <p class="mono section__label">Information</p>
      <h2 id="information-feature-heading" class="section__title section__title--sm">Built around the work.</h2>
      <p class="section__lede">Explore the media, technology, event systems and production support that connect a brief to its audience.</p>
      <a class="btn" href="<?= e_attr(url('rentals')) ?>">Start renting <span aria-hidden="true">↗</span></a>
    </div>

    <div class="information-carousel" data-information-carousel role="region" aria-roledescription="carousel" aria-label="3AM capabilities">
      <div class="information-carousel__viewport" data-information-viewport tabindex="0">
        <div class="information-carousel__track" data-information-track>
          <?php foreach ($informationCards as $index => [$title, $body, $slot]): ?>
            <article class="information-slide" data-information-slide aria-label="<?= e_attr(($index + 1) . ' of ' . count($informationCards)) ?>">
              <?= $this->partial('partials.frame', ['slot' => $slot, 'ratio' => '4x3']) ?>
              <div class="information-slide__body">
                <p class="mono"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></p>
                <h3><?= e($title) ?></h3>
                <p><?= e($body) ?></p>
              </div>
            </article>
          <?php endforeach ?>
        </div>
      </div>
      <div class="information-carousel__nav">
        <div class="information-carousel__dots" data-information-dots aria-label="Choose a carousel slide"></div>
        <p class="sr-only" data-information-status aria-live="polite"></p>
      </div>
    </div>
  </div>
</section>

<?php $this->end() ?>
