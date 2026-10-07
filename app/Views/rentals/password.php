<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Password recovery — 3AM Rentals<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-section rentals-account-section"><div class="rentals-shell">
  <div class="rentals-account-auth rentals-password-panel">
    <p class="rentals-kicker">Your account</p><h1><?= $token ? 'Choose a new password' : 'Forgot password?' ?></h1>
    <?php if ($error): ?><p role="alert" class="rentals-account__notice"><?= e($error) ?></p><?php endif ?>
    <?php if ($notice): ?><p role="status"><?= e($notice) ?></p><?php elseif ($token && !($valid ?? false)): ?>
      <p><a href="<?= e_attr(url('rentals/account/forgot')) ?>">Request a new reset link</a></p>
    <?php else: ?>
      <form method="post" action="<?= e_attr($token ? url('rentals/account/reset/'.$token) : url('rentals/account/forgot')) ?>" class="rentals-account__form">
        <?= csrf_field() ?>
        <?php if ($token): ?>
          <label>New password<input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="72"></label>
          <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" maxlength="72"></label>
        <?php else: ?><label>Email<input type="email" name="email" autocomplete="email" required maxlength="190"></label><?php endif ?>
        <button type="submit" class="rentals-btn rentals-btn--primary"><?= $token ? 'Update password' : 'Send reset link' ?></button>
      </form>
    <?php endif ?>
    <p><a href="<?= e_attr(url('rentals/account')) ?>">Back to sign in</a></p>
  </div>
</div></section>
<?php $this->end() ?>
