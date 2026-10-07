<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Request <?= e($service['name']) ?> — 3AM Rentals<?php $this->end() ?>
<?php $this->start('content') ?>
<?php $value = static fn (string $key): string => (string) ($fields[$key] ?? ''); ?>
<section class="rentals-page-hero rentals-service-request-hero"><div class="rentals-shell rentals-page-hero__inner"><div><p class="rentals-kicker">Service enquiry</p><h1>Plan your production.</h1></div><p>Tell us about your event. The team will review the scope, crew and equipment before confirming a quotation.</p></div></section>
<section class="rentals-section rentals-section--tight"><div class="rentals-shell rentals-service-request-layout">
  <aside class="rentals-support-panel rentals-service-request-summary">
    <p class="rentals-card__meta">Selected service</p><h2><?= e($service['name']) ?></h2>
    <?php if (trim((string) $service['description']) !== ''): ?><p><?= nl2br(e((string) $service['description'])) ?></p><?php endif ?>
    <?php if ((float) $service['rental_rate'] > 0): ?><p>Starting at <strong>₱<?= e(number_format((float) $service['rental_rate'], 2)) ?></strong> / <?= e((string) $service['rental_unit']) ?></p><?php else: ?><p>Quotation on request.</p><?php endif ?>
    <p>This is a service enquiry. Payment and final scope are arranged with the team.</p>
    <a href="<?= e_attr(url('rentals/services')) ?>">← Back to services</a>
  </aside>
  <div class="rentals-support-panel">
    <p class="rentals-card__meta">Event requirements</p><h2>Request this service</h2>
    <p>Submitting as <?= e($user['name']) ?> · <?= e($user['email']) ?></p>
    <?php if ($errors !== []): ?><div class="rentals-account__error" role="alert"><strong>Request not submitted.</strong> <?= e($errors['form'] ?? 'Please check the highlighted fields.') ?></div><?php endif ?>
    <form method="post" action="<?= e_attr(url('rentals/services/' . (int) $service['id'] . '/request')) ?>" class="rentals-service-request-form" data-service-request-form>
      <?= csrf_field() ?><input type="hidden" name="request_key" value="<?= e_attr($formKey) ?>">
      <div class="rentals-service-request-dates">
        <?php foreach (['start_date' => 'Event start', 'end_date' => 'Event end'] as $key => $label): ?>
          <label class="rentals-date-field" for="service-<?= e_attr($key) ?>"><?= e($label) ?>
            <input type="date" id="service-<?= e_attr($key) ?>" name="<?= e_attr($key) ?>" min="<?= e_attr(date('Y-m-d')) ?>" required value="<?= e_attr($value($key)) ?>"<?= isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="service-' . e_attr($key) . '-error"' : '' ?>>
            <?php if (isset($errors[$key])): ?><small id="service-<?= e_attr($key) ?>-error"><?= e($errors[$key]) ?></small><?php endif ?>
          </label>
        <?php endforeach ?>
      </div>
      <?php foreach (['phone' => ['Contact number', 40, 'tel'], 'location' => ['Event location', 255, 'text']] as $key => [$label, $max, $type]): ?>
        <label class="rentals-date-field" for="service-<?= e_attr($key) ?>"><?= e($label) ?>
          <input type="<?= e_attr($type) ?>" id="service-<?= e_attr($key) ?>" name="<?= e_attr($key) ?>" maxlength="<?= $max ?>" required value="<?= e_attr($value($key)) ?>"<?= $key === 'phone' ? ' autocomplete="tel"' : '' ?><?= isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="service-' . e_attr($key) . '-error"' : '' ?>>
          <?php if (isset($errors[$key])): ?><small id="service-<?= e_attr($key) ?>-error"><?= e($errors[$key]) ?></small><?php endif ?>
        </label>
      <?php endforeach ?>
      <label class="rentals-date-field" for="service-details">Coverage, crew and equipment needed
        <textarea id="service-details" name="details" maxlength="5000" rows="5" required placeholder="Describe the event, audience size, coverage hours, camera crew and technical support needed."<?= isset($errors['details']) ? ' aria-invalid="true" aria-describedby="service-details-error"' : '' ?>><?= e($value('details')) ?></textarea>
        <?php if (isset($errors['details'])): ?><small id="service-details-error"><?= e($errors['details']) ?></small><?php endif ?>
      </label>
      <button class="rentals-btn rentals-btn--primary" type="submit"><span class="rentals-submit-spinner" data-service-spinner hidden aria-hidden="true"></span><span data-service-submit-label>Submit service request</span></button>
      <p>Your request will appear under <a href="<?= e_attr(url('rentals/service-requests')) ?>">Service requests</a> as Pending.</p>
    </form>
  </div>
</div></section>
<?php $this->end() ?>
