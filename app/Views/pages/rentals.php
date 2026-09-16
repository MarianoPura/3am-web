<?php $this->extend('layouts.base'); ?>
<?php $this->start('title') ?>Equipment Rentals — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('equipment-rentals')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Equipment and space rentals — AV gear, staging and studio access. Contact 3AM for the current equipment list.<?php $this->end() ?>
<?php $this->start('content') ?>
<?= $this->partial('partials.page-intro', [
    'label' => 'Equipment rentals / 02', 'title' => 'Equipment. Space. Possibilities.',
    'description' => config('app.ventures')[3]['body'],
]) ?>
<section class="section rental-browse" data-theme="light" aria-labelledby="rental-browse-heading">
  <div class="shell">
    <div class="section__head">
      <div><p class="mono section__label">Browse rental categories</p><h2 id="rental-browse-heading" class="section__title section__title--sm">What do you need?</h2></div>
      <a class="text-link" href="<?= e_attr(url('start/ventures')) ?>">Request the current equipment list <span aria-hidden="true">↗</span></a>
    </div>
    <div class="rental-grid">
      <?php foreach ($categories as $category): ?>
        <article class="rental-category" id="rental-<?= e_attr($category['id']) ?>">
          <?= $this->partial('partials.frame', ['slot' => $category['slot'], 'ratio' => '4x3']) ?>
          <div class="rental-category__body">
            <p class="mono">Rental category</p>
            <h3><?= e($category['name']) ?></h3>
            <a class="text-link" href="<?= e_attr(url('start/ventures')) ?>">Inquire about <?= e($category['name']) ?> <span aria-hidden="true">↗</span></a>
          </div>
        </article>
      <?php endforeach ?>
    </div>
    <p class="image-note">Photographs illustrate our production work and rental services. Specific items and availability are confirmed on inquiry.</p>
    <?php if ($items !== []): ?>
      <div class="rental-inventory">
        <h2 class="section__title section__title--sm">Equipment list</h2>
        <div class="rental-grid">
          <?php foreach ($items as $item): ?>
            <article class="rental-category">
              <?= $this->partial('partials.frame', ['slot' => $item['slot']]) ?>
              <div class="rental-category__body">
                <h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p>
                <a class="text-link" href="<?= e_attr(url('start/ventures')) ?>">Ask about this item <span aria-hidden="true">↗</span></a>
              </div>
            </article>
          <?php endforeach ?>
        </div>
      </div>
    <?php else: ?>
      <div class="inventory-note">
        <div><p class="mono section__label">Current equipment list</p><h2>Tell us what you need.</h2><p>Contact us for the current equipment list, availability and pricing.</p></div>
        <a class="btn" href="<?= e_attr(url('start/ventures')) ?>">Request the equipment list</a>
      </div>
    <?php endif ?>
  </div>
</section>
<section class="section rental-assistance" data-theme="dark">
  <div class="shell editorial-split">
    <div><p class="mono section__label">Spaces & operations</p><h2 class="section__title">Beyond equipment.</h2></div>
    <div><p class="section__lede"><?= e(config('app.ventures_section.subhead')) ?></p><a class="text-link" href="<?= e_attr(url('services') . '#ventures') ?>">Explore studio, stage and event services <span aria-hidden="true">↗</span></a></div>
  </div>
</section>
<?php $this->end() ?>