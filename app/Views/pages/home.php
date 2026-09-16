<?php
/**
 * Homepage.
 *
 * SITEMAP — anchors on this page
 *   #top             Hero
 *   #channels        What We Do — the three channels
 *   #capabilities    Capabilities — Media / Technology / Events
 *   #track-record    Proof — verifiable facts, reel request
 *   #media           Media — capabilities + work gallery
 *   #technology      Technology — signal path
 *   #ventures        Ventures — studio, stage, events, rentals
 *   #contact         Contact
 *
 * CONTENT STILL NEEDED FROM THE CLIENT — see README "Placeholders"
 *   Every <figure class="frame"> without a src is an empty media slot. The
 *   label names what belongs there and the ratio is fixed, so dropping in the
 *   real asset needs no layout change.
 *
 * NO COUNTERS ON THIS PAGE, DELIBERATELY.
 *   The previous version rendered "0 Years operating" because the markup
 *   carried 0 as its resting state and relied on JavaScript to correct it.
 *   Facts are now stated in words and are correct with JavaScript disabled.
 *
 * @var App\Core\View $this
 * @var array $channels
 * @var array $capabilities
 * @var array $proof
 * @var array $ventures       Studio / Stage / Event Management / Rentals
 * @var array $sectionMedia
 * @var array $sectionTech
 * @var array $sectionVentures
 * @var array $mediaWork      Six gallery entries; blanks render placeholders
 * @var array $company
 */

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

  <div class="hero__scroll mono" aria-hidden="true">
    <span>Scroll</span>
    <span class="hero__scroll-line"></span>
  </div>
</section>


<?php /* ══ 02 · WHAT WE DO ═════════════════════════════════════ */ ?>
<section class="section" id="channels" data-theme="light"
         aria-labelledby="channels-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>What we do</p>
    <h2 id="channels-heading" class="section__title" data-reveal>
      Three channels, one signal.
    </h2>

    <p class="section__lede"><?= e($company['legal_name']) ?> — media production, technology and event systems in <?= e($company['address']['locality']) ?>, Philippines.</p>
    <div class="channels">
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


<?php /* ══ 03 · CAPABILITIES ═══════════════════════════════════
         Text-dense by design, for contrast against the media-heavy
         sections either side. Each unit now carries a "why it
         matters" line so the list does not read as buzzwords. */ ?>
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

    <div class="rack" tabindex="0" role="region" aria-label="Capabilities">
      <?php $unit = 1; foreach ($capabilities as $capKey => $group): ?>
        <article class="rack__unit" id="capability-<?= e_attr($capKey) ?>" data-reveal>
          <header class="rack__head">
            <span class="mono rack__index"><?= e(str_pad((string) $unit++, 2, '0', STR_PAD_LEFT)) ?></span>
            <h3 class="rack__title"><?= e($group['label']) ?></h3>
          </header>

          <p class="rack__summary"><?= e($group['summary']) ?></p>

          <a class="text-link" href="<?= e_attr(url('services') . '#' . $capKey) ?>">Explore <?= e($group['label']) ?> <span aria-hidden="true">↗</span></a>

          <p class="rack__why"><?= e($group['why']) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>


<?php /* ══ 04 · TRACK RECORD ════════════════════════════════════
         Replaces the empty "Selected work" placeholder and the
         zeroed counters. Facts in words — correct with JavaScript
         disabled, and impossible to render as "0".

         Swap for real case study cards as soon as cleared client
         work exists; that is strictly stronger than this. */ ?>
<section class="section" id="track-record" data-theme="dark"
         aria-labelledby="track-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>Track record</p>
    <h2 id="track-heading" class="section__title" data-reveal>
      Built to stay on air.
    </h2>

    <div class="proof">
      <?php foreach ($proof as $fact): ?>
        <article class="proof__item" data-reveal>
          <h3 class="proof__label"><?= e($fact['label']) ?></h3>
          <p class="proof__body"><?= e($fact['body']) ?></p>
        </article>
      <?php endforeach ?>
    </div>

    <div class="reel" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => 'track.featured', 'play' => true]) ?>
    </div>

    <?php /* The four supporting galleries remain on /projects. */ ?>
    <div class="reel-cta" data-reveal>
      <a class="btn" href="<?= e_attr(url('projects')) ?>">Explore our projects</a>
    </div>
  </div>
</section>


<section class="section rental-preview" id="rentals" data-theme="light" aria-labelledby="rentals-heading">
  <div class="shell editorial-split">
    <div>
      <p class="mono section__label">Equipment rentals</p>
      <h2 id="rentals-heading" class="section__title">Equipment and space rentals.</h2>
      <p class="section__lede"><?= e(config('app.ventures')[3]['body']) ?></p>
      <div class="rental-preview__categories">
        <?php foreach (config('rentals.categories') as $category): ?><span><?= e($category['name']) ?></span><?php endforeach ?>
      </div>
      <a class="btn" href="<?= e_attr(url('equipment-rentals')) ?>">Explore equipment rentals</a>
    </div>
    <div class="rental-preview__image" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
      <p class="mono image-note">Equipment and space · Inquire for the current list</p>
    </div>
  </div>
</section>
<?php /* MEDIA =================================================
         The production and creative side. Gallery reads from
         config/projects.php; anything absent falls back to a
         designed placeholder from config/assets.php. */ ?>
<section class="section" id="media" data-theme="light" aria-labelledby="media-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>Media</p>
    <h2 id="media-heading" class="section__title" data-reveal><?= e($sectionMedia['headline']) ?></h2>
    <p class="section__lede" data-reveal><?= e($sectionMedia['subhead']) ?></p>

    <ul class="tags" data-reveal tabindex="0" aria-label="Services">
      <?php foreach ($sectionMedia['tags'] as $tag): ?>
        <li class="tag mono"><?= e($tag) ?></li>
      <?php endforeach ?>
    </ul>

    <div class="work-grid" tabindex="0" role="region" aria-label="Media work gallery">
      <?php foreach ($mediaWork as $i => $item): ?>
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
      <a class="btn" href="<?= e_attr(url('start/media')) ?>">Start a media project</a>
      <a class="btn btn--ghost" href="<?= e_attr(url('projects')) ?>">Explore our projects</a>
    </div>
  </div>
</section>


<?php /* TECHNOLOGY — services and production imagery. */ ?>
<section class="section systems" id="technology" data-theme="light"
         aria-labelledby="technology-heading">
  <div class="shell">
    <?= $this->partial('partials.technology-panel') ?>
  </div>
</section>


<?php /* VENTURES ==============================================
         Four real business lines. The CTA is softer than the other
         two on purpose: a day's equipment rental and a full event
         management brief are very different sales, and "Start a
         project" fits neither. */ ?>
<section class="section" id="ventures" data-theme="light" aria-labelledby="ventures-heading">
  <div class="shell">
    <p class="mono section__label" data-reveal>Ventures</p>
    <h2 id="ventures-heading" class="section__title" data-reveal><?= e($sectionVentures['headline']) ?></h2>
    <p class="section__lede" data-reveal><?= e($sectionVentures['subhead']) ?></p>

    <p class="venture-overview">
      <?php foreach ($ventures as $venture): ?><span><?= e($venture['name']) ?></span><?php endforeach ?>
    </p>
    <div class="section__cta" data-reveal>
      <a class="btn" href="<?= e_attr(url('services') . '#ventures') ?>">Explore Ventures</a>
    </div>
  </div>
</section>


<?php /* ══ 08 · CONTACT ════════════════════════════════════════
         TODO(phase-5): replace the mailto with the three-step
         inquiry form writing to contact_inquiries. The chips below
         become step one of that form and are non-interactive until
         then — a control that looks clickable but is not is worse
         than plain text. */ ?>
<section class="section cta" id="contact" aria-labelledby="cta-heading">
  <div class="shell">
    <h2 id="cta-heading" class="cta__title" data-reveal>What are you building?</h2>

    <ul class="chips" data-reveal>
      <?php foreach (['Media Production', 'Event', 'Livestream', 'Technology', 'Website', 'Digital Content'] as $chip): ?>
        <li class="chip mono"><?= e($chip) ?></li>
      <?php endforeach ?>
    </ul>

    <div class="cta__actions" data-reveal>
      <a class="btn btn--invert" href="<?= e_attr(url('start/media')) ?>">Start a media project</a>
      <a class="btn btn--invert" href="<?= e_attr(url('start/technology')) ?>">Start a tech project</a>
    </div>
  </div>
</section>

<?php $this->end() ?>
