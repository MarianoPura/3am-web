<?php
/**
 * Analytics area layout — consistent with the 3AM rentals admin shell.
 *
 * Uses tokens.css, app.css, and analytics.css.
 * Light ground (--c-paper), navy ink, tally amber accent.
 *
 * @var App\Core\View $this
 * @var array|null    $user
 */
$siteTitle = $this->section('title') ?: 'Analytics';
$user      = $user ?? null;

// Determine active nav key from URL path
$basePath  = (string) config('app.base_path', '');
$rawPath   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
$path      = $basePath !== '' ? str_replace($basePath, '', $rawPath) : $rawPath;
$path      = '/' . ltrim($path, '/');

if (str_starts_with($path, '/analytics/events'))   { $activeKey = 'events'; }
elseif (str_starts_with($path, '/analytics/visitors')) { $activeKey = 'visitors'; }
else { $activeKey = 'dashboard'; }
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1622">
  <title><?= e($siteTitle) ?> — 3AM Analytics</title>
  <link rel="icon" href="<?= e_attr(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;800;900&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/analytics.css')) ?>">
</head>
<body class="analytics-module">
<a class="skip-link" href="#analytics-content">Skip to content</a>

<?php if ($user !== null): ?>
<header class="analytics-header">
  <div class="analytics-shell analytics-header__top">
    <a class="analytics-header__brand" href="<?= e_attr(url('analytics')) ?>" aria-label="3AM Analytics dashboard">
      <span class="analytics-header__brand-dot"></span>
      <span class="analytics-header__brand-wordmark">
        <strong>3AM ANALYTICS</strong>
        <small>Meta Pixel Tracking</small>
      </span>
    </a>
    <div class="analytics-header__account">
      <span><?= e((string) ($user['name'] ?? 'Admin')) ?></span>
      <form method="post" action="<?= e_attr(url('analytics/logout')) ?>">
        <?= csrf_field() ?>
        <button type="submit">Sign out</button>
      </form>
    </div>
  </div>
  <div class="analytics-header__nav-wrap">
    <div class="analytics-shell analytics-header__nav-row">
      <nav class="analytics-header__nav" aria-label="Analytics navigation">
        <a href="<?= e_attr(url('analytics')) ?>"<?= $activeKey === 'dashboard' ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1 11h2v4H1zm4-4h2v8H5zm4-3h2v11H9zm4-3h2v14h-2z"/></svg>
          Dashboard
        </a>
        <a href="<?= e_attr(url('analytics/visitors')) ?>"<?= $activeKey === 'visitors' ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 7a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1c-2.67 0-8 1.34-8 4v1h16v-1c0-2.66-5.33-4-8-4z"/></svg>
          Visitors
        </a>
      </nav>
    </div>
  </div>
</header>
<?php endif ?>

<main class="analytics-main" id="analytics-content">
  <?= $this->section('content') ?>
</main>

<footer class="analytics-footer">
  <div class="analytics-shell">3AM Digital Media &middot; Analytics</div>
</footer>
</body>
</html>
