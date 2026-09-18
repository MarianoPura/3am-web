<?php
/** Primary navigation: one shared page list for desktop, mobile and footer. */
$brandMark = site_media(config('assets.brand.mark'));
?>
<header class="nav" data-nav>
  <div class="nav__inner">

    <a class="nav__mark" href="<?= e_attr(url('/')) ?>" aria-label="<?= e_attr(config('app.brand')) ?> — home">
      <?php if ($brandMark !== null): ?>
        <img src="<?= e_attr($brandMark) ?>" width="48" height="48" alt="">
      <?php else: ?>
      <svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">
        <g fill="currentColor" stroke="currentColor" stroke-width="7" stroke-linejoin="round">
          <polygon points="50,12 70,45 30,45"/>
          <polygon points="29,54 49,87 9,87"/>
          <polygon points="71,54 91,87 51,87"/>
        </g>
      </svg>
      <?php endif ?>
    </a>

    <nav class="nav__links" aria-label="Primary">
      <?= $this->partial('partials.site-links') ?>
    </nav>

    <a class="btn btn--sm" href="<?= e_attr(url('start')) ?>">Start a project</a>

    <?php /* Toggled by js/app.js; hidden on wide viewports via CSS.
             aria-expanded is kept in sync there. */ ?>
    <button class="nav__toggle" type="button" data-nav-toggle
            aria-expanded="false" aria-controls="nav-panel">
      <span class="nav__toggle-bar" aria-hidden="true"></span>
      <span class="nav__toggle-bar" aria-hidden="true"></span>
      <span class="sr-only">Menu</span>
    </button>

  </div>

  <?php /* Full-screen panel on touch and narrow viewports. Large targets,
           thumb-reachable — not a shrunken desktop bar. */ ?>
  <div class="nav__panel" id="nav-panel" data-nav-panel hidden>
    <nav class="nav__panel-group" aria-label="Explore">
      <?= $this->partial('partials.site-links') ?>
    </nav>
    <a class="btn nav__cta" href="<?= e_attr(url('start')) ?>">Start a project</a>
    <div class="nav__contact">
      <p class="mono">Get in touch</p>
      <a href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
    </div>
  </div>
</header>
