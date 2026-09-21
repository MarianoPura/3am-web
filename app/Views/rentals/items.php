<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Inventory — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/items')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Review available rental inventory, categories and production equipment support from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$itemsList = is_array($items ?? null) ? (array) $items : [];
$catalogItems = [];
foreach ($itemsList as $item) {
    $catalogItems[] = [
        'id' => (string) ($item['id'] ?? ''),
        'name' => (string) ($item['name'] ?? 'Rental item'),
        'description' => (string) ($item['description'] ?? 'Production equipment for event and studio use.'),
        'category' => (string) ($item['category'] ?? 'production'),
        'availability_status' => (string) ($item['availability_status'] ?? 'Inquire for availability'),
        'includes' => (string) ($item['includes'] ?? ''),
    ];
}
if ($catalogItems === []) {
    $catalogItems = [
        ['id' => 'mirrorless-camera-kit', 'name' => 'Mirrorless Camera Kit', 'description' => 'A flexible production package for content capture, interviews and event coverage.', 'category' => 'camera', 'availability_status' => 'Inquire for availability', 'includes' => 'Camera body · Lens · Tripod · Capture media'],
        ['id' => 'wireless-audio-kit', 'name' => 'Wireless Audio Kit', 'description' => 'Reliable wireless audio support for interviews, stage events and livestream productions.', 'category' => 'audio', 'availability_status' => 'Inquire for availability', 'includes' => 'Transmitters · Receivers · Headset mic'],
    ];
}
?>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Rental inventory</p>
      <h2>Browse available equipment.</h2>
      <p>Explore representative production resources with practical support for live, studio and event work.</p>
    </div>

    <div class="rentals-catalog-grid">
      <?php foreach ($catalogItems as $item): ?>
        <article class="rentals-item-card" data-rentals-item data-category="<?= e_attr($item['category']) ?>">
          <div class="rentals-item-card__image">
            <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '4x3']) ?>
          </div>
          <div class="rentals-item-card__body">
            <span class="rentals-card__meta"><?= e($item['category']) ?></span>
            <h3><?= e($item['name']) ?></h3>
            <p><?= e($item['description']) ?></p>
            <div class="rentals-item-card__meta-row">
              <span><?= e($item['availability_status']) ?></span>
              <span><?= e($item['id']) ?></span>
            </div>
            <?php if ($item['includes'] !== ''): ?>
              <p class="rentals-item-card__inclusions"><?= e($item['includes']) ?></p>
            <?php endif ?>
            <a class="rentals-item-card__link" href="<?= e_attr(url('/start?type=Rentals&rental=' . rawurlencode($item['id']))) ?>">Request this rental →</a>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
