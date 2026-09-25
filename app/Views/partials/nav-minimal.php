<?php
/**
 * Minimal Header for Ad Landing Page
 *
 * Provides brand identity on the left and a single direct CTA to #lead-form on the right,
 * with no external navigation links so visitors stay focused on converting.
 *
 * @var string $cta Label for the header button; empty hides it.
 */
$brandMark = site_media(config('assets.brand.mark'));
?>
<header class="nav nav--minimal" data-nav>
  <div class="nav__inner">
    <div class="nav__brand-group">
      <span class="nav__mark">
        <?php if ($brandMark !== null): ?>
          <img src="<?= e_attr($brandMark) ?>" width="36" height="36" alt="<?= e_attr(config('app.brand')) ?>">
        <?php else: ?>
          <svg viewBox="0 0 100 100" width="32" height="32" role="img" aria-label="<?= e_attr(config('app.brand')) ?>" focusable="false">
            <g fill="currentColor" stroke="currentColor" stroke-width="7" stroke-linejoin="round">
              <polygon points="50,12 70,45 30,45"/>
              <polygon points="29,54 49,87 9,87"/>
              <polygon points="71,54 91,87 51,87"/>
            </g>
          </svg>
        <?php endif ?>
      </span>
      <span class="mono nav__brand"><?= e(config('app.brand')) ?></span>
    </div>

    <?php if (!empty($cta)): ?>
      <a class="btn btn--sm nav__cta-minimal" href="#lead-form" data-lp-cta><?= e($cta) ?></a>
    <?php endif ?>
  </div>
</header>
