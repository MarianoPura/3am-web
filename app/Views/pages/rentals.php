<?php $this->extend('layouts.base'); ?>
<?php
$categoryMeta = [
    'camera-video' => ['Camera / video', 'Capture resources for interviews, events and content production.', 'channel.media'],
    'audio' => ['Audio', 'Audio support for presenters, rooms and live programs.', 'venture.events'],
    'lighting' => ['Lighting', 'Lighting options for sets, stages and event coverage.', 'venture.stage'],
    'display' => ['LED / display', 'Presentation and viewing systems for rooms, talks and events.', 'channel.technology'],
    'broadcast' => ['Livestream / production', 'Signal and production resources for live or hybrid delivery.', 'systems.control_room'],
    'studio-space' => ['Studio / space', 'Production environments for planned shoots and sessions.', 'venture.studio'],
];
$categoryNames = [];
foreach ((array) $categories as $category) {
    $id = (string) ($category['id'] ?? '');
    $categoryNames[$id] = (string) ($category['name'] ?? ($categoryMeta[$id][0] ?? 'Rental option'));
}
$catalogItems = array_values((array) $items);
$catalogCount = count($catalogItems);
?>
<?php $this->start('title') ?>Rentals — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Explore production, event, technical and creative resources available through 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>

<section class="page-intro rentals-hero" data-theme="light" aria-labelledby="rentals-title">
  <div class="shell rentals-hero__layout">
    <div>
      <p class="mono section__label">Rentals / 03</p>
      <h1 id="rentals-title" class="page-intro__title">Rentals.</h1>
      <p class="section__lede">Explore production, event, technical and creative resources available through 3AM.</p>
    </div>
    <div class="rentals-hero__image">
      <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
      <p class="mono image-note">Representative production environment</p>
    </div>
  </div>
</section>

<section class="section rental-categories" data-theme="light" aria-labelledby="rental-categories-heading">
  <div class="shell">
    <p class="mono section__label">Browse by category</p>
    <h2 id="rental-categories-heading" class="section__title section__title--sm">Find the right starting point.</h2>
    <div class="rental-category-strip" aria-label="Rental categories">
      <?php foreach ((array) $categories as $category): ?>
        <?php $categoryId = (string) ($category['id'] ?? ''); $meta = $categoryMeta[$categoryId] ?? [($category['name'] ?? 'Rental option'), 'Production resources for your brief.', 'venture.rentals']; ?>
        <a class="rental-category" href="#rental-catalog" data-rental-category-link="<?= e_attr($categoryId) ?>">
          <?= $this->partial('partials.frame', ['slot' => $category['slot'] ?? $meta[2], 'ratio' => '4x3']) ?>
          <div class="rental-category__body">
            <h3><?= e($category['name'] ?? $meta[0]) ?></h3>
            <p><?= e($category['description'] ?? $meta[1]) ?></p>
            <span class="mono">Browse options ↘</span>
          </div>
        </a>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section rental-browse" id="rental-catalog" data-theme="light" data-rental-catalog aria-labelledby="rental-catalog-heading">
  <div class="shell">
    <div class="section__head rental-catalog__head">
      <div><p class="mono section__label">Resources</p><h2 id="rental-catalog-heading" class="section__title section__title--sm">Explore rental options.</h2></div>
      <div class="rental-catalog__intro"><p>These representative options help shape a package around your production or event. Availability, final inclusions and pricing are confirmed on inquiry.</p></div>
    </div>

    <?php if ($catalogItems !== []): ?>
      <div class="rental-tools" aria-label="Rental catalogue controls">
        <label class="rental-search"><span class="mono">Search rentals</span><input class="field__input" type="search" data-rental-search placeholder="Camera, audio, studio..." autocomplete="off" aria-controls="rental-items"></label>
        <fieldset class="rental-filter">
          <legend class="mono">Filter by category</legend>
          <div class="rental-filter__options">
            <button class="rental-filter__button is-active" type="button" data-rental-filter="all" aria-pressed="true" aria-controls="rental-items">All rentals</button>
            <?php foreach ((array) $categories as $category): ?><button class="rental-filter__button" type="button" data-rental-filter="<?= e_attr($category['id'] ?? '') ?>" aria-pressed="false" aria-controls="rental-items"><?= e($category['name'] ?? 'Rental option') ?></button><?php endforeach ?>
          </div>
        </fieldset>
      </div>

      <p class="rental-catalog__status" data-rental-status role="status" aria-live="polite">Showing <?= e((string) $catalogCount) ?> rental options.</p>
      <div class="rental-items" id="rental-items" data-rental-items>
        <?php foreach ($catalogItems as $item): ?>
          <?php
          $itemCategory = (string) ($item['category'] ?? '');
          $itemCategoryName = $categoryNames[$itemCategory] ?? 'Rental option';
          $itemName = (string) ($item['name'] ?? 'Rental option');
          $itemDescription = (string) ($item['description'] ?? 'Representative rental option.');
          $itemIdealFor = (string) ($item['ideal_for'] ?? 'Production and events');
          $itemIncludes = (string) ($item['includes'] ?? 'Package details confirmed on inquiry');
          $searchText = strtolower(implode(' ', [$itemName, $itemCategoryName, $itemDescription, $itemIdealFor, $itemIncludes]));
          ?>
          <article class="rental-item-card" data-rental-item data-rental-category="<?= e_attr($itemCategory) ?>" data-rental-search="<?= e_attr($searchText) ?>">
            <?= $this->partial('partials.frame', ['slot' => $item['slot'] ?? null, 'ratio' => '16x9']) ?>
            <div class="rental-item-card__body">
              <div class="rental-item-card__meta"><span class="mono"><?= !empty($item['is_sample']) ? 'Representative option' : e($item['availability_status'] ?? 'Inquire for availability') ?></span><span class="tag tag--cat mono"><?= e($itemCategoryName) ?></span></div>
              <h3><?= e($itemName) ?></h3>
              <p class="rental-item-card__description"><?= e($itemDescription) ?></p>
              <p class="rental-item-card__ideal"><span class="mono">Ideal for</span> <?= e($itemIdealFor) ?></p>
              <details class="rental-item-card__package"><summary>Typical package</summary><p><?= e($itemIncludes) ?></p></details>
              <a class="btn btn--sm" href="<?= e_attr(url('contact')) ?>">Ask about this option</a>
            </div>
          </article>
        <?php endforeach ?>
      </div>
      <button class="btn btn--ghost rental-catalog__more" type="button" data-rental-more hidden aria-controls="rental-items" aria-expanded="false">Show more options</button>
      <p class="rental-catalog__empty" data-rental-empty hidden>No rentals match that search. Try another category or contact us about your brief.</p>
    <?php else: ?>
      <div class="inventory-note"><div><p class="mono section__label">Catalogue being prepared</p><h2>Tell us what you need.</h2><p>Contact us for the current rental list, availability and package details.</p></div><a class="btn" href="<?= e_attr(url('contact')) ?>">Contact 3AM</a></div>
    <?php endif ?>
    <p class="image-note">Photographs illustrate production work and rental services; they do not represent confirmed inventory.</p>
  </div>
</section>

<section class="section rental-use-cases" data-theme="light" aria-labelledby="rental-use-cases-heading">
  <div class="shell">
    <p class="mono section__label">Browse by purpose</p>
    <h2 id="rental-use-cases-heading" class="section__title section__title--sm">Resources shaped around the brief.</h2>
    <div class="rental-use-cases__grid">
      <?php foreach ([['For productions', 'Camera, lighting, audio and supporting production resources.'], ['For events', 'Presentation, display and technical resources for live programs.'], ['For livestreaming', 'Switching, encoding, monitoring and multi-camera workflows.'], ['For creative work', 'Studio environments and practical production support.']] as [$title, $body]): ?>
        <article><span class="tally" aria-hidden="true"></span><h3><?= e($title) ?></h3><p><?= e($body) ?></p></article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="section rental-assistance" data-theme="dark" aria-labelledby="rental-assistance-heading">
  <div class="shell cta__split">
    <div><p class="mono">Rental support</p><h2 id="rental-assistance-heading" class="section__title section__title--sm">Need help finding the right setup?</h2></div>
    <div class="cta__actions"><a class="btn" href="<?= e_attr(url('contact')) ?>">Contact 3AM</a><a class="btn btn--ghost" href="<?= e_attr(url('start')) ?>">Start a project</a></div>
  </div>
</section>

<?php $this->end() ?>
