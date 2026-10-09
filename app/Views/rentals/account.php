<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>Rental Account — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/account')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Sign in or create a customer account for 3AM Rentals.<?php $this->end() ?>
<?php $this->start('content') ?>
<?php
$user = is_array($user ?? null) ? $user : null;
$activeAction = ($action ?? '') === 'register' ? 'register' : 'login';
$submittedEmail = (string) ($submittedEmail ?? '');
?>
<?php if ($user !== null): ?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div>
      <p class="rentals-kicker">Rental account</p>
      <h1>Welcome back.</h1>
    </div>
    <p>Keep your cart and rental requests connected to your customer record.</p>
  </div>
</section>
<section class="rentals-section rentals-section--tight rentals-account">
  <div class="rentals-shell">
      <div class="rentals-support-panel rentals-account__signed-in">
        <p class="rentals-card__meta">Signed in</p>
        <h2><?= e($user['name']) ?></h2>
        <dl class="rentals-account__facts">
          <div><dt>Email</dt><dd><?= e((string) $user['email']) ?></dd></div>
          <div><dt>Account type</dt><dd><?= e(match (strtolower((string) ($user['role'] ?? ''))) { 'admin' => 'Admin', 'superadmin' => 'Superadmin', 'customer' => 'Customer', default => 'Account' }) ?></dd></div>
          <div><dt>Member since</dt><dd><?= e((string) ($user['created_at'] ?? 'Not recorded')) ?></dd></div>
          <div><dt>Last sign in</dt><dd><?= e((string) ($user['last_login'] ?? 'Not recorded')) ?></dd></div>
        </dl>
        <div class="rentals-inline-actions">
          <a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/cart')) ?>">View cart</a>
          <a class="rentals-btn rentals-btn--dark" href="<?= e_attr(url('rentals/orders')) ?>">Rental orders</a>
          <a class="rentals-btn" href="<?= e_attr(url('rentals/service-requests')) ?>">Service requests</a>
          <a class="rentals-btn" href="<?= e_attr(url('rentals/account/settings')) ?>">Account settings</a>
          <form method="post" action="<?= e_attr(url('rentals/logout')) ?>">
            <?= csrf_field() ?>
            <button class="rentals-btn" type="submit">Sign out</button>
          </form>
        </div>
      </div>
  </div>
</section>
<?php else: ?>
<section class="rentals-account rentals-account--guest">
  <div class="rentals-shell">
    <div class="rentals-account__auth">
      <div class="rentals-account__intro">
        <p class="rentals-kicker">Customer account</p>
        <h1>Your rental account.</h1>
        <p>Keep your cart and rental requests connected to your customer record.</p>
      </div>
      <div class="rentals-account__content">
        <nav class="rentals-account__switch" aria-label="Account options">
          <a href="<?= e_attr(url('rentals/account')) ?>"<?= $activeAction === 'login' ? ' aria-current="page"' : '' ?>>Sign in</a>
          <a href="<?= e_attr(url('rentals/account') . '?mode=register') ?>"<?= $activeAction === 'register' ? ' aria-current="page"' : '' ?>>Create account</a>
        </nav>
        <?php if (($error ?? null) !== null): ?>
          <div class="rentals-account__error" role="alert"><?= e($error) ?></div>
        <?php endif ?>
        <form method="post" action="<?= e_attr(url('rentals/account')) ?>" class="rentals-account__form">
          <input type="hidden" name="action" value="<?= e_attr($activeAction) ?>">
          <?= csrf_field() ?>
          <h2><?= $activeAction === 'login' ? 'Sign in' : 'Create account' ?></h2>
          <p><?= $activeAction === 'login' ? 'Continue with your saved cart and rental requests.' : 'Save your selections and keep track of your requests.' ?></p>
          <div class="rentals-account__fields">
            <?php if ($activeAction === 'register'): ?>
              <label class="rentals-date-field">Name<input type="text" name="name" autocomplete="name" required maxlength="150"></label>
            <?php endif ?>
            <label class="rentals-date-field">Email<input type="email" name="email" autocomplete="email" value="<?= e_attr($submittedEmail) ?>" required<?= $activeAction === 'register' ? ' maxlength="190"' : '' ?>></label>
            <label class="rentals-date-field">Password<input type="password" name="password" autocomplete="<?= $activeAction === 'login' ? 'current-password' : 'new-password' ?>" required<?= $activeAction === 'register' ? ' minlength="8"' : '' ?>></label>
          </div>
          <button class="rentals-btn rentals-btn--primary" type="submit"><?= $activeAction === 'login' ? 'Sign in' : 'Create account' ?></button>
        </form>
        <?php if ($activeAction === 'login'): ?><p><a href="<?= e_attr(url('rentals/account/forgot')) ?>">Forgot password?</a></p><?php endif ?>
      </div>
    </div>
  </div>
</section>
<?php endif ?>
<?php $this->end() ?>
