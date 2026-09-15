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
?>
<header class="nav" data-nav>
  <div class="nav__inner">

    <a class="nav__mark" href="<?= e_attr(url('/')) ?>" aria-label="<?= e_attr(config('app.brand')) ?> — home">
      <svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">
        <g fill="currentColor" stroke="currentColor" stroke-width="7" stroke-linejoin="round">
          <polygon points="50,12 70,45 30,45"/>
          <polygon points="29,54 49,87 9,87"/>
          <polygon points="71,54 91,87 51,87"/>
        </g>
      </svg>
    </a>

    <nav class="nav__links" aria-label="Primary">
      <a href="<?= e_attr(url('/')) ?>#channels">What we do</a>
      <a href="<?= e_attr(url('/')) ?>#capabilities">Capabilities</a>
      <a href="<?= e_attr(url('/')) ?>#track-record">Track record</a>
      <a href="<?= e_attr(url('/')) ?>#media">Media</a>
      <a href="<?= e_attr(url('/')) ?>#technology">Technology</a>
      <a href="<?= e_attr(url('/')) ?>#ventures">Ventures</a>
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
    <a href="<?= e_attr(url('/')) ?>#channels">What we do</a>
    <a href="<?= e_attr(url('/')) ?>#capabilities">Capabilities</a>
    <a href="<?= e_attr(url('/')) ?>#track-record">Track record</a>
    <a href="<?= e_attr(url('/')) ?>#media">Media</a>
    <a href="<?= e_attr(url('/')) ?>#technology">Technology</a>
    <a href="<?= e_attr(url('/')) ?>#ventures">Ventures</a>
    <a href="<?= e_attr(url('start/media')) ?>">Start a project</a>
  </div>
</header>
