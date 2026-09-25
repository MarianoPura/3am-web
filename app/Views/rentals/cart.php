<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Cart — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/cart')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Set quantities and rental dates for your selected 3AM equipment.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$items = is_array($items ?? null) ? $items : [];
$summary = is_array($summary ?? null) ? $summary : ['items' => [], 'subtotal' => 0, 'security_deposit' => 0, 'total' => 0];
$ready = (bool) ($ready ?? false);
$notice = $_SESSION['rentals_notice'] ?? null;
unset($_SESSION['rentals_notice']);
$lineSummaries = [];
foreach ($summary['items'] as $line) { $lineSummaries[(string) $line['line_id']] = $line; }
?>
<section class="rentals-page-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Your selection</p><h1>Rental Cart.</h1></div><p>Review the dates and quantities you selected for each piece of equipment before checkout.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell">
  <?php if (is_string($notice)): ?><p class="rentals-support-panel" role="alert"><?= e($notice) ?></p><?php endif ?>
  <?php if ($items === []): ?>
    <div class="rentals-support-panel rentals-cart-empty"><p class="rentals-card__meta">No items yet</p><h2>Start with the right equipment.</h2><p>Browse the catalogue and open an item to add it to your cart.</p><a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Explore Equipment</a></div>
  <?php else: ?>
    <div class="rentals-cart-layout">
      <div class="rentals-cart-lines">
        <?php foreach ($items as $item): ?>
          <?php
          $itemId = (string) ($item['item_id'] ?? '');
          $lineId = (string) ($item['line_id'] ?? $itemId);
          $name = (string) ($item['name'] ?? 'Rental item');
          $quantity = (int) ($item['quantity'] ?? 1);
          $start = (string) ($item['rental_start_date'] ?? '');
          $end = (string) ($item['rental_end_date'] ?? '');
          $line = $lineSummaries[$lineId] ?? null;
          $hasDates = $start !== '' && $end !== '';
          ?>
          <article class="rentals-cart-item">
            <div class="rentals-cart-item__image"><?= $this->partial('rentals.partials.image', ['image_path' => $item['image_path'] ?? null, 'name' => $name]) ?></div>
            <div class="rentals-cart-item__main">
              <div class="rentals-cart-item__heading"><div><p class="rentals-card__meta"><?= e((string) ($item['category'] ?? 'Equipment')) ?></p><h2><?= e($name) ?></h2></div><span class="rentals-cart-item__state"><?= ($item['is_unavailable'] ?? false) ? 'Unavailable' : (!$hasDates ? 'Dates needed' : (($item['can_checkout'] ?? false) ? 'Ready to review' : 'Check availability')) ?></span></div>
              <p class="rentals-cart-item__rate">Listed rate: ₱<?= e(number_format((float) ($item['rental_rate'] ?? 0), 2)) ?><?= ($item['rental_unit'] ?? '') !== '' ? ' / ' . e((string) $item['rental_unit']) : '' ?></p>
              <form method="post" action="<?= e_attr(url('rentals/cart/update')) ?>" class="rentals-cart-form rentals-cart-form--update">
                <?= csrf_field() ?><input type="hidden" name="line" value="<?= e_attr($lineId) ?>"><input type="hidden" name="id" value="<?= e_attr($itemId) ?>">
                <label class="rentals-date-field"><span>Quantity</span><input type="number" name="quantity" min="1" max="999" value="<?= e_attr((string) $quantity) ?>" required></label>
                <label class="rentals-date-field"><span>Start date</span><input type="date" name="rental_start_date" min="<?= e_attr(date('Y-m-d')) ?>" value="<?= e_attr($start) ?>" required></label>
                <label class="rentals-date-field"><span>End date</span><input type="date" name="rental_end_date" min="<?= e_attr(date('Y-m-d')) ?>" value="<?= e_attr($end) ?>" required></label>
                <noscript><button type="submit" class="rentals-btn rentals-btn--dark">Save changes</button></noscript>
              </form>
              <div class="rentals-cart-item__bottom"><span><?php if ($hasDates && $line !== null && ($item['can_checkout'] ?? false)): ?>Rental estimate: ₱<?= e(number_format((float) $line['line_total'], 2)) ?> · Deposit: ₱<?= e(number_format((float) $line['security_deposit'], 2)) ?><?php elseif ($item['is_unavailable'] ?? false): ?>This item is no longer available. Remove it to continue.<?php elseif ($hasDates): ?>Dates or quantity are unavailable. Choose another period or remove this item.<?php else: ?>Estimate appears after you set dates.<?php endif ?></span><form method="post" action="<?= e_attr(url('rentals/cart/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="line" value="<?= e_attr($lineId) ?>"><button type="submit" class="rentals-text-link">Remove</button></form></div>
            </div>
          </article>
        <?php endforeach ?>
      </div>
      <aside class="rentals-cart-summary">
        <p class="rentals-card__meta">Request summary</p><h2><?= e((string) ($count ?? 0)) ?> item<?= (int) ($count ?? 0) === 1 ? '' : 's' ?></h2>
        <?php if ($ready): ?>
          <dl><div><dt>Rental subtotal</dt><dd>₱<?= e(number_format((float) $summary['subtotal'], 2)) ?></dd></div><div><dt>Security deposits</dt><dd>₱<?= e(number_format((float) $summary['security_deposit'], 2)) ?></dd></div><div class="rentals-cart-summary__total"><dt>Estimated total</dt><dd>₱<?= e(number_format((float) $summary['total'], 2)) ?></dd></div></dl>
          <p>Availability and final terms are confirmed by the Rentals team.</p>
          <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/checkout')) ?>">Continue to checkout</a>
        <?php else: ?>
          <p>Choose valid dates and quantities for every item. Your changes save automatically.</p>
          <span class="rentals-btn rentals-btn--preview" aria-disabled="true">Complete cart details first</span>
        <?php endif ?>
        <a class="rentals-text-link" href="<?= e_attr(url('rentals/items')) ?>">Browse more equipment →</a>
        <form method="post" action="<?= e_attr(url('rentals/cart/clear')) ?>"><?= csrf_field() ?><button class="rentals-text-link" type="submit">Clear cart</button></form>
      </aside>
    </div>
  <?php endif ?>
</div></section>
<?php $this->end() ?>
