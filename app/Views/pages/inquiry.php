<?php
/**
 * Unified project inquiry form.
 *
 * Accessibility notes, since forms are where it usually goes wrong:
 *   - Every field has a real visible <label>. Placeholder-only labelling
 *     disappears the moment someone starts typing, which strands anyone who
 *     looks away mid-field.
 *   - Errors are linked with aria-describedby and announced via aria-live, so
 *     a screen-reader user hears what failed instead of being silently
 *     returned to the top of a form.
 *   - Fields carry autocomplete and inputmode so a phone keyboard shows the
 *     right keys and browser autofill works.
 *
 * @var App\Core\View $this
 * @var array $form
 * @var array $old
 * @var array $errors
 * @var array $company
 */

$this->extend('layouts.base');

$val  = static fn (string $k): string => (string) ($old[$k] ?? '');
$err  = static fn (string $k): ?string => $errors[$k] ?? null;
?>

<?php $this->start('title') ?><?= e($form['title']) ?> — 3AM<?php $this->end() ?>
<?php $this->start('description') ?><?= e($form['lede']) ?><?php $this->end() ?>

<?php $this->start('head') ?>
<meta name="robots" content="noindex">
<?php $this->end() ?>

<?php $this->start('content') ?>

<section class="section form-page" data-theme="light" aria-labelledby="form-heading">
  <div class="shell form-shell form-shell--inquiry">
    <div class="form-intro">

    <p class="mono section__label"><?= e($form['kicker']) ?></p>
    <h1 id="form-heading" class="section__title"><?= e($form['title']) ?></h1>
    <p class="section__lede"><?= e($form['lede']) ?></p>


    <p class="form-intro__response">We usually respond within one business day.</p>
    <p class="form-intro__contact">
      <a href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
    </p>
    <p class="mono form__back">
      <a href="<?= e_attr(url('/')) ?>">&larr; Back</a>
    </p>
    </div>

    <div class="form-fields">
    <?php if ($err('form') !== null): ?>
      <div class="form-alert" role="alert"><?= e($err('form')) ?></div>
    <?php endif ?>

    <form class="form" method="post" action="<?= e_attr(url('start')) ?>" novalidate>
      <?= csrf_field() ?>

      <?php /* Time of render. A submission faster than a human could read the
               form is a bot. Paired with the honeypot below. */ ?>
      <input type="hidden" name="_t" value="<?= e_attr(time()) ?>">

      <?php /* Honeypot. Hidden from sight AND from assistive technology, so no
               real user can fill it by accident — including a screen-reader
               user, who would otherwise be silently rejected. */ ?>
      <div class="form-hp" aria-hidden="true">
        <label for="<?= e_attr(config('forms.honeypot_field')) ?>">Company website</label>
        <input type="text" id="<?= e_attr(config('forms.honeypot_field')) ?>"
               name="<?= e_attr(config('forms.honeypot_field')) ?>"
               tabindex="-1" autocomplete="off">
      </div>

      <fieldset class="project-choice" <?= $err('type') ? 'aria-invalid="true" aria-describedby="type-error"' : '' ?>>
        <legend class="field__label">What do you need? <span aria-hidden="true">*</span></legend>
        <div class="project-choice__options">
          <?php foreach ($form['types'] as $option): ?>
            <label class="project-choice__option">
              <input type="radio" name="type" value="<?= e_attr($option) ?>" required <?= $val('type') === $option ? 'checked' : '' ?>>
              <span><?= e($option) ?></span>
            </label>
          <?php endforeach ?>
        </div>
        <?php if ($err('type')): ?><p class="field__error" id="type-error"><?= e($err('type')) ?></p><?php endif ?>
      </fieldset>
      <div class="field-row">
        <!-- Name -->
        <div class="field">
          <label class="field__label" for="name">
            Name <span class="field__req" aria-hidden="true">*</span>
          </label>
          <input class="field__input" type="text" id="name" name="name"
                 value="<?= e_attr($val('name')) ?>" required
                 autocomplete="name" maxlength="120"
                 <?= $err('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
          <?php if ($err('name')): ?>
            <p class="field__error" id="name-error"><?= e($err('name')) ?></p>
          <?php endif ?>
        </div>

        <!-- Company -->
        <div class="field">
          <label class="field__label" for="company">Company <span class="field__opt">optional</span></label>
          <input class="field__input" type="text" id="company" name="company"
                 value="<?= e_attr($val('company')) ?>"
                 autocomplete="organization" maxlength="160" <?= $err('company') ? 'aria-invalid="true" aria-describedby="company-error"' : '' ?>>
          <?php if ($err('company')): ?><p class="field__error" id="company-error"><?= e($err('company')) ?></p><?php endif ?>
        </div>
      </div>

      <div class="field-row">
        <!-- Email -->
        <div class="field">
          <label class="field__label" for="email">
            Email <span class="field__req" aria-hidden="true">*</span>
          </label>
          <input class="field__input" type="email" id="email" name="email"
                 value="<?= e_attr($val('email')) ?>" required
                 autocomplete="email" inputmode="email" maxlength="190"
                 <?= $err('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
          <?php if ($err('email')): ?>
            <p class="field__error" id="email-error"><?= e($err('email')) ?></p>
          <?php endif ?>
        </div>

        <!-- Phone -->
        <div class="field">
          <label class="field__label" for="phone">
            Contact number <span class="field__req" aria-hidden="true">*</span>
          </label>
          <input class="field__input" type="tel" id="phone" name="phone"
                 value="<?= e_attr($val('phone')) ?>" required
                 autocomplete="tel" inputmode="tel" maxlength="40"
                 <?= $err('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
          <?php if ($err('phone')): ?>
            <p class="field__error" id="phone-error"><?= e($err('phone')) ?></p>
          <?php endif ?>
        </div>
      </div>

      <!-- Details -->
      <div class="field">
        <label class="field__label" for="details">
          Project details <span class="field__req" aria-hidden="true">*</span>
        </label>
        <textarea class="field__input field__input--area" id="details" name="details"
                  rows="4" required maxlength="5000"
                  placeholder="Include your requirements, location and dates."
                  <?= $err('details') ? 'aria-describedby="details-error"' : '' ?>
                  <?= $err('details') ? 'aria-invalid="true"' : '' ?>><?= e($val('details')) ?></textarea>
        <?php if ($err('details')): ?>
          <p class="field__error" id="details-error"><?= e($err('details')) ?></p>
        <?php endif ?>
      </div>

      <div class="form__actions">
        <button class="btn" type="submit">Send enquiry</button>
      </div>
    </form>
    </div>

  </div>
</section>

<?php $this->end() ?>
