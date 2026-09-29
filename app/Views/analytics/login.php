<?php
/**
 * Analytics login page — consistent with the 3AM design system.
 *
 * @var App\Core\View $this
 * @var string|null   $error
 * @var string        $email
 */
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1622">
  <title>Analytics Login — 3AM Digital Media</title>
  <link rel="icon" href="<?= e_attr(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;800;900&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/analytics.css')) ?>">
</head>
<body>
<div class="analytics-login-wrap">
  <div class="analytics-login">
    <div class="analytics-login__brand">
      <div class="analytics-login__logo" aria-hidden="true">
        <img src="<?= e_attr(site_media('media/logo-mark.png')) ?> " alt="">
      </div>
      <h1 class="analytics-login__title">Analytics</h1>
      <p class="analytics-login__sub">Admin Access Only</p>
    </div>

    <div class="analytics-login__card">
      <?php if ($error !== null): ?>
      <div class="analytics-login__error" role="alert">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm.75 4v4.5h-1.5V5h1.5zm0 6v1.5h-1.5V11h1.5z"/></svg>
        <?= e($error) ?>
      </div>
      <?php endif ?>

      <form method="POST" action="<?= e_attr(url('analytics/login')) ?>" id="analytics-login-form">
        <?= csrf_field() ?>
        <label class="analytics-login__label" for="email">Email Address</label>
        <input class="analytics-login__input" type="email" id="email" name="email"
               value="<?= e_attr($email ?? '') ?>"
               placeholder="admin@3ammediatech.com"
               required autocomplete="email">

        <label class="analytics-login__label" for="password">Password</label>
        <input class="analytics-login__input" type="password" id="password" name="password"
               placeholder="••••••••"
               required autocomplete="current-password">

        <button type="submit" class="analytics-login__submit">Sign in to Analytics</button>
      </form>
    </div>

    <p class="analytics-login__foot">3AM Digital Media &middot; Analytics Dashboard</p>
  </div>
</div>
</body>
</html>
