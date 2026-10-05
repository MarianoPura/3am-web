<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Account Settings — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/account/settings')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Update your 3AM Rentals account name and password.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
// Pull data passed in from the controller, with safe defaults
$user    = is_array($user ?? null)      ? $user    : [];
$success = is_string($success ?? null)  ? $success : null;
$error   = is_string($error ?? null)    ? $error   : null;
?>

<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div>
      <p class="rentals-kicker">Customer account</p>
      <h1>Account settings.</h1>
    </div>
    <p>Update your display name or change your password.</p>
  </div>
</section>

<section class="rentals-section rentals-section--tight rentals-account">
  <div class="rentals-shell">
    <div class="rentals-support-panel">

      <p class="rentals-card__meta">
        <a href="<?= e_attr(url('rentals/account')) ?>">← Back to account</a>
      </p>

      <?php if ($success !== null): ?>
        <div class="rentals-account__success" role="status">
          <?= e($success) ?>
        </div>
      <?php endif ?>

      <?php if ($error !== null): ?>
        <div class="rentals-account__error" role="alert">
          <?= e($error) ?>
        </div>
      <?php endif ?>

      <form
        method="post"
        action="<?= e_attr(url('rentals/account/settings')) ?>"
        class="rentals-account__form"
      >
        <?= csrf_field() ?>

        <h2>Your details</h2>

        <div class="rentals-account__fields">

          <label class="rentals-date-field">
            Name
            <input
              type="text"
              name="name"
              autocomplete="name"
              required
              maxlength="150"
              value="<?= e_attr((string) ($user['name'] ?? '')) ?>"
            >
          </label>

          <label class="rentals-date-field">
            Email
            <input
              type="email"
              name="email"
              required
              maxlength="190"
              autocomplete="email"
              value="<?= e_attr((string) ($user['email'] ?? '')) ?>"
            >
          </label>

        </div>

        <h2>Change password</h2>
        <p>Leave these fields empty if you do not want to change your password.</p>

        <div class="rentals-account__fields">

          <label class="rentals-date-field">
            Current password
            <input
              type="password"
              name="current_password"
              autocomplete="current-password"
            >
          </label>

          <label class="rentals-date-field">
            New password
            <input
              type="password"
              name="new_password"
              autocomplete="new-password"
              minlength="8"
            >
          </label>

        </div>

        <button class="rentals-btn rentals-btn--primary" type="submit">
          Save changes
        </button>

      </form>

    </div>
  </div>
</section>

<?php $this->end() ?>
