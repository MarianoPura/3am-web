<?php $this->extend('layouts.base'); ?>
<?php
$categoryNames = [];
foreach ((array) $categories as $category) {
    $categoryNames[(string) ($category['id'] ?? '')] = (string) ($category['name'] ?? 'Rental option');
}
$catalogItems = array_values((array) $items);
$catalogCount = count($catalogItems);
?>
<?php $this->start('title') ?>Rentals — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('equipment-rentals')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Rental options for production, events, livestreams and studio work. Contact 3AM for the current list, availability and package details.<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', [
    'label' => 'Rentals / 02', 'title' => 'Equipment. Space. Possibilities.',
    'description' => config('app.ventures')[3]['body'],
]) ?>

<section class="section rental-browse" id="rental-catalog" data-theme="light" data-rental-catalog aria-labelledby="rental-catalog-heading">
  <div class="shell">
    <div class="section__head rental-catalog__head">
      <div>
        <p class="mono section__label">Sample inventory</p>
        <h2 id="rental-catalog-heading" class="section__title section__title--sm">Representative rental options.</h2>
      </div>
      <div class="rental-catalog__intro">
        <p>Browse a small set of development options for production, events, and spaces. Final package details, availability, and pricing are confirmed on inquiry.</p>
        <a class="text-link" href="<?= e_attr(url('start/ventures')) ?>">Ask about a custom package <span aria-hidden="true">↗</span></a>
      </div>
    </div>

    <div class="rental-tools" aria-label="Rental catalogue controls">
      <label class="rental-search">
        <span class="mono">Search sample rentals</span>
        <input class="field__input" type="search" data-rental-search placeholder="Camera, audio, studio..." autocomplete="off" aria-controls="rental-items">
      </label>
      <fieldset class="rental-filter">
        <legend class="mono">Filter by category</legend>
        <div class="rental-filter__options">
          <button class="rental-filter__button is-active" type="button" data-rental-filter="all" aria-pressed="true" aria-controls="rental-items">All rentals</button>
          <?php foreach ((array) $categories as $category): ?>
            <button class="rental-filter__button" type="button" data-rental-filter="<?= e_attr($category['id'] ?? '') ?>" aria-pressed="false" aria-controls="rental-items"><?= e($category['name'] ?? 'Rental option') ?></button>
          <?php endforeach ?>
        </div>
      </fieldset>
    </div>

    <p class="rental-catalog__status" data-rental-status role="status" aria-live="polite">
      Showing <?= e((string) $catalogCount) ?> sample options.
    </p>

    <?php if ($catalogItems !== []): ?>
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
          $inquiryUrl = url('start/ventures') . '?rental=' . rawurlencode($itemName);
          ?>
          <article class="rental-item-card" data-rental-item data-rental-category="<?= e_attr($itemCategory) ?>" data-rental-search="<?= e_attr($searchText) ?>">
            <?= $this->partial('partials.frame', ['slot' => $item['slot'] ?? null, 'ratio' => '16x9']) ?>
            <div class="rental-item-card__body">
              <div class="rental-item-card__meta">
                <span class="mono">Sample option</span>
                <span class="tag tag--cat mono"><?= e($itemCategoryName) ?></span>
              </div>
              <h3><?= e($itemName) ?></h3>
              <p class="rental-item-card__description"><?= e($itemDescription) ?></p>
              <p class="rental-item-card__ideal"><span class="mono">Ideal for</span> <?= e($itemIdealFor) ?></p>
              <details class="rental-item-card__package">
                <summary>Typical package</summary>
                <p><?= e($itemIncludes) ?></p>
              </details>
              <a class="btn btn--sm" href="<?= e_attr($inquiryUrl) ?>">Inquire for availability</a>
            </div>
          </article>
        <?php endforeach ?>
      </div>
      <button class="btn btn--ghost rental-catalog__more" type="button" data-rental-more hidden aria-controls="rental-items" aria-expanded="false">Show more options</button>
      <p class="rental-catalog__empty" data-rental-empty hidden>No sample rentals match that search. Try another category or ask us to shape a package around your brief.</p>
    <?php else: ?>
      <div class="inventory-note">
        <div>
          <p class="mono section__label">Sample inventory</p>
          <h2>Tell us what you need.</h2>
          <p>Contact us for the current rental list, availability, and package details.</p>
        </div>
        <a class="btn" href="<?= e_attr(url('start/ventures')) ?>">Ask about rentals</a>
      </div>
    <?php endif ?>

    <p class="image-note">Photographs illustrate production work and rental services; they are not exact sample inventory. Availability and final package details are confirmed on inquiry.</p>
  </div>
</section>

<section class="section rental-process" data-theme="light" aria-labelledby="rental-process-heading">
  <div class="shell">
    <p class="mono section__label">How it works</p>
    <h2 id="rental-process-heading" class="section__title section__title--sm">Start with the brief.</h2>
    <div class="rental-process__steps">
      <article>
        <span class="mono">01</span>
        <h3>Tell us what you need.</h3>
        <p>Share the production, event, or space requirement and the date you are planning around.</p>
      </article>
      <article>
        <span class="mono">02</span>
        <h3>Shape the package.</h3>
        <p>We can clarify the useful combination of equipment, space, and technical support for the brief.</p>
      </article>
      <article>
        <span class="mono">03</span>
        <h3>Confirm the details.</h3>
        <p>Availability, final inclusions, and pricing are confirmed directly before anything is booked.</p>
      </article>
    </div>
  </div>
</section>

<section class="section rental-assistance" data-theme="dark" aria-labelledby="rental-assistance-heading">
  <div class="shell editorial-split rental-assistance__inner">
    <div class="rental-assistance__heading">
      <p class="mono section__label">Spaces &amp; operations</p>
      <h2 id="rental-assistance-heading" class="section__title">Beyond equipment.</h2>
    </div>
    <div class="rental-assistance__copy">
      <p class="section__lede"><?= e(config('app.ventures_section.subhead')) ?></p>
      <a class="text-link" href="<?= e_attr(url('services') . '#ventures') ?>">Explore studio, stage and event services <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</section>
<?php $this->end() ?>
