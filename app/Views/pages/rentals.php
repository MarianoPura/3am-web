<?php $this->extend('layouts.base'); ?>
<?php $this->start('title') ?>Rentals — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Production equipment, technical resources and event support from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', ['label' => 'Rentals / 02', 'title' => 'Media. Technology. Possibilities.', 'description' => 'Explore the production equipment, technical resources and event support that help 3AM deliver each project.', 'cta' => ['href' => url('rentals'), 'label' => 'Explore rentals']]) ?>
<section class="section information-overview" data-theme="light" aria-labelledby="information-overview-heading">
  <div class="shell">
    <p class="mono section__label">How we work</p>
    <h2 id="information-overview-heading" class="section__title section__title--sm">Built around the work.</h2>
    <div class="information-grid">
      <?php foreach ([
        ['01 — Media production', 'Video production, photography, creative production and media support from the first frame through delivery.', 'media.work1'],
        ['02 — Technology & event systems', 'Platforms, AV systems, LED and display solutions, and technical support for live environments.', 'channel.technology'],
        ['03 — Livestream & production support', 'Multi-camera production, monitoring, switching and the signal path that keeps an audience connected.', 'systems.control_room'],
        ['04 — Production equipment', 'The cameras, lighting, audio and technical resources used to support a considered production brief.', 'venture.rentals'],
        ['05 — Events', 'Technical support for presentations, conferences, launches, productions and live programs.', 'venture.events'],
        ['06 — Studio & creative environment', 'Production spaces and practical coordination for shoots, recordings and creative sessions.', 'venture.studio'],
      ] as [$title, $body, $slot]): ?>
        <article class="information-card" data-reveal>
          <?= $this->partial('partials.frame', ['slot' => $slot, 'ratio' => '16x9']) ?>
          <div class="information-card__body"><p class="mono section__label"><?= e($title) ?></p><p><?= e($body) ?></p></div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<section class="section information-process" data-theme="dark" aria-labelledby="information-process-heading">
  <div class="shell editorial-split"><div><p class="mono section__label">Project support</p><h2 id="information-process-heading" class="section__title">Start with the brief.</h2></div><div><p class="section__lede">Tell us what you are making, where it needs to happen and what the audience should experience. We will help shape the production and technical path around it.</p></div></div>
</section>
<?php $this->end() ?>
