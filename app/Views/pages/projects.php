<?php
/**
 * Projects showcase.
 *
 * Renders from config/projects.php. Filtering is CSS-only via :has() with a
 * radio group, so the grid works with JavaScript disabled — a portfolio that
 * needs a script to show its contents is a portfolio that sometimes shows
 * nothing.
 *
 * @var App\Core\View $this
 * @var array $projects
 * @var array $categories
 * @var array $company
 */

$this->extend('layouts.base');

$featured = array_values(array_filter($projects, static fn (array $p): bool => !empty($p['featured'])));
$rest     = array_values(array_filter($projects, static fn (array $p): bool => empty($p['featured'])));
?>

<?php $this->start('title') ?>Projects — 3AM Media, Technology and Ventures<?php $this->end() ?>
<?php $this->start('description') ?>Selected production, event technology and digital work by 3AM Media, Technology and Ventures Inc.<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('projects')) ?><?php $this->end() ?>

<?php $this->start('content') ?>

<div class="projects-surface" data-theme="dark">
<section class="section projects-head page-intro" data-theme="dark" aria-labelledby="projects-heading" style="padding-top: 100px">
  <div class="shell">
    <p class="mono section__label">Projects / 03</p>
    <h1 id="projects-heading" class="section__title">Selected work.</h1>
    <p class="section__lede">
      Production, event technology and digital platforms — the whole chain,
      delivered by one team.
    </p>
  </div>
</section>

<section class="section section--tight project-showreel" data-theme="dark" aria-label="3AM showreel" style="padding-top: 0">
  <div class="shell">
    <div class="showreel-heading"><p class="mono section__label">In the field</p><span class="mono">3AM showreel</span></div>
    <?= $this->partial('partials.frame', ['slot' => 'track.featured']) ?>
  </div>
</section>
<?php if ($projects === []): ?>

  <?php /* Honest empty state. No invented clients: naming work that has not
           been cleared is the one unrecoverable mistake on a portfolio site.
           The capability frames still give the page real visual weight. */ ?>
  <section class="section" data-theme="dark" aria-label="Work in preparation">
    <div class="shell">


      <div class="reel-grid">
        <?php foreach ([
            ['track.broadcast', 'Broadcast', 'Multi-camera, switching, live'],
            ['track.events',    'Events',    'LED, AV, staging'],
            ['track.digital',   'Digital',   'Web, streaming, systems'],
            ['track.stills',    'Stills',    'Corporate, event, product'],
        ] as [$slotKey, $title, $note]): ?>
          <article class="reel-card" data-reveal>
            <?= $this->partial('partials.frame', ['slot' => $slotKey]) ?>
            <h2 class="reel-card__title"><?= e($title) ?></h2>
            <p class="mono reel-card__note"><?= e($note) ?></p>
          </article>
        <?php endforeach ?>
      </div>
      <div class="emerging" data-reveal>
        <span class="badge mono">In production</span>
        <p class="emerging__body">
          Case studies are being prepared for publication. For the current
          portfolio and showreel, get in touch — we will send it across.
        </p>
        <a class="btn" href="<?= e_attr(url('start')) ?>">Request the portfolio</a>
      </div>
    </div>
  </section>

<?php else: ?>

  <?php /* Filter. Radios rather than buttons so the whole thing works without
           JavaScript; the grid responds through :has() on the fieldset. */ ?>
  <section class="section section--tight" data-theme="dark">
    <div class="shell">
      <fieldset class="filter" id="project-filter">
        <legend class="mono filter__legend">Filter by</legend>

        <input class="filter__radio" type="radio" name="cat" id="cat-all" value="all" checked>
        <label class="filter__chip mono" for="cat-all">All</label>

        <?php foreach ($categories as $category): ?>
          <?php $id = 'cat-' . str_slug($category); ?>
          <input class="filter__radio" type="radio" name="cat"
                 id="<?= e_attr($id) ?>" value="<?= e_attr(str_slug($category)) ?>">
          <label class="filter__chip mono" for="<?= e_attr($id) ?>"><?= e($category) ?></label>
        <?php endforeach ?>
      </fieldset>
    </div>
  </section>

  <?php if ($featured !== []): ?>
    <section class="section section--tight" data-theme="dark" aria-label="Featured project">
      <div class="shell">
        <?php foreach ($featured as $project): ?>
          <article class="feature" data-reveal
                   data-cat="<?= e_attr(str_slug((string) ($project['category'] ?? ''))) ?>">
            <?= $this->partial('partials.frame', [
                'ratio' => '21x9',
                'label' => $project['title'] ?? 'Featured',
                'src'   => $project['image'] ?? null,
                'alt'   => $project['alt'] ?? '',
                'video' => $project['video'] ?? null,
            ]) ?>
            <div class="feature__body">
              <h2 class="feature__title"><?= e($project['title'] ?? '') ?></h2>
              <p class="mono feature__meta">
                <?= e($project['client'] ?? '') ?>
                <?php if (!empty($project['year'])): ?> · <?= e($project['year']) ?><?php endif ?>
                <?php if (!empty($project['category'])): ?> · <?= e($project['category']) ?><?php endif ?>
              </p>
              <?php if (!empty($project['scope'])): ?>
                <p class="feature__scope"><?= e($project['scope']) ?></p>
              <?php endif ?>
            </div>
          </article>
        <?php endforeach ?>
      </div>
    </section>
  <?php endif ?>

  <section class="section" data-theme="dark" aria-label="All projects">
    <div class="shell">
      <div class="project-grid">
        <?php foreach ($rest as $project): ?>
          <article class="project-card" data-reveal
                   data-cat="<?= e_attr(str_slug((string) ($project['category'] ?? ''))) ?>">
            <?= $this->partial('partials.frame', [
                'ratio' => '16x9',
                'label' => $project['title'] ?? 'Project',
                'src'   => $project['image'] ?? null,
                'alt'   => $project['alt'] ?? '',
                'video' => $project['video'] ?? null,
            ]) ?>
            <h2 class="project-card__title"><?= e($project['title'] ?? '') ?></h2>
            <p class="mono project-card__meta">
              <?= e($project['client'] ?? '') ?>
              <?php if (!empty($project['year'])): ?> · <?= e($project['year']) ?><?php endif ?>
            </p>
            <?php if (!empty($project['scope'])): ?>
              <p class="project-card__scope"><?= e($project['scope']) ?></p>
            <?php endif ?>
          </article>
        <?php endforeach ?>
      </div>
    </div>
  </section>

<?php endif ?>

<?php /* CTA */ ?>
</div>
<section class="section cta" aria-labelledby="projects-cta">
  <div class="shell">
    <h2 id="projects-cta" class="cta__title">What are you building?</h2>
    <div class="cta__actions">
      <a class="btn btn--invert" href="<?= e_attr(url('start')) ?>">Start a project</a>
    </div>
  </div>
</section>

<?php $this->end() ?>
