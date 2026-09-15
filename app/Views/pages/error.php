<?php
/**
 * Error page.
 *
 * Deliberately says nothing about why. An error page that reports whether a
 * record exists, or which component failed, is a reconnaissance tool — and this
 * one also answers requests for reserved legacy paths, where confirming that
 * something is there would be worse than an honest miss.
 *
 * @var App\Core\View $this
 * @var int    $status
 * @var string $message
 */

$this->extend('layouts.base');

$titles = [
    404 => 'Not found',
    403 => 'Not permitted',
    405 => 'Method not allowed',
    419 => 'Session expired',
    429 => 'Too many requests',
    500 => 'Something went wrong',
];

$title = $titles[$status] ?? 'Error';
?>

<?php $this->start('title') ?><?= e($status . ' — ' . $title) ?><?php $this->end() ?>

<?php $this->start('head') ?>
<meta name="robots" content="noindex">
<?php $this->end() ?>

<?php $this->start('content') ?>
<section class="error shell" data-theme="dark">
  <p class="mono">Signal lost</p>

  <p class="error__code"><?= e($status) ?></p>

  <h1 style="font-size:var(--t-h2)"><?= e($title) ?></h1>

  <?php if ($message !== ''): ?>
    <p style="color:var(--fg-muted)"><?= e($message) ?></p>
  <?php endif ?>

  <p style="margin-top:var(--s-6)">
    <a href="<?= e_attr(url('/')) ?>" class="mono" style="color:var(--c-tally)">
      Return to start &rarr;
    </a>
  </p>
</section>
<?php $this->end() ?>
