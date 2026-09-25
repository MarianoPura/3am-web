<?php
/**
 * Lead Form Partial
 *
 * High-converting, accessible quote form for event inquiries.
 * Supports both AJAX submission (with Meta Pixel lead tracking) and standard POST fallback.
 *
 * @var App\Core\View $this
 * @var array $form   Configuration for the quote form
 * @var array $copy   Copy configuration from config/landing.php
 * @var array $old    Previously submitted values
 * @var array $errors Validation error messages
 */

$val = static fn (string $k): string => (string) ($old[$k] ?? '');
$err = static fn (string $k): ?string => $errors[$k] ?? null;

$limits = (array) config('forms.limits', [
    'name'    => 120,
    'email'   => 254,
    'phone'   => 40,
    'company' => 160,
    'details' => 2000,
]);
$hp = (string) config('forms.honeypot_field', 'company_website');

// Service options
$services = $form['types'] ?? [
    'Event Production & Livestreaming',
    'Event Coverage (Video / Photo)',
    'LED / AV & Technical Production',
    'Equipment Rentals',
    'Other',
];

$heading     = $copy['heading']     ?? 'Get a Quote for Your Event';
$lede        = $copy['lede']        ?? 'Tell us the date, venue and what you need.';
$next        = $copy['next']        ?? 'We usually respond within 1 business day.';
$buttonText  = $copy['button']      ?? 'Submit My Inquiry';
$reassurance = $copy['reassurance'] ?? 'Your information is kept strictly confidential and only used to contact you regarding your quote.';
?>

<div class="lp-form-card" id="lead-form" data-theme="light" data-lp-form-card>

  <!-- 1. The Inquiry Form Container -->
  <div id="form-container" data-lp-form-body>
    <h2 class="lp-form-card__title" id="lead-form-heading"><?= e($heading) ?></h2>
    <p class="lp-form-card__lede"><?= e($lede) ?> <?= e($next) ?></p>

    <!-- General Error Banner -->
    <div class="form-alert lp-alert" id="form-general-error" role="alert" data-lp-alert <?= $err('form') === null ? 'style="display:none;"' : '' ?>>
      <?= e($err('form') ?? '') ?>
    </div>

    <form class="lp-form" id="quote-form" method="POST" action="<?= e_attr(url('/get-a-quote')) ?>" novalidate aria-labelledby="lead-form-heading" data-lp-form>
      <?= csrf_field() ?>
      <input type="hidden" name="_t" value="<?= e_attr(time()) ?>">

      <!-- Campaign Attribution (filled automatically by JavaScript) -->
      <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid'] as $utm): ?>
        <input type="hidden" name="<?= e_attr($utm) ?>" value="<?= e_attr($val($utm)) ?>" data-lp-utm>
      <?php endforeach ?>

      <!-- Anti-Spam Honeypot: Hidden from real humans -->
      <div style="display:none;" aria-hidden="true">
        <label for="lead-hp-<?= e_attr($hp) ?>">Company Website</label>
        <input type="text" id="lead-hp-<?= e_attr($hp) ?>" name="<?= e_attr($hp) ?>" tabindex="-1" autocomplete="off">
      </div>

      <!-- Field: Full Name -->
      <div class="field">
        <label class="field__label" for="lead-name">Full Name <span class="field__req" aria-hidden="true">*</span></label>
        <input class="field__input" type="text" id="lead-name" name="name" value="<?= e_attr($val('name')) ?>"
               placeholder="e.g. Maria Santos" required autocomplete="name" maxlength="<?= e_attr($limits['name']) ?>"
               <?= $err('name') !== null ? 'aria-invalid="true"' : '' ?>>
        <p class="field__error" id="error-name" data-lp-error="name" <?= $err('name') === null ? 'style="display:none;"' : '' ?>><?= e($err('name') ?? '') ?></p>
      </div>

      <!-- Row: Mobile Number & Email -->
      <div class="lp-form__row">
        <div class="field">
          <label class="field__label" for="lead-phone">Mobile Number <span class="field__req" aria-hidden="true">*</span></label>
          <input class="field__input" type="tel" id="lead-phone" name="phone" value="<?= e_attr($val('phone')) ?>"
                 placeholder="0917 123 4567" required autocomplete="tel" inputmode="tel" maxlength="<?= e_attr($limits['phone']) ?>"
                 <?= $err('phone') !== null ? 'aria-invalid="true"' : '' ?>>
          <p class="field__error" id="error-phone" data-lp-error="phone" <?= $err('phone') === null ? 'style="display:none;"' : '' ?>><?= e($err('phone') ?? '') ?></p>
        </div>

        <div class="field">
          <label class="field__label" for="lead-email">Email Address <span class="field__req" aria-hidden="true">*</span></label>
          <input class="field__input" type="email" id="lead-email" name="email" value="<?= e_attr($val('email')) ?>"
                 placeholder="maria@company.com" required autocomplete="email" inputmode="email" maxlength="<?= e_attr($limits['email']) ?>"
                 <?= $err('email') !== null ? 'aria-invalid="true"' : '' ?>>
          <p class="field__error" id="error-email" data-lp-error="email" <?= $err('email') === null ? 'style="display:none;"' : '' ?>><?= e($err('email') ?? '') ?></p>
        </div>
      </div>

      <!-- Field: Service Type -->
      <div class="field">
        <label class="field__label" for="lead-type">Service Needed <span class="field__req" aria-hidden="true">*</span></label>
        <select class="field__input" id="lead-type" name="type" required <?= $err('type') !== null ? 'aria-invalid="true"' : '' ?>>
          <option value="" disabled <?= $val('type') === '' ? 'selected' : '' ?>>Select an event service</option>
          <?php foreach ($services as $service): ?>
            <option value="<?= e_attr($service) ?>" <?= $val('type') === $service ? 'selected' : '' ?>><?= e($service) ?></option>
          <?php endforeach ?>
        </select>
        <p class="field__error" id="error-type" data-lp-error="type" <?= $err('type') === null ? 'style="display:none;"' : '' ?>><?= e($err('type') ?? '') ?></p>
      </div>

      <!-- Field: Event Details (Optional) -->
      <div class="field">
        <label class="field__label" for="lead-details">Event Details <span class="field__opt">Optional</span></label>
        <textarea class="field__input lp-form__area" id="lead-details" name="details" rows="2"
                  placeholder="Tell us the event date, venue location, or specific requirements..."
                  maxlength="<?= e_attr($limits['details']) ?>"><?= e($val('details')) ?></textarea>
        <p class="field__error" id="error-details" data-lp-error="details" <?= $err('details') === null ? 'style="display:none;"' : '' ?>><?= e($err('details') ?? '') ?></p>
      </div>

      <!-- Submit Button -->
      <button class="btn lp-form__submit" type="submit" id="submit-btn" data-lp-submit>
        <span id="submit-text" data-lp-submit-label><?= e($buttonText) ?></span>
        <span class="lp-spinner" id="submit-spinner" aria-hidden="true"></span>
      </button>

      <p class="lp-form__note"><?= e($reassurance) ?></p>
    </form>
  </div>

  <!-- 2. Success Message (Shown after confirmed inquiry submission) -->
  <div id="form-success" class="lp-success" tabindex="-1" role="status" data-lp-success style="display:none;">
    <div class="lp-success__icon" aria-hidden="true">✓</div>
    <h2 class="lp-form-card__title">Inquiry Received!</h2>
    <p>Thank you for reaching out. Our team will review your requirements and get in touch with you within one business day.</p>
    <p class="mono lp-success__ref" id="success-reference" data-lp-reference></p>
  </div>

</div>
