<?php
/**
 * Primary navigation.
 *
 * Anchors, not page links. /work, /about and /services do not exist yet, and a
 * navigation full of 404s is worse than a navigation that goes where it says.
 * The site functions as a complete single page until those templates land in
 * Phases 2-3, at which point these become real routes and this file is the one
 * place that changes.
 *
 * The mark is the triangle cluster alone. The full lockup goes here once the
 * source vector is in /brand/ — the wordmark is artwork and is never
 * substituted with typed text in a web font.
 *
 * @var App\Core\View $this
 */
$homeUrl = rtrim(url('/'), '/') . '/';
$pagePath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$homePath = rtrim($homeUrl, '/');
$isHome = $pagePath === $homePath || $pagePath === $homePath . '/index.php';
$sectionUrl = static fn (string $id): string => ($isHome ? '' : $homeUrl) . '#' . $id;
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
      <a href="<?= e_attr($sectionUrl('channels')) ?>">What we do</a>
      <a href="<?= e_attr($sectionUrl('capabilities')) ?>">Capabilities</a>
      <a href="<?= e_attr($sectionUrl('track-record')) ?>">Track record</a>
      <a href="<?= e_attr($sectionUrl('media')) ?>">Media</a>
      <a href="<?= e_attr($sectionUrl('technology')) ?>">Technology</a>
      <a href="<?= e_attr($sectionUrl('ventures')) ?>">Ventures</a>
    </nav>

    <a class="btn btn--sm" href="<?= e_attr(url('start/media')) ?>">Start a project</a>

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
    <a href="<?= e_attr($sectionUrl('channels')) ?>">What we do</a>
    <a href="<?= e_attr($sectionUrl('capabilities')) ?>">Capabilities</a>
    <a href="<?= e_attr($sectionUrl('track-record')) ?>">Track record</a>
    </nav>
    <nav class="nav__panel-group nav__panel-group--channels" aria-label="Our channels">
    <a href="<?= e_attr($sectionUrl('media')) ?>">Media</a>
    <a href="<?= e_attr($sectionUrl('technology')) ?>">Technology</a>
    <a href="<?= e_attr($sectionUrl('ventures')) ?>">Ventures</a>
    </nav>
    <a class="btn nav__cta" href="<?= e_attr(url('start/media')) ?>">Start a project</a>
    <div class="nav__contact">
      <p class="mono">Get in touch</p>
      <a href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
    </div>
  </div>
</header>
