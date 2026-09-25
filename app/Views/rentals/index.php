<?php $this->extend('rentals.layouts.base'); ?>

<?php $this->start('title') ?>
3AM Rentals — Production Equipment & Support
<?php $this->end() ?>

<?php $this->start('canonical') ?>
<?= e_attr(absolute_url('rentals')) ?>
<?php $this->end() ?>

<?php $this->start('description') ?>
Browse production equipment and technical support resources for events, studio production, livestreaming and digital media work.
<?php $this->end() ?>

<?php $this->start('content') ?>
<div class="rentals-home">

<?php
$categoriesList = is_array($categories ?? null)
    ? (array) $categories
    : [];

$itemsList = is_array($items ?? null)
    ? (array) $items
    : [];

$servicesList = is_array($services ?? null)
    ? (array) $services
    : [];

$featuredItems = array_slice($itemsList, 0, 3);
$featuredServices = array_slice($servicesList, 0, 4);
?>

<section class="rentals-hero">
  <div class="rentals-shell rentals-hero__grid">

    <div>
      <p class="rentals-kicker">3AM Rentals</p>

      <h1>
        Equipment for your next production.
      </h1>

      <p>
        Browse reliable production equipment and rental resources for
        events, content production, livestreaming, studio work and
        technical operations.
      </p>

      <div class="rentals-hero__actions">

        <a
          class="rentals-btn rentals-btn--primary"
          href="<?= e_attr(url('rentals/items')) ?>"
        >
          Browse Rental Items
        </a>

        <a
          class="rentals-btn rentals-btn--secondary"
          href="<?= e_attr(url('rentals/how-to-rent')) ?>"
        >
          How to Rent
        </a>

      </div>

      <ul class="rentals-badges" aria-label="Rental use cases">
        <li>Events</li>
        <li>Media</li>
        <li>Livestream</li>
        <li>Studio</li>
      </ul>
    </div>

    <div class="rentals-hero__media">

      <?= $this->partial(
          'partials.frame',
          [
              'slot' => 'rentals.hero',
              'ratio' => '16x9'
          ]
      ) ?>

      <div class="rentals-hero__media-note">
        <span>Production ready</span>
        <strong>Camera · Lighting · Support</strong>
      </div>

    </div>

  </div>
</section>


<section class="rentals-section rentals-section--dark">
  <div class="rentals-shell">

    <div class="rentals-section__header rentals-section__header--split">

      <div>
        <p class="rentals-kicker">Browse by category</p>

        <h2>
          Find the right equipment for your production requirements.
        </h2>

        <p>
          Explore available equipment grouped around practical
          production needs.
        </p>
      </div>

      <a
        class="rentals-text-link"
        href="<?= e_attr(url('rentals/categories')) ?>"
      >
        All categories <span aria-hidden="true">↗</span>
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
                <?= e((string) $itemCount) ?>
                <?= $itemCount === 1 ? 'item' : 'items' ?>
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

        <p class="rentals-card__meta">Categories</p>

        <h3>Rental categories are being updated.</h3>

        <p>
          Contact the 3AM team for current equipment availability.
        </p>

      </div>

    <?php endif ?>

  </div>
</section>


<section class="rentals-section rentals-section--tight rentals-section--light">
  <div class="rentals-shell">

    <div class="rentals-section__header rentals-section__header--split">

      <div>
        <p class="rentals-kicker">Featured rentals</p>
        <h2>Available equipment and resources.</h2>
      </div>

      <a
        class="rentals-text-link"
        href="<?= e_attr(url('rentals/items')) ?>"
      >
        Browse inventory <span aria-hidden="true">↗</span>
      </a>

    </div>

    <?php if ($featuredItems !== []): ?>

      <div class="rentals-catalog-grid">

        <?php foreach ($featuredItems as $item): ?>

          <?php
          $itemId = (string) ($item['id'] ?? '');
          $itemName = (string) ($item['name'] ?? 'Rental item');
          $isSample = ($item['is_sample'] ?? false) === true;
          $itemCategory = (string) ($item['category'] ?? 'production');

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
          ?>

          <article
            class="rentals-item-card"
            data-rentals-item
            data-category="<?= e_attr($itemCategory) ?>"
          >

            <div class="rentals-item-card__image">

              <?= $this->partial('rentals.partials.image', ['image_path' => $imagePath, 'name' => $itemName]) ?>
              <?php if ($isSample): ?><span class="rentals-item-card__preview">Preview item</span><?php endif ?>

            </div>

            <div class="rentals-item-card__body">

              <span class="rentals-card__meta">
                <?= e(
                    ucwords(
                        str_replace(
                            ['-', '_'],
                            ' ',
                            $itemCategory
                        )
                    )
                ) ?>
              </span>

              <h3>
                <?= e($itemName) ?>
              </h3>

              <?php if (!$isSample && $description !== ''): ?>
                <p class="rentals-item-card__description">
                  <?= e($description) ?>
                </p>
              <?php endif ?>

              <?php if (!$isSample && $idealFor !== ''): ?>

                <p class="rentals-item-card__ideal">
                  <strong>Ideal for</strong>
                  <?= e($idealFor) ?>
                </p>

              <?php endif ?>

              <?php if ($isSample): ?>
                <p class="rentals-item-card__description">Inventory and pricing await confirmation.</p>
              <?php else: ?><div class="rentals-item-card__meta-row">

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

              </div><?php endif ?>

              <?php if (!$isSample): ?><a
                class="rentals-item-card__link"
                href="<?= e_attr(url('rentals/items?category=' . rawurlencode($itemCategory) . '#rental-item-' . rawurlencode($itemId))) ?>"
              >
                View equipment details →
              </a><?php endif ?>

            </div>

          </article>

        <?php endforeach ?>

      </div>

    <?php else: ?>

      <div class="rentals-support-panel">
        <p class="rentals-card__meta">Rental inventory</p>
        <h3>Equipment information is being updated.</h3>
        <p>Contact 3AM for current rental availability.</p>
      </div>

    <?php endif ?>

  </div>
</section>


<section class="rentals-section rentals-section--tight rentals-section--dark rentals-section--services">
  <div class="rentals-shell">

    <div class="rentals-section__header">
      <p class="rentals-kicker">Services</p>

      <h2>
        Support that keeps your production on track.
      </h2>
    </div>

    <div class="rentals-services-layout">

      <div class="rentals-services-visual">

        <?= $this->partial(
            'partials.frame',
            [
                'slot' => 'track.broadcast',
                'ratio' => '4x3'
            ]
        ) ?>

        <p>
          Equipment works best with the right production
          support around it.
        </p>

      </div>

      <div class="rentals-services-grid">

        <?php if ($featuredServices !== []): ?>

          <?php foreach ($featuredServices as $service): ?>

            <?php
            $serviceName = (string) (
                $service['name'] ?? 'Production service'
            );

            $serviceDescription = trim(
                (string) ($service['description'] ?? '')
            );

            $serviceRate = (float) (
                $service['rental_rate'] ?? 0
            );

            $serviceUnit = trim(
                (string) ($service['rental_unit'] ?? '')
            );
            ?>

            <article class="rentals-service">

              <p class="rentals-card__meta">
                Service
              </p>

              <h3>
                <?= e($serviceName) ?>
              </h3>

              <?php if ($serviceDescription !== ''): ?>
                <p>
                  <?= e($serviceDescription) ?>
                </p>
              <?php endif ?>

              <?php if ($serviceRate > 0): ?>

                <p class="rentals-item-card__ideal">
                  <strong>Rate</strong>

                  ₱<?= e(number_format($serviceRate, 2)) ?>

                  <?php if ($serviceUnit !== ''): ?>
                    / <?= e($serviceUnit) ?>
                  <?php endif ?>
                </p>

              <?php endif ?>

            </article>

          <?php endforeach ?>

        <?php else: ?>

          <div class="rentals-support-panel">

            <p class="rentals-card__meta">
              Services
            </p>

            <h3>
              Service information is being updated.
            </h3>

            <p>
              Contact the 3AM team to discuss production
              and technical support requirements.
            </p>

          </div>

        <?php endif ?>

      </div>

    </div>

    <div class="rentals-inline-actions">

      <a
        class="rentals-btn rentals-btn--dark"
        href="<?= e_attr(url('rentals/services')) ?>"
      >
        View Services
      </a>

    </div>

  </div>
</section>


<section class="rentals-section rentals-section--tight rentals-section--light">
  <div class="rentals-shell">

    <div class="rentals-section__header">

      <p class="rentals-kicker">
        How renting works
      </p>

      <h2>
        Simple and practical from first browse to final coordination.
      </h2>

    </div>

    <div class="rentals-steps-grid">

      <?php foreach ([
          [
              'Browse',
              'Search equipment and choose the item that fits your production.'
          ],
          [
              'Add to Cart',
              'Choose quantity and dates when adding equipment, then review your Cart.'
          ],
          [
              'Checkout',
              'Sign in or create an account, choose a payment method and upload payment proof.'
          ],
          [
              'Pending Review',
              'Submit your request and track payment review under My Rentals.'
          ],
      ] as $stepIndex => [$title, $body]): ?>

        <article class="rentals-step">

          <p class="rentals-step__number">
            <?= e(
                str_pad(
                    (string) ($stepIndex + 1),
                    2,
                    '0',
                    STR_PAD_LEFT
                )
            ) ?>
          </p>

          <h3><?= e($title) ?></h3>

          <p><?= e($body) ?></p>

        </article>

      <?php endforeach ?>

    </div>

  </div>
</section>


<section class="rentals-section rentals-section--tight rentals-section--dark">
  <div class="rentals-shell">

    <div class="rentals-section__header">

      <p class="rentals-kicker">
        Use cases
      </p>

      <h2>
        Built around real production needs.
      </h2>

    </div>

    <div class="rentals-use-cases">

      <?php foreach ([
          ['Production', 'media.work6'],
          ['Events', 'track.events'],
          ['Livestreaming', 'systems.control_room'],
          ['Creative Work', 'rentals.camera.vlogging'],
      ] as [$useCase, $useCaseSlot]): ?>

        <article class="rentals-use-case">

          <?= $this->partial(
              'partials.frame',
              [
                  'slot' => $useCaseSlot,
                  'ratio' => '4x3'
              ]
          ) ?>

          <div>

            <p class="rentals-card__meta">
              Use case
            </p>

            <h3><?= e($useCase) ?></h3>

            <p>
              Equipment and support shaped for practical
              production needs, event coordination and
              technical delivery.
            </p>

          </div>

        </article>

      <?php endforeach ?>

    </div>

  </div>
</section>


<section class="rentals-section rentals-section--tight rentals-section--light">
  <div class="rentals-shell">

    <div class="rentals-cta-band rentals-cta-band--split">

      <div>

        <p class="rentals-kicker">
          Support
        </p>

        <h2>
          Need help planning your rental?
        </h2>

        <p>
          Talk to the 3AM team about your equipment
          and production requirements.
        </p>

      </div>

      <div class="rentals-inline-actions">

        <a
          class="rentals-btn rentals-btn--primary"
          href="<?= e_attr(url('rentals/support')) ?>"
        >
          Contact Rental Support
        </a>

      </div>

    </div>

  </div>
</section>

</div>
<?php $this->end() ?>
