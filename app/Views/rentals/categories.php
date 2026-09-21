<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rentals Categories — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/categories')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Browse 3AM rental categories for camera, audio, lighting and technical production support.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$categoriesList = is_array($categories ?? null) ? (array) $categories : [];
$itemsList = is_array($items ?? null) ? (array) $items : [];
$categoriesOut = [];
foreach ($categoriesList as $category) {
    $id = (string) ($category['id'] ?? '');
    $count = 0;
    foreach ($itemsList as $item) {
        if (($item['category'] ?? '') === $id) {
            $count++;
        }
    }
    $categoriesOut[] = [
        'id' => $id,
        'name' => (string) ($category['name'] ?? ucfirst($id)),
        'description' => 'Equipment and support resources for ' . strtolower((string) ($category['name'] ?? 'production')) . ' workflows.',
        'count' => $count,
    ];
}
if ($categoriesOut === []) {
    $categoriesOut = [
        ['id' => 'camera', 'name' => 'Camera', 'description' => 'Production cameras, support and capture gear for live and studio workflows.', 'count' => 1],
        ['id' => 'audio', 'name' => 'Audio', 'description' => 'Wireless, recording and monitoring tools for clean capture and event coverage.', 'count' => 1],
        ['id' => 'lighting', 'name' => 'Lighting', 'description' => 'Flexible lighting solutions for events, interviews and stage environments.', 'count' => 1],
    ];
}
?>
<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Categories</p>
      <h2>Browse Rental Categories</h2>
      <p>Find the equipment and technical resources that best suit your production needs.</p>
    </div>

    <div class="rentals-grid">
      <?php foreach ($categoriesOut as $category): ?>
        <article class="rentals-card">
          <div class="rentals-card__image">
            <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
          </div>
          <div class="rentals-card__body">
            <span class="rentals-card__meta"><?= e((string) ($category['count'] ?? 0)) ?> items</span>
            <h3><?= e((string) ($category['name'] ?? 'Equipment')) ?></h3>
            <p><?= e((string) ($category['description'] ?? 'Production support resources for your workflow.')) ?></p>
            <a class="rentals-card__link" href="<?= e_attr(url('rentals/items')) ?>">View items →</a>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php $this->end() ?>
