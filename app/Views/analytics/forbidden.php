<?php
/**
 * 403 Forbidden — non-admin user.
 *
 * @var App\Core\View $this
 * @var array|null    $user
 */
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1622">
  <title>Access Denied — 3AM Analytics</title>
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
  <div class="analytics-login" style="text-align:center">
    <div style="margin-bottom:1.5rem;color:var(--c-alert);font:900 4.5rem/1 var(--f-display);letter-spacing:-.04em;text-transform:uppercase">403</div>
    <h1 class="analytics-login__title" style="font-size:1.6rem">Access Denied</h1>
    <p style="margin:.75rem 0 1.5rem;color:var(--c-mist);font-size:.875rem;line-height:1.6">
      You are signed in as <strong style="color:#fff"><?= e((string)($user['email'] ?? '')) ?></strong>,
      but your account does not have admin access to Analytics.
    </p>
    <a href="<?= e_attr(url('analytics/login')) ?>"
       style="display:inline-block;padding:.7rem 1.5rem;background:var(--c-tally);color:var(--c-ink);font:800 .72rem/1 var(--f-display);letter-spacing:.08em;text-transform:uppercase;text-decoration:none">
      Sign in with a different account
    </a>
  </div>
</div>
</body>
</html>
