<?php
/**
 * Facebook / Meta Ads landing page — /get-a-quote.
 *
 * Built around one action. Every button on the page goes to #lead-form; the
 * site navigation is replaced by a logo and a CTA (chrome=minimal), and the
 * only way off the page is the email address in the footer.
 *
 * Content comes from config/landing.php and the existing config/app.php
 * facts. Nothing here is a claim that the rest of the site does not already
 * make.
 *
 * @var App\Core\View $this
 * @var array $landing
 * @var array $form
 * @var array $old
 * @var array $errors
 * @var array $company
 * @var array $capabilities
 * @var array $proof
 * @var array $video  ['kind' => file|embed|none, 'src' => ?string]
 */

$this->extend('layouts.base');

$slots  = (array) config('assets.slots', []);
$poster = site_media($landing['video_poster'] ?? null);
$hero   = $landing['hero'];
?>

<?php $this->start('title') ?><?= e($landing['meta']['title']) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= e($landing['meta']['description']) ?><?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url((string) $landing['path'])) ?><?php $this->end() ?>
<?php $this->start('chrome') ?>minimal<?php $this->end() ?>
<?php $this->start('chrome-cta') ?><?= e($hero['cta']) ?><?php $this->end() ?>

<?php $this->start('head') ?>
<meta property="og:image" content="<?= e_attr(absolute_url((string) $landing['meta']['og_image'])) ?>">
<meta property="og:image:width" content="1600">
<meta property="og:image:height" content="900">
<link rel="stylesheet" href="<?= e_attr(versioned('css/landing.css')) ?>">
<?php $this->end() ?>

<?php $this->start('content') ?>

<?php /* Read by js/landing.js. The Pixel loads only when an ID is set. */ ?>
<div hidden data-lp-config id="landing-config"
     data-pixel-id="<?= e_attr($landing['meta_pixel_id']) ?>"
     data-content-name="<?= e_attr($hero['kicker']) ?>"
     data-visit-id="<?= e_attr((string) ($visit_id ?? '')) ?>"
     data-has-fb-id="<?= !empty($has_fb_id) ? '1' : '0' ?>"
     data-csrf-token="<?= e_attr(csrf_token()) ?>"
     data-track-url="<?= e_attr(url('/track-event')) ?>"></div>

<?php /* ══ HERO + FORM ═════════════════════════════════════════ */ ?>
<section class="lp-hero" data-theme="dark" aria-labelledby="lp-title">
  <div class="shell lp-hero__grid">

    <div class="lp-hero__copy">

      <h1 id="lp-title" class="lp-hero__title"><?= e($hero['headline']) ?></h1>
      <p class="lp-hero__lede"><?= e($hero['lede']) ?></p>
      <div class="lp-hero__media">
        <?php if (($video['kind'] ?? 'none') === 'file'): ?>
          <video autoplay muted loop playsinline preload="auto" poster="<?= e_attr($poster ?? '') ?>">
            <source src="<?= e_attr($video['src']) ?>" type="video/mp4">
          </video>
        <?php else: ?>
          <?= $this->partial('partials.frame', [
              'slot'    => 'track.featured',
              'ratio'   => '16x9',
              'video'   => '',
              'play'    => false,
              'caption' => null,
          ]) ?>
        <?php endif ?>
      </div>
      <ul class="lp-trust" aria-label="About 3AM">
        <li>Established in <?= e($company['founded'] ?? '2021') ?></li>
        <li>100% In-house crew &amp; equipment</li>
        <li>Broadcast-grade cameras &amp; switchers</li>
        <li><?= e($company['address']['locality']) ?>, <?= e($company['address']['region']) ?></li>
      </ul>

      <!-- Quick jump CTA — opens modal on mobile, scrolls on desktop -->
      <a class="btn lp-hero__quick-cta" href="#lead-form" data-lp-cta data-lp-modal-trigger>
        <span>Get a Quote for Your Event</span>
        <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v10.586l3.293-3.293a1 1 0 111.414 1.414l-5 5a1 1 0 01-1.414 0l-5-5a1 1 0 111.414-1.414L9 14.586V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
      </a>
    </div>

    <div class="lp-hero__form">
      <?= $this->partial('partials.lead-form', [
          'form'     => $form,
          'copy'     => $landing['form'],
          'old'      => $old,
          'errors'   => $errors,
          'visit_id' => $visit_id ?? null,
      ]) ?>
    </div>

  </div>
</section>

<?php /* ══ MOBILE QUOTE MODAL ════════════════════════════════════ */ ?>
<div class="lp-modal" id="lp-quote-modal" role="dialog" aria-modal="true" aria-labelledby="lp-modal-heading" hidden data-lp-modal>
  <div class="lp-modal__backdrop" data-lp-modal-close></div>
  <div class="lp-modal__sheet">
    <div class="lp-modal__header">
      <span class="lp-modal__brand mono">3AM Digital Media</span>
      <button class="lp-modal__close" type="button" aria-label="Close quote form" data-lp-modal-close>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/></svg>
      </button>
    </div>
    <div class="lp-modal__body">
      <?= $this->partial('partials.lead-form', [
          'form'     => $form,
          'copy'     => $landing['form'],
          'old'      => $old,
          'errors'   => $errors,
          'visit_id' => $visit_id ?? null,
      ]) ?>
    </div>
  </div>
</div>

<?php /* ══ SERVICES (CAROUSEL ON MOBILE) ═══════════════════════ */ ?>
<section class="section lp-services" id="services" data-theme="dark" aria-labelledby="lp-services-heading" data-lp-view>
  <div class="shell">
    <div class="lp-services__header">
      <div>
        <p class="mono section__label">Services</p>
        <h2 id="lp-services-heading" class="section__title section__title--sm"><?= e($landing['services']['heading']) ?></h2>
      </div>
      <div class="lp-carousel__arrows" aria-label="Services carousel controls">
        <button type="button" class="lp-carousel__arrow lp-carousel__arrow--prev" data-lp-carousel-prev aria-label="Previous service">‹</button>
        <button type="button" class="lp-carousel__arrow lp-carousel__arrow--next" data-lp-carousel-next aria-label="Next service">›</button>
      </div>
    </div>

    <div class="lp-carousel" data-lp-carousel>
      <div class="lp-cards lp-carousel__track" data-lp-carousel-track>
        <?php $slideIdx = 0; foreach ($landing['services']['items'] as $key => $slot): ?>
          <?php if (!isset($capabilities[$key])) { continue; } $cap = $capabilities[$key]; ?>
          <article class="lp-card lp-carousel__slide <?= $slideIdx === 0 ? 'is-active' : '' ?>" data-lp-carousel-slide data-slide-index="<?= $slideIdx ?>">
            <?= $this->partial('partials.frame', ['slot' => $slot, 'ratio' => '16x9', 'video' => '', 'play' => false]) ?>
            <div class="lp-card__body">
              <h3 class="lp-card__title"><?= e($cap['label']) ?></h3>
              <p class="lp-card__summary"><?= e($cap['summary']) ?></p>
              <p class="lp-card__benefit"><?= e($cap['why']) ?></p>
              <ul class="tags lp-card__tags" aria-label="<?= e_attr($cap['label']) ?> services">
                <?php foreach (array_slice($cap['items'], 0, 4) as $item): ?>
                  <li class="tag"><?= e($item) ?></li>
                <?php endforeach ?>
              </ul>
            </div>
          </article>
        <?php $slideIdx++; endforeach ?>
      </div>

      <div class="lp-carousel__dots" data-lp-carousel-dots aria-label="Services carousel pagination">
        <?php for ($i = 0; $i < $slideIdx; $i++): ?>
          <button type="button" class="lp-carousel__dot <?= $i === 0 ? 'is-active' : '' ?>" data-lp-carousel-dot="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"></button>
        <?php endfor ?>
      </div>
    </div>
  </div>
</section>

<?php /* ══ PROBLEM → ANSWER ═════════════════════════════════════ */ ?>
<section class="section lp-problems" data-theme="light" aria-labelledby="lp-problems-heading" style="
    padding-bottom: 50px;
    padding-top: 45px;">
  <div class="shell lp-split">
    <div>
      <h2 id="lp-problems-heading" class="section__title section__title--sm"><?= e($landing['problems']['heading']) ?></h2>
      <ul class="lp-pains">
        <?php foreach ($landing['problems']['items'] as $pain): ?>
          <li><?= e($pain) ?></li>
        <?php endforeach ?>
      </ul>
    </div>
    <div class="lp-answer">
      <h3 class="lp-answer__title"><?= e($landing['problems']['answer_heading']) ?></h3>
      <p><?= e($landing['problems']['answer']) ?></p>
      <a class="btn btn--invert" href="#lead-form" data-lp-cta data-lp-modal-trigger><?= e($hero['cta']) ?></a>
    </div>
  </div>
</section>

<?php /* ══ WHY 3AM ═══════════════════════════════════════════════ */ ?>
<section class="section lp-why" data-theme="light" aria-labelledby="lp-why-heading" style="
    padding-top: 0px;
    padding-bottom: 75px;
">
  <div class="shell">
    <h2 id="lp-why-heading" class="section__title section__title--sm"><?= e($landing['why']['heading']) ?></h2>
    <div class="proof lp-proof">
      <?php foreach ($proof as $fact): ?>
        <article class="proof__item">
          <h3 class="proof__label"><?= e($fact['label']) ?></h3>
          <p class="proof__body"><?= e($fact['body']) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<?php /* ══ RECENT WORK — real stills, in place of testimonials ══════ */ ?>
<section class="section lp-work" data-theme="dark" aria-labelledby="lp-work-heading">
  <div class="shell">
    <p class="mono section__label">Our work</p>
    <h2 id="lp-work-heading" class="section__title section__title--sm"><?= e($landing['work']['heading']) ?></h2>
    <p class="section__lede"><?= e($landing['work']['lede']) ?></p>

    <div class="lp-gallery">
      <?php foreach ($landing['work']['slots'] as $slot): ?>
        <?php if (!isset($slots[$slot])) { continue; } ?>
        <?= $this->partial('partials.frame', [
            'slot'    => $slot,
            'ratio'   => '16x9',
            'video'   => '',
            'play'    => false,
            'caption' => $slots[$slot]['label'] ?? null,
        ]) ?>
      <?php endforeach ?>
    </div>
  </div>
</section>

<?php /* ══ CLIENT LOGOS CAROUSEL ═══════════════════════════════════ */ ?>
<section class="section lp-clients" data-theme="dark" aria-label="Clients we have worked with">
  <div class="shell">
    <p class="mono section__label">Trusted By</p>
    <div class="lp-logos-track-wrapper" aria-hidden="true">
      <div class="lp-logos-track" data-lp-logos-track>
        <?php
          /* ─────────────────────────────────────────────────────────────────
           * CLIENT LOGOS — easy to replace.
           *
           * To swap or add a logo:
           * 1. Place an SVG or PNG in media/clients/ (or any media folder).
           * 2. Update the 'logo' path and 'name' in the $clients array below.
           * ────────────────────────────────────────────────────────────────
           */
          $clients = [
            ['name' => 'Crystal Quartz 2',             'logo' => 'media/clients/cryztal-quartz.png'],
            ['name' => 'Civil-Military Operations School (CMOC AFP)', 'logo' => 'media/clients/civil-military-school.png'],
            ['name' => 'Deakin University',             'logo' => 'media/clients/deakin-university.png'],
            ['name' => 'Titanium 2 Life Insurance Agency Inc.', 'logo' => 'media/clients/titanium-2.png'],
          ];
        ?>
        <?php foreach ($clients as $client): ?>
        <div class="lp-logos-item">
          <img src="<?= e_attr(site_media($client['logo'])) ?>" alt="<?= e_attr($client['name']) ?>" width="160" height="110" loading="lazy">
        </div>
        <?php endforeach ?>
        <!-- Duplicate set for seamless infinite loop -->
        <?php foreach ($clients as $client): ?>
        <div class="lp-logos-item" aria-hidden="true">
          <img src="<?= e_attr(site_media($client['logo'])) ?>" alt="" width="160" height="110" loading="lazy">
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</section>

<?php /* ══ FINAL CTA ═════════════════════════════════════════════ */ ?>
<section class="section lp-final" data-theme="dark" aria-labelledby="lp-final-heading">
  <div class="shell lp-narrow lp-final__inner">
    <h2 id="lp-final-heading" class="section__title section__title--sm"><?= e($landing['final']['heading']) ?></h2>
    <p class="section__lede"><?= e($landing['final']['body']) ?></p>
    <a class="btn lp-final__btn" href="#lead-form" data-lp-cta data-lp-modal-trigger><?= e($landing['final']['cta']) ?></a>
  </div>
</section>


<?php $this->end() ?>

<?php $this->start('scripts') ?>
<script src="<?= e_attr(versioned('js/landing.js')) ?>" nonce="<?= e_attr($nonce ?? '') ?>" defer></script>
<?php $this->end() ?>
