<?php
/**
 * Inquiry confirmation.
 *
 * Its own URL, so the conversion is measurable in analytics and so a refresh
 * cannot resubmit the form.
 *
 * @var App\Core\View $this
 * @var string|null $reference
 * @var array $company
 */

$this->extend('layouts.base');
?>

<?php $this->start('title') ?>Enquiry received — 3AM<?php $this->end() ?>

<?php $this->start('head') ?>
<meta name="robots" content="noindex">
<?php $this->end() ?>

<?php $this->start('content') ?>

<section class="section form-page" data-theme="dark" aria-labelledby="received-heading">
  <div class="shell form-shell">

    <p class="mono section__label">
      <span class="tally" aria-hidden="true"></span> Received
    </p>

    <h1 id="received-heading" class="section__title">Got it.</h1>

    <p class="section__lede">
      Your enquiry is with us. Someone from the relevant team will come back to
      you — usually within one business day.
    </p>

    <?php if ($reference !== null): ?>
      <div class="reference">
        <p class="mono reference__label">Your reference</p>
        <p class="reference__code"><?= e($reference) ?></p>
        <p class="reference__note">
          Quote this if you call or email about the same project.
        </p>
      </div>
    <?php endif ?>

    <div class="form__actions">
      <a class="btn" href="<?= e_attr(url('projects')) ?>">Explore our projects</a>
      <a class="btn btn--ghost" href="<?= e_attr(url('/')) ?>">Back</a>
    </div>

    <p class="mono form__back">
      <?= e($company['address']['street']) ?>,
      <?= e($company['address']['locality']) ?>
    </p>

  </div>
</section>

<?php $this->end() ?>
