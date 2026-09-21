<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>3AM Rentals — Production Equipment & Support<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Browse production equipment and technical support resources for events, studio production, livestreaming and digital media work.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$categoriesList = is_array($categories ?? null) ? (array) $categories : [];
$itemsList = is_array($items ?? null) ? (array) $items : [];
$featured = array_slice($itemsList, 0, 3);
$categoryCards = [];
foreach ($categoriesList as $category) {
    $id = (string) ($category['id'] ?? '');
    $count = 0;
    foreach ($itemsList as $item) {
        if (($item['category'] ?? '') === $id) {
            $count++;
        }
    }
    $categoryCards[] = [
        'id' => $id,
        'name' => (string) ($category['name'] ?? ucfirst($id)),
        'description' => 'Equipment and support resources for ' . strtolower((string) ($category['name'] ?? 'production')) . ' work.',
        'count' => $count,
    ];
}
if ($categoryCards === []) {
    $categoryCards = [
        ['id' => 'camera', 'name' => 'Camera', 'description' => 'Production cameras, support, and capture gear for live and studio workflows.', 'count' => 1],
        ['id' => 'audio', 'name' => 'Audio', 'description' => 'Wireless, recording and monitoring solutions for reliable capture.', 'count' => 1],
        ['id' => 'lighting', 'name' => 'Lighting', 'description' => 'Flexible lighting for events, studio work and stage builds.', 'count' => 1],
    ];
}
?>
<section class="rentals-hero">
  <div class="rentals-shell rentals-hero__grid">
    <div>
      <p class="rentals-kicker">3AM Rentals</p>
      <h1>Equipment and production resources for your next project.</h1>
      <p>Browse reliable production equipment and rental resources for events, content production, livestreaming, studio work and technical operations.</p>
      <div class="rentals-hero__actions">
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse Rental Items</a>
        <a class="rentals-btn rentals-btn--secondary" href="<?= e_attr(url('rentals/how-to-rent')) ?>">How to Rent</a>
      </div>
      <ul class="rentals-badges" aria-label="Rental categories">
        <li>Events</li>
        <li>Media</li>
        <li>Livestream</li>
        <li>Studio</li>
      </ul>
    </div>
    <div class="rentals-hero__media">
      <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
    </div>
  </div>
</section>

<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Browse by category</p>
      <h2>Find the right equipment for your production requirements.</h2>
      <p>Explore the categories that support events, transportable media operations, livestream capture and technical planning.</p>
    </div>

    <div class="rentals-grid">
      <?php foreach ($categoryCards as $category): ?>
        <article class="rentals-card">
          <div class="rentals-card__image">
            <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '16x9']) ?>
          </div>
          <div class="rentals-card__body">
            <span class="rentals-card__meta"><?= e((string) ($category['count'] ?? 0)) ?> items</span>
            <h3><?= e((string) ($category['name'] ?? 'Equipment')) ?></h3>
            <p><?= e((string) ($category['description'] ?? 'Production equipment and resources for your workflow.')) ?></p>
            <a class="rentals-card__link" href="<?= e_attr(url('rentals/categories')) ?>">View category →</a>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Featured rentals</p>
      <h2>Available equipment and resources.</h2>
    </div>

    <div class="rentals-catalog-grid">
      <?php foreach ($featured as $item): ?>
        <?php $itemCategory = (string) ($item['category'] ?? 'production'); ?>
        <article class="rentals-item-card" data-rentals-item data-category="<?= e_attr($itemCategory) ?>">
          <div class="rentals-item-card__image">
            <?= $this->partial('partials.frame', ['slot' => $item['slot'] ?? 'venture.rentals', 'ratio' => '4x3']) ?>
          </div>
          <div class="rentals-item-card__body">
            <span class="rentals-card__meta"><?= e($itemCategory) ?></span>
            <h3><?= e((string) ($item['name'] ?? 'Rental item')) ?></h3>
            <p><?= e((string) ($item['description'] ?? '')) ?></p>
            <div class="rentals-item-card__meta-row">
              <span><?= e((string) ($item['availability_status'] ?? 'Inquire for availability')) ?></span>
              <span><?= e((string) ($item['is_sample'] ? 'Sample' : 'Available')) ?></span>
            </div>
            <a class="rentals-item-card__link" href="<?= e_attr(url('rentals/items')) ?>">View Rental →</a>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Services</p>
      <h2>Support that keeps your production on track.</h2>
    </div>

    <div class="rentals-services-grid">
      <?php foreach ([
        ['Equipment Setup', 'On-site coordination and practical setup support for staged production environments.'],
        ['Technical Assistance', 'Production support for audio, visual systems and operational reliability.'],
        ['Event Support', 'Technical coordination for live programs, presentations and event operations.'],
        ['Livestream Support', 'Monitoring, switching and operational support for live broadcast environments.'],
      ] as [$title, $body]): ?>
        <article class="rentals-service">
          <p class="rentals-card__meta">Service</p>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>

    <div class="rentals-inline-actions">
      <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/services')) ?>">View Services</a>
    </div>
  </div>
</section>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">How renting works</p>
      <h2>Simple and practical from first browse to final coordination.</h2>
    </div>

    <div class="rentals-steps-grid">
      <?php foreach ([
        ['Browse', 'Find the equipment or resources you need for your event or production.'],
        ['Select', 'Review the item details, intended use and availability information.'],
        ['Send Request', 'Submit your rental requirements, preferred schedule and scope to the 3AM team.'],
        ['Confirmation', 'Availability and next steps are reviewed before the rental is confirmed.'],
      ] as [$title, $body]): ?>
        <article class="rentals-step">
          <p class="rentals-card__meta">Step</p>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">Use cases</p>
      <h2>Built around real production needs.</h2>
    </div>

    <div class="rentals-services-grid">
      <?php foreach (['Production', 'Events', 'Livestreaming', 'Creative Work'] as $useCase): ?>
        <article class="rentals-service">
          <p class="rentals-card__meta">Use case</p>
          <h3><?= e($useCase) ?></h3>
          <p>Equipment and support shaped for practical production needs, event coordination and technical delivery.</p>
        </article>
      <?php endforeach ?>
    </div>
  </div>
</section>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">
    <div class="rentals-cta-band">
      <p class="rentals-kicker">Support</p>
      <h2>Need help planning your rental?</h2>
      <p>Talk to the 3AM team about your equipment and production requirements.</p>
      <div class="rentals-inline-actions">
        <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/support')) ?>">Contact Rental Support</a>
      </div>
    </div>
  </div>
</section>
<?php $this->end() ?>
