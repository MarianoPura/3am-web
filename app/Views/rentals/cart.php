<?php $this->extend('rentals.layouts.base'); ?>

<?php $this->start('title') ?>Your Rental Cart — 3AM<?php $this->end() ?>

<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/cart')) ?><?php $this->end() ?>

<?php $this->start('description') ?>Review and manage the items you have added to your 3AM rental cart.<?php $this->end() ?>

<?php $this->start('content') ?>

<?php
$items = is_array($items ?? null) ? $items : [];
$summary = is_array($summary ?? null) ? $summary : ['subtotal' => 0.0, 'deposit' => 0.0, 'total' => 0.0];
$count = (int) ($count ?? 0);
$empty = (bool) ($empty ?? true);
?>

<section class="rentals-section rentals-section--tight">
  <div class="rentals-shell">

    <div class="rentals-support-panel" style="margin-bottom: 1.5rem;">
      <p class="rentals-card__meta">Rental cart</p>
      <h2 style="margin-bottom:0.25rem;">Your selected equipment</h2>
      <p>
        <?= $empty ? 'Your cart is empty.' : 'You have ' . e((string) $count) . ' item(s) in the cart.' ?>
      </p>
    </div>

    <?php if ($empty): ?>

      <div class="rentals-support-panel">
        <h3>No rental items selected yet.</h3>
        <p>Browse the equipment catalogue and add items to prepare a rental request.</p>

        <div class="rentals-inline-actions">
          <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/items')) ?>">Browse equipment</a>
          <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/support')) ?>">Contact rental support</a>
        </div>
      </div>

    <?php else: ?>

      <div class="rentals-cart-layout" style="display:grid; grid-template-columns: 1.5fr 0.7fr; gap: 1.5rem; align-items:start;">

        <div class="rentals-support-panel">
          <?php foreach ($items as $item): ?>
            <?php
            $itemId = (string) ($item['item_id'] ?? '');
            $name = (string) ($item['name'] ?? 'Rental item');
            $quantity = (int) ($item['quantity'] ?? 1);
            $rate = (float) ($item['rental_rate'] ?? 0);
            $unit = trim((string) ($item['rental_unit'] ?? ''));
            $dates = trim((string) (($item['rental_start_date'] ?? '') ?: ($item['rental_end_date'] ?? '')));
            ?>
            <div class="rentals-cart-item" style="display:flex; gap: 1rem; padding:1rem 0; border-bottom:1px solid var(--rentals-border);">
              <div class="rentals-item-card__image" style="width:140px; flex-shrink:0;">
                <?php if (!empty($item['image_path'])): ?>
                  <img src="<?= e_attr(url(ltrim((string) $item['image_path'], '/'))) ?>" alt="<?= e_attr($name) ?>" loading="lazy" style="height:90px; object-fit:cover; border-radius:16px;">
                <?php else: ?>
                  <?= $this->partial('partials.frame', ['slot' => 'venture.rentals', 'ratio' => '4x3']) ?>
                <?php endif ?>
              </div>

              <div style="flex:1; min-width:0;">
                <p class="rentals-card__meta"><?= e($item['category'] ?? 'Production') ?></p>
                <h3 style="margin:0.3rem 0;"><?= e($name) ?></h3>
                <?php if ($dates !== ''): ?>
                  <p style="margin:0.15rem 0;">Rental window: <?= e($dates) ?></p>
                <?php endif ?>
                <p style="margin:0.4rem 0 0;">
                  Quantity: <strong><?= e((string) $quantity) ?></strong>
                  <?php if ($rate > 0): ?>
                    &nbsp;•&nbsp; <?= e(number_format($rate, 2)) ?><?= $unit !== '' ? ' / ' . e($unit) : '' ?>
                  <?php endif ?>
                </p>
              </div>

              <form method="post" action="<?= e_attr(url('rentals/cart/remove')) ?>" style="align-self:center;">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e_attr($itemId) ?>">
                <button type="submit" class="rentals-btn rentals-btn--dark" style="min-height:40px; padding:0.7rem 1rem;">Remove</button>
              </form>
            </div>
          <?php endforeach ?>
        </div>

        <aside class="rentals-support-panel">
          <p class="rentals-card__meta">Summary</p>
          <div style="display:grid; gap:0.75rem; margin-top:1rem;">
            <div style="display:flex; justify-content:space-between; gap:1rem;">
              <span>Subtotal</span>
              <strong>₱<?= e(number_format((float) ($summary['subtotal'] ?? 0), 2)) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; gap:1rem;">
              <span>Deposit</span>
              <strong>₱<?= e(number_format((float) ($summary['deposit'] ?? 0), 2)) ?></strong>
            </div>
            <hr style="border-color:var(--rentals-border);">
            <div style="display:flex; justify-content:space-between; gap:1rem; font-weight:700;">
              <span>Total</span>
              <span>₱<?= e(number_format((float) ($summary['total'] ?? 0), 2)) ?></span>
            </div>
          </div>

          <div class="rentals-inline-actions" style="display:grid; gap:0.7rem; margin-top:1.25rem;">
            <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Continue browsing</a>
            <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/support')) ?>">Request final quote</a>
          </div>

          <form method="post" action="<?= e_attr(url('rentals/cart/clear')) ?>" style="margin-top: 1rem;">
            <?= csrf_field() ?>
            <button type="submit" class="rentals-btn" style="background: transparent; border-color: var(--rentals-border); color: var(--rentals-text); width:100%;">Clear cart</button>
          </form>
        </aside>

      </div>

    <?php endif ?>

  </div>
</section>

<?php $this->end() ?>