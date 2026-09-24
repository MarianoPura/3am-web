<?php $this->extend('rentals.layouts.base'); ?>

<?php $this->start('title') ?>Rental Services — 3AM<?php $this->end() ?>

<?php $this->start('canonical') ?>
<?= e_attr(absolute_url('rentals/services')) ?>
<?php $this->end() ?>

<?php $this->start('description') ?>
Technical and event support services for rentals, production coordination and live operations.
<?php $this->end() ?>

<?php $this->start('content') ?>

<?php
$servicesList = is_array($services ?? null)
    ? (array) $services
    : [];
?>

<section class="rentals-page-hero rentals-page-hero--media">
  <div class="rentals-shell rentals-page-hero__inner">
    <div>
      <p class="rentals-kicker">Rental services</p>
      <h1>Support behind the equipment.</h1>
    </div>

    <p>
      Planning, setup and live technical coordination for events,
      media work and studio production.
    </p>
  </div>
</section>

<section class="rentals-section rentals-section--services">
  <div class="rentals-shell">

    <div class="rentals-section__header">
      <p class="rentals-kicker">Services</p>
      <h2>Technical support that keeps production moving.</h2>

      <p>
        Explore available production and technical services provided
        alongside 3AM rental equipment.
      </p>
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
          Practical support from preparation through live operation.
        </p>
      </div>

      <div class="rentals-services-grid">

        <?php if ($servicesList !== []): ?>

          <?php foreach ($servicesList as $service): ?>

            <?php
            $serviceId = (string) ($service['id'] ?? '');
            $serviceName = (string) ($service['name'] ?? 'Rental service');

            $description = trim(
                (string) ($service['description'] ?? '')
            );

            $idealFor = trim(
                (string) ($service['ideal_for'] ?? '')
            );

            $availability = trim(
                (string) ($service['availability_status'] ?? '')
            );

            $rentalUnit = trim(
                (string) ($service['rental_unit'] ?? '')
            );

            $rate = (float) ($service['rental_rate'] ?? 0);
            ?>

            <article class="rentals-service">

              <p class="rentals-card__meta">
                Service
              </p>

              <h3>
                <?= e($serviceName) ?>
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
                  <?php if ($rate > 0): ?>
                    ₱<?= e(number_format($rate, 2)) ?>
                    <?= $rentalUnit !== ''
                        ? ' / ' . e($rentalUnit)
                        : '' ?>
                  <?php else: ?>
                    Rate on request
                  <?php endif ?>
                </span>

              </div>

              <a
                class="rentals-item-card__link"
                href="<?= e_attr(
                    url(
                        '/start?type=Rentals&service=' .
                        rawurlencode($serviceId)
                    )
                ) ?>"
              >
                Request service →
              </a>

            </article>

          <?php endforeach ?>

        <?php else: ?>

          <div class="rentals-support-panel">
            <p class="rentals-card__meta">Services</p>

            <h3>Service information is being updated.</h3>

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
        href="<?= e_attr(url('rentals/support')) ?>"
      >
        Discuss support needs
      </a>
    </div>

  </div>
</section>

<?php $this->end() ?>