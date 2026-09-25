<?php $this->extend('rentals.layouts.base'); ?>

<?php $this->start('title') ?>Rentals Categories — 3AM<?php $this->end() ?>

<?php $this->start('canonical') ?>
<?= e_attr(absolute_url('rentals/categories')) ?>
<?php $this->end() ?>

<?php $this->start('description') ?>
Browse 3AM rental categories for production equipment and technical resources.
<?php $this->end() ?>

<?php $this->start('content') ?>

<?php
$categoriesList = is_array($categories ?? null)
    ? (array) $categories
    : [];

$itemsList = is_array($items ?? null)
    ? (array) $items
    : [];
?>

<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">

    <div>
      <p class="rentals-kicker">Categories</p>
      <h1>Built for the way productions work.</h1>
    </div>

    <p>
      Browse equipment categories and find the resources
      that match your production requirements.
    </p>

  </div>
</section>

<section class="rentals-section">
  <div class="rentals-shell">

    <div class="rentals-section__header rentals-section__header--split">

      <div>
        <p class="rentals-kicker">Browse</p>
        <h2>Rental categories.</h2>

        <p>
          Explore available equipment grouped by production category.
        </p>
      </div>

      <a
        class="rentals-text-link"
        href="<?= e_attr(url('rentals/items')) ?>"
      >
        View all equipment
        <span aria-hidden="true">↗</span>
      </a>

    </div>

    <?php if ($categoriesList !== []): ?>

      <div class="rentals-grid">

        <?php foreach ($categoriesList as $categoryIndex => $category): ?>

          <?php
          $categoryId = (string) ($category['id'] ?? '');
          $categoryName = (string) ($category['name'] ?? 'Equipment');

          $description = trim(
              (string) ($category['description'] ?? '')
          );

          $imagePath = trim(
              (string) ($category['image_path'] ?? '')
          );

          $itemCount = 0;

          foreach ($itemsList as $item) {
              if (
                  (string) ($item['category'] ?? '') === $categoryId
              ) {
                  $itemCount++;
              }
          }
          ?>

          <article class="rentals-card">

            <div class="rentals-card__image">

              <?= $this->partial('rentals.partials.image', ['image_path' => $imagePath, 'name' => $categoryName]) ?>

            </div>

            <div class="rentals-card__body">

              <span class="rentals-card__index">
                <?= e(
                    str_pad(
                        (string) ($categoryIndex + 1),
                        2,
                        '0',
                        STR_PAD_LEFT
                    )
                ) ?>
              </span>

              <span class="rentals-card__meta">
                <?php if (($category['is_sample'] ?? false) === true): ?>
                  Preview image
                <?php else: ?>
                  <?= e((string) $itemCount) ?>
                  <?= $itemCount === 1 ? 'item' : 'items' ?>
                <?php endif ?>
              </span>

              <h3>
                <?= e($categoryName) ?>
              </h3>

              <p>
                <?= e(
                    $description !== ''
                        ? $description
                        : 'Production equipment and resources for this category.'
                ) ?>
              </p>

              <a
                class="rentals-card__link"
                href="<?= e_attr(url('rentals/items?category=' . rawurlencode($categoryId))) ?>"
              >
                View items →
              </a>

            </div>

          </article>

        <?php endforeach ?>

      </div>

    <?php else: ?>

      <div class="rentals-support-panel">

        <p class="rentals-card__meta">
          Categories
        </p>

        <h3>
          Rental categories are being updated.
        </h3>

        <p>
          Contact the 3AM team for current equipment
          and production rental availability.
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