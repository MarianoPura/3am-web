<?php
$siteTitle = $this->section('title') ?: 'Rentals Admin — 3AM';
$siteDesc = '3AM Rentals administration.';
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#0b1622">
  <title><?= e($siteTitle) ?></title>
  <meta name="description" content="<?= e_attr($siteDesc) ?>">
  <link rel="icon" href="<?= e_attr(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;800;900&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/rentals.css')) ?>">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/rentals-admin.css')) ?>">
</head>
<body class="rentals-module rentals-admin-module">
<a class="skip-link" href="#rentals-admin-content">Skip to content</a>
<?= $this->partial('rentals.partials.admin-header', ['section' => $section ?? 'dashboard', 'adminUser' => $adminUser ?? null]) ?>
<main class="rentals-admin-main" id="rentals-admin-content">
  <?= $this->section('content') ?>
</main>
<footer class="rentals-admin-footer"><div class="rentals-shell">3AM Rentals · Administration</div></footer>
</body>
</html>
