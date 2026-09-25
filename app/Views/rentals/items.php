<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Equipment — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/items')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Browse production equipment available through 3AM Rentals.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$itemsList = is_array($items ?? null) ? $items : [];
$categoriesList = is_array($categories ?? null) ? $categories : [];
$notice = $_SESSION['rentals_notice'] ?? null;
unset($_SESSION['rentals_notice']);
?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">Rental inventory</p><h1>Equipment for the work.</h1></div>
    <p>Explore the tools behind 3AM productions. Open an item to choose dates and quantity before adding it to Cart.</p>
  </div>
</section>
<section class="rentals-section rentals-section--catalogue">
  <div class="rentals-shell">
    <?php if (is_string($notice)): ?><p class="rentals-support-panel" role="alert"><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-toolbar" aria-label="Equipment catalogue controls">
      <label class="rentals-toolbar__search"><span class="sr-only">Search equipment</span><input type="search" placeholder="Search equipment…" data-rentals-search></label>
      <div class="rentals-toolbar__filters" aria-label="Filter by category">
        <button class="rentals-filter is-active" type="button" data-rentals-filter="all" aria-pressed="true">All</button>
        <?php foreach ($categoriesList as $category): ?>
          <?php if (($category['id'] ?? '') !== '' && ($category['name'] ?? '') !== ''): ?>
            <button class="rentals-filter" type="button" data-rentals-filter="<?= e_attr((string) $category['id']) ?>" aria-pressed="false"><?= e((string) $category['name']) ?></button>
          <?php endif ?>
        <?php endforeach ?>
      </div>
    </div>
    <?php if ($itemsList !== []): ?>
      <p class="rentals-results" data-rentals-results role="status" aria-live="polite"></p>
      <div class="rentals-catalog-grid">
        <?php foreach ($itemsList as $item): ?>
          <?php
          $itemId = (string) ($item['id'] ?? '');
          $name = (string) ($item['name'] ?? 'Rental item');
          $category = (string) ($item['category'] ?? 'production');
          $categoryName = (string) ($item['category_name'] ?? ucwords(str_replace(['-', '_'], ' ', $category)));
          $description = trim((string) ($item['description'] ?? ''));
          $ideal = trim((string) ($item['ideal_for'] ?? ''));
          $path = (string) ($item['image_path'] ?? '');
          $image = \App\Models\RentalCatalog::imagePath($path);
          $sample = ($item['is_sample'] ?? false) === true;
          $available = (int) ($item['available_quantity'] ?? 0);
          $status = trim((string) ($item['availability_status'] ?? ''));
          $rate = (float) ($item['rental_rate'] ?? 0);
          $unit = trim((string) ($item['rental_unit'] ?? ''));
          $deposit = (float) ($item['security_deposit'] ?? 0);
          $canRent = !$sample && $available > 0 && !in_array(strtolower($status), ['unavailable', 'out_of_stock', 'inactive', 'reserved'], true);
          $detail = [
              'id' => $itemId, 'name' => $name, 'category' => $categoryName,
              'description' => $description, 'ideal' => $ideal,
              'image' => $image !== null ? site_media($image) : '',
              'status' => $sample ? 'Preview only — inventory not confirmed' : ($status !== '' ? $status : 'Ask for availability'),
              'available' => $available, 'rate' => $rate > 0 ? '₱' . number_format($rate, 2) . ($unit !== '' ? ' / ' . $unit : '') : 'Rate on request',
              'deposit' => $deposit > 0 ? '₱' . number_format($deposit, 2) : 'None listed',
              'canRent' => $canRent, 'isSample' => $sample,
          ];
          ?>
          <article id="rental-item-<?= e_attr($itemId) ?>" class="rentals-item-card" data-rentals-item data-category="<?= e_attr($category) ?>">
            <div class="rentals-item-card__image">
              <?= $this->partial('rentals.partials.image', ['image_path' => $path, 'name' => $name]) ?>
              <?php if ($sample): ?><span class="rentals-item-card__preview">Preview item</span><?php endif ?>
            </div>
            <div class="rentals-item-card__body">
              <span class="rentals-card__meta"><?= e($categoryName) ?></span>
              <h3><?= e($name) ?></h3>
              <?php if ($description !== ''): ?><p class="rentals-item-card__description"><?= e($description) ?></p><?php endif ?>
              <div class="rentals-item-card__meta-row"><span><?= e($sample ? 'Preview only' : ($available > 0 ? 'Available to request' : 'Currently unavailable')) ?></span><strong><?= e($rate > 0 ? '₱' . number_format($rate, 2) . ($unit !== '' ? ' / ' . $unit : '') : 'Rate on request') ?></strong></div>
              <button class="rentals-btn rentals-btn--dark rentals-item-card__detail" type="button" data-rentals-detail='<?= e_attr(json_encode($detail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>'>View details <span aria-hidden="true">↗</span></button>
            </div>
          </article>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="rentals-support-panel"><p class="rentals-card__meta">Rental inventory</p><h3>Equipment information is being updated.</h3><p>Contact the 3AM team for current availability.</p><a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/support')) ?>">Contact Rental Support</a></div>
    <?php endif ?>
  </div>
</section>
<dialog class="rentals-detail" data-rentals-dialog aria-labelledby="rentals-detail-title">
  <button class="rentals-detail__close" type="button" data-rentals-close aria-label="Close equipment details">×</button>
  <div class="rentals-detail__success" data-detail-success hidden><p class="rentals-card__meta">Rental cart</p><h2>Added to cart</h2><p>Your equipment is saved for the selected dates.</p><button class="rentals-btn rentals-btn--primary" type="button" data-detail-continue>Continue browsing</button></div>
  <div class="rentals-detail__grid" data-detail-content>
    <div class="rentals-detail__visual"><img data-detail-image alt="" hidden></div>
    <div class="rentals-detail__body">
      <p class="rentals-card__meta" data-detail-category></p>
      <h2 id="rentals-detail-title" data-detail-name></h2>
      <p data-detail-description></p>
      <dl class="rentals-detail__facts">
        <div><dt>Ideal use</dt><dd data-detail-ideal></dd></div>
        <div><dt>Listed rate</dt><dd data-detail-rate></dd></div>
        <div><dt>Security deposit</dt><dd data-detail-deposit></dd></div>
        <div><dt>Availability</dt><dd data-detail-status></dd></div>
      </dl>
      <form method="post" action="<?= e_attr(url('rentals/cart/add')) ?>" data-detail-form data-availability-url="<?= e_attr(url('rentals/availability')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" data-detail-id>
        <div class="rentals-detail__dates">
          <input type="hidden" name="rental_start_date"><input type="hidden" name="rental_end_date">
          <div class="rentals-detail__date-choice"><span>Rental dates</span><button type="button" data-date-trigger aria-expanded="false" aria-controls="rental-date-picker">Select rental dates</button></div>
          <label>Quantity<input type="number" name="quantity" min="1" max="999" value="1" required></label>
        </div>
        <div class="rentals-availability" id="rental-date-picker" data-availability-calendar aria-label="Choose rental dates" hidden>
          <div class="rentals-availability__head"><button type="button" data-month-prev aria-label="Previous month">←</button><strong data-month-label></strong><button type="button" data-month-next aria-label="Next month">→</button></div>
          <div class="rentals-availability__weekdays" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
          <div class="rentals-availability__days" data-calendar-days></div>
          <button class="rentals-availability__clear" type="button" data-date-clear>Clear dates</button>
          <p class="rentals-availability__legend"><span>● Available</span><span>● Reserved / unavailable</span></p>
        </div>
        <button class="rentals-btn rentals-btn--primary" type="submit" data-detail-add>Add to Cart</button>
      </form>
      <p class="rentals-detail__message" data-detail-message role="status" aria-live="polite">Select available rental dates.</p>
    </div>
  </div>
</dialog>
<?php $this->end() ?>
