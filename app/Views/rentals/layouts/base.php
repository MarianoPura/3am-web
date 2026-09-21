<?php
$siteTitle  = $this->section('title') ?: '3AM Rentals';
$siteDesc   = $this->section('description') ?: 'Production equipment and rental resources for media, events and technical operations.';
$canonical  = $this->section('canonical') ?: absolute_url('rentals');
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($siteTitle) ?></title>
  <meta name="description" content="<?= e_attr($siteDesc) ?>">
  <link rel="canonical" href="<?= e_attr($canonical) ?>">
  <meta name="theme-color" content="#0b1622">
  <link rel="stylesheet" href="<?= e_attr(versioned('css/rentals.css')) ?>">
  <?= $this->section('head') ?>
</head>
<body class="rentals-module">
<?= $this->partial('rentals.partials.header') ?>
<main class="rentals-main">
  <?= $this->section('content') ?>
</main>
<?= $this->partial('rentals.partials.footer') ?>
<script src="<?= e_attr(versioned('js/rentals.js')) ?>" defer></script>
</body>
</html>
