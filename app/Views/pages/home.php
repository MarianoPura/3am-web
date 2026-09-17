<?php
/** Homepage overview; detailed information lives on dedicated pages. */

$this->extend('layouts.base');

?>

<?php $this->start('title') ?>3AM Media, Technology and Ventures — Media. Technology. Possibilities.<?php $this->end() ?>

<?php $this->start('description') ?>Full-chain media production, event technology, and digital platform development. Video, livestreaming, LED/AV systems, and web development — one team, Quezon City, Philippines.<?php $this->end() ?>


<?php $this->start('content') ?>

<?php /* ══ 01 · HERO ═══════════════════════════════════════════ */ ?>
<section class="hero" id="top" data-theme="dark" data-hero>
  <div class="shell hero__grid">

    <div class="hero__copy">
      <div class="hero__kicker" data-reveal>
        <span class="tally" aria-hidden="true"></span>
        <span class="mono"><?= e($company['legal_name']) ?></span>
        <span class="mono hero__sep" aria-hidden="true">·</span>
        <span class="mono">Est. <?= e($company['founded']) ?></span>
      </div>

      <h1 class="hero__title">
        <span class="line" data-reveal-line><span>Media.</span></span>
        <span class="line" data-reveal-line><span>Technology.</span></span>
        <span class="line" data-reveal-line><span>Possibilities.</span></span>
      </h1>

      <p class="hero__lede" data-reveal>
        One team handles everything &mdash; from the camera to the screen your
        audience is watching on. No handoffs, no gaps where the stream can drop.
      </p>

      <?php /* Two CTAs, not one. A client booking a corporate video and a
               client specifying AV for a 500-person hybrid conference are
               different buyers with different urgency and budget; funnelling
               both through one button loses the qualification for free. The
               split also reinforces the Media / Technology structure. */ ?>
      <div class="hero__actions" data-reveal>
        <a class="btn" href="<?= e_attr(url('start/media')) ?>">Start a media project</a>
        <a class="btn btn--ghost" href="<?= e_attr(url('start/technology')) ?>">Start a tech project</a>
      </div>
    </div>

    <div class="hero__media" data-reveal>
      <?php /* Slot defined in config/assets.php — 'hero.showreel' */ ?>
      <?= $this->partial('partials.frame', ['slot' => 'hero.showreel', 'play' => true]) ?>
    </div>

  </div>


</section>


<?php /* ══ 02 · WHAT WE DO ═════════════════════════════════════ */ ?>
<section class="section channels-section" id="channels" data-theme="light"
         aria-labelledby="channels-heading">


  <div class="shell">
    <p class="mono section__label" data-reveal>What we do</p>
    <h2 id="channels-heading" class="section__title" data-reveal>
      Three channels, one signal.
    </h2>

    <p class="section__lede"><?= e($company['legal_name']) ?> — media production, technology and event systems in <?= e($company['address']['locality']) ?>, Philippines.</p>
    <a class="text-link" href="<?= e_attr(url('about')) ?>">About 3AM <span aria-hidden="true">↗</span></a>
    <div class="channels" data-carousel="Three Channels">
      <?php foreach ($channels as $key => $channel): ?>
        <article class="channel" data-reveal data-channel="<?= e_attr($key) ?>">
          <?= $this->partial('partials.frame', ['slot' => 'channel.' . $key]) ?>
          <div class="channel__body">
            <span class="channel__mark" aria-hidden="true"></span>
            <h3 class="channel__label"><?= e($channel['label']) ?></h3>
            <p class="channel__line"><?= e($channel['line']) ?></p>
            <p class="channel__body-text"><?= e($channel['body']) ?></p>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>


<?php /* Compact services preview. */ ?>
<section class="section" id="capabilities" data-theme="light"
         aria-labelledby="capabilities-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>Capabilities</p>
    <h2 id="capabilities-heading" class="section__title" data-reveal>
      The full chain, in house.
    </h2>
    <p class="section__lede" data-reveal>
      Production, technology and event systems under one roof — so the camera,
      the encoder, the LED wall and the platform it all lands on are handled by
      the same team.
    </p>

    <div class="rack" data-carousel="Capabilities" role="region" aria-label="Capabilities">
      <?php $unit = 1; foreach ($capabilities as $capKey => $group): ?>
        <article class="rack__unit" id="capability-<?= e_attr($capKey) ?>" data-reveal>
          <header class="rack__head">
            <span class="mono rack__index"><?= e(str_pad((string) $unit++, 2, '0', STR_PAD_LEFT)) ?></span>
            <h3 class="rack__title"><?= e($group['label']) ?></h3>
          </header>

          <p class="rack__summary"><?= e($group['summary']) ?></p>

          <a class="text-link" href="<?= e_attr(url('services') . '#' . $capKey) ?>">Explore <?= e($group['label']) ?> <span aria-hidden="true">↗</span></a>


        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>


<?php /* MEDIA =================================================
         The production and creative side. Gallery reads from
         config/projects.php; anything absent falls back to a
         designed placeholder from config/assets.php. */ ?>
<section class="section" id="media" data-theme="light" aria-labelledby="media-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>Selected work</p>
    <h2 id="media-heading" class="section__title" data-reveal><?= e($sectionMedia['headline']) ?></h2>
    <p class="section__lede" data-reveal><?= e($sectionMedia['subhead']) ?></p>

    <div class="work-grid" data-carousel="Selected Work" role="region" aria-label="Media work gallery">
      <?php foreach (array_slice($mediaWork, 0, 3, true) as $i => $item): ?>
        <?php $workSlot = config('assets.slots')['media.work' . ($i + 1)]; ?>
        <article class="work-card" data-reveal>
          <?= $this->partial('partials.frame', [
              'slot'  => 'media.work' . ($i + 1),
          ]) ?>
          <div class="work-card__body">
            <?php if (!empty($item['category'])): ?><span class="tag tag--cat mono"><?= e($item['category']) ?></span><?php endif ?>
            <h3 class="work-card__title"><?= e($item['title'] ?? $workSlot['label']) ?></h3>
            <?php if (!empty($item['scope'])): ?><p class="work-card__note"><?= e($item['scope']) ?></p><?php endif ?>
          </div>
        </article>
      <?php endforeach ?>
    </div>

    <div class="section__cta" data-reveal>
      <a class="btn" href="<?= e_attr(url('projects')) ?>">Explore projects</a>
    </div>
  </div>
</section>



<section class="section rental-preview" id="rentals" data-theme="light" aria-labelledby="rentals-heading">
  <div class="shell editorial-split">
    <div>
      <p class="mono section__label">Rentals</p>
      <h2 id="rentals-heading" class="section__title">Equipment and space rentals.</h2>
      <p class="section__lede"><?= e(config('app.ventures')[3]['body']) ?></p>
      <a class="btn" href="<?= e_attr(url('equipment-rentals')) ?>">Explore rentals</a>
    </div>
    <div class="rental-preview__image" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
      <p class="mono image-note">Equipment and space · Inquire for the current list</p>
    </div>
  </div>
</section>

<?php $this->end() ?>
