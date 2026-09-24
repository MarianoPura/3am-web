<?php $this->extend('rentals.layouts.base'); ?>

<?php $this->start('title') ?>Rental Inventory — 3AM<?php $this->end() ?>

<?php $this->start('canonical') ?>
<?= e_attr(absolute_url('rentals/items')) ?>
<?php $this->end() ?>

<?php $this->start('description') ?>
Review available rental inventory, categories and production equipment support from 3AM.
<?php $this->end() ?>

<?php $this->start('content') ?>

<?php
$itemsList = is_array($items ?? null)
    ? (array) $items
    : [];

$categoriesList = is_array($categories ?? null)
    ? (array) $categories
    : [];
?>

<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">

    <div>
      <p class="rentals-kicker">Rental inventory</p>
      <h1>Browse equipment.</h1>
    </div>

    <p>
      Search by name or narrow the catalogue by category.
      Availability is confirmed with the 3AM team before a rental is finalized.
    </p>

  </div>
</section>

<section class="rentals-section rentals-section--catalogue">
  <div class="rentals-shell">

    <div class="rentals-toolbar" aria-label="Rental catalogue controls">

      <label class="rentals-toolbar__search">
        <span class="sr-only">Search rental items</span>

        <input
          type="search"
          placeholder="Search rental equipment…"
          data-rentals-search
        >
      </label>

      <div
        class="rentals-toolbar__filters"
        aria-label="Filter by category"
      >

        <button
          class="rentals-filter is-active"
          type="button"
          data-rentals-filter="all"
        >
          All
        </button>

        <?php foreach ($categoriesList as $category): ?>

          <?php
          $categoryId = (string) ($category['id'] ?? '');
          $categoryName = (string) ($category['name'] ?? '');

          if ($categoryId === '' || $categoryName === '') {
              continue;
          }
          ?>

          <button
            class="rentals-filter"
            type="button"
            data-rentals-filter="<?= e_attr($categoryId) ?>"
          >
            <?= e($categoryName) ?>
          </button>

        <?php endforeach ?>

      </div>
    </div>

    <?php if ($itemsList !== []): ?>

      <div class="rentals-catalog-grid">

        <?php foreach ($itemsList as $item): ?>

          <?php
          $itemId = (string) ($item['id'] ?? '');
          $itemName = (string) ($item['name'] ?? 'Rental item');

          $category = (string) ($item['category'] ?? 'production');

          $description = trim(
              (string) ($item['description'] ?? '')
          );

          $idealFor = trim(
              (string) ($item['ideal_for'] ?? '')
          );

          $imagePath = trim(
              (string) ($item['image_path'] ?? '')
          );

          $availability = trim(
              (string) ($item['availability_status'] ?? '')
          );

          $rentalUnit = trim(
              (string) ($item['rental_unit'] ?? '')
          );

          $rentalRate = (float) ($item['rental_rate'] ?? 0);

          $securityDeposit = (float) (
              $item['security_deposit'] ?? 0
          );

          $availableQuantity = (int) (
              $item['available_quantity'] ?? 0
          );

          $sku = trim(
              (string) ($item['sku'] ?? '')
          );
          ?>

          <article
            class="rentals-item-card"
            data-rentals-item
            data-category="<?= e_attr($category) ?>"
          >

            <div class="rentals-item-card__image">

              <?php if ($imagePath !== ''): ?>

                <img
                  src="<?= e_attr(url(ltrim($imagePath, '/'))) ?>"
                  alt="<?= e_attr($itemName) ?>"
                  loading="lazy"
                >

              <?php else: ?>

                <?= $this->partial(
                    'partials.frame',
                    [
                        'slot' => 'venture.rentals',
                        'ratio' => '4x3'
                    ]
                ) ?>

              <?php endif ?>

            </div>

            <div class="rentals-item-card__body">

              <span class="rentals-card__meta">
                <?= e(
                    ucwords(
                        str_replace(
                            ['-', '_'],
                            ' ',
                            $category
                        )
                    )
                ) ?>
              </span>

              <h3>
                <?= e($itemName) ?>
              </h3>

              <?php if ($description !== ''): ?>
                <p>
                  <?= e($description) ?>
                </p>
              <?php endif ?>

              <?php if ($idealFor !== ''): ?>

                <p class="rentals-item-card__ideal">
                  <strong>Ideal for</strong>
                  <?= e($idealFor) ?>
                </p>

              <?php endif ?>

              <div class="rentals-item-card__meta-row">

                <span>
                  <?= e(
                      $availability !== ''
                          ? $availability
                          : 'Inquire for availability'
                  ) ?>
                </span>

                <span>
                  <?php if ($rentalRate > 0): ?>

                    ₱<?= e(number_format($rentalRate, 2)) ?>

                    <?php if ($rentalUnit !== ''): ?>
                      / <?= e($rentalUnit) ?>
                    <?php endif ?>

                  <?php else: ?>

                    Rate on request

                  <?php endif ?>
                </span>

              </div>

              <div class="rentals-item-card__meta-row">

                <span>
                  Available:
                  <?= e((string) $availableQuantity) ?>
                </span>

                <?php if ($sku !== ''): ?>

                  <span>
                    SKU: <?= e($sku) ?>
                  </span>

                <?php endif ?>

              </div>

              <?php if ($securityDeposit > 0): ?>

                <p class="rentals-item-card__inclusions">
                  Security deposit:
                  ₱<?= e(number_format($securityDeposit, 2)) ?>
                </p>

              <?php endif ?>

              <div class="rentals-inline-actions" style="margin-top:1rem; gap:0.5rem; justify-content:flex-start;">
                <form method="post" action="<?= e_attr(url('rentals/cart/add')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e_attr($itemId) ?>">
                  <button type="submit" class="rentals-btn rentals-btn--primary" style="min-height:42px; padding:0.7rem 1rem;">
                    Add to cart
                  </button>
                </form>

                <a
                  class="rentals-btn rentals-btn--dark"
                  href="<?= e_attr(
                      url(
                          '/start?type=Rentals&rental=' .
                          rawurlencode($itemId)
                      )
                  ) ?>"
                  style="min-height:42px; padding:0.7rem 1rem;"
                >
                  Request this rental
                </a>
              </div>

            </div>
          </article>

        <?php endforeach ?>

      </div>

    <?php else: ?>

      <div class="rentals-support-panel">

        <p class="rentals-card__meta">
          Rental inventory
        </p>

        <h3>
          Equipment information is being updated.
        </h3>

        <p>
          Contact the 3AM team for current rental availability
          and production requirements.
        </p>

        <div class="rentals-inline-actions">

          <a
            class="rentals-btn rentals-btn--dark"
            href="<?= e_attr(url('rentals/support')) ?>"
          >
            Contact Rental Support
          </a>

        </div>

      </div>

    <?php endif ?>

  </div>
</section>

<?php $this->end() ?>