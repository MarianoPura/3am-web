<?php
/**
 * Project inquiry form — serves both the media and technology variants.
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

$slug = $form['slug'];
$val  = static fn (string $k): string => (string) ($old[$k] ?? '');
$err  = static fn (string $k): ?string => $errors[$k] ?? null;
?>

<?php $this->start('title') ?><?= e($form['title']) ?> — 3AM<?php $this->end() ?>
<?php $this->start('description') ?><?= e($form['lede']) ?><?php $this->end() ?>

<?php $this->start('head') ?>
<meta name="robots" content="noindex">
<?php $this->end() ?>

<?php $this->start('content') ?>

<section class="section form-page" data-theme="dark" aria-labelledby="form-heading">
  <div class="shell form-shell">

    <p class="mono section__label"><?= e($form['kicker']) ?></p>
    <h1 id="form-heading" class="section__title"><?= e($form['title']) ?></h1>
    <p class="section__lede"><?= e($form['lede']) ?></p>

    <?php if ($err('form') !== null): ?>
      <div class="form-alert" role="alert"><?= e($err('form')) ?></div>
    <?php endif ?>

    <form class="form" method="post" action="<?= e_attr(url('start/' . $slug)) ?>" novalidate>
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

      <!-- Project type -->
      <div class="field">
        <label class="field__label" for="type">
          Type of project <span class="field__req" aria-hidden="true">*</span>
        </label>
        <select class="field__input" id="type" name="type" required
                <?= $err('type') ? 'aria-invalid="true" aria-describedby="type-error"' : '' ?>>
          <option value="">Select one…</option>
          <?php foreach ($form['types'] as $option): ?>
            <option value="<?= e_attr($option) ?>" <?= $val('type') === $option ? 'selected' : '' ?>>
              <?= e($option) ?>
            </option>
          <?php endforeach ?>
        </select>
        <?php if ($err('type')): ?>
          <p class="field__error" id="type-error"><?= e($err('type')) ?></p>
        <?php endif ?>
      </div>

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
                 autocomplete="organization" maxlength="160">
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
        <p class="field__hint" id="details-hint">
          Please include your requirements, the location, and your dates —
          those three things let us come back with a real answer rather than
          another round of questions.
        </p>
        <textarea class="field__input field__input--area" id="details" name="details"
                  rows="7" required maxlength="5000"
                  aria-describedby="details-hint<?= $err('details') ? ' details-error' : '' ?>"
                  <?= $err('details') ? 'aria-invalid="true"' : '' ?>><?= e($val('details')) ?></textarea>
        <?php if ($err('details')): ?>
          <p class="field__error" id="details-error"><?= e($err('details')) ?></p>
        <?php endif ?>
      </div>

      <div class="form__actions">
        <button class="btn" type="submit">Send enquiry</button>
      </div>
    </form>

    <p class="mono form__back">
      <a href="<?= e_attr(url('/')) ?>">&larr; Back</a>
    </p>

  </div>
</section>

<?php $this->end() ?>
