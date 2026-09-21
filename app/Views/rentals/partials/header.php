<?php
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = [
    ['label' => 'Home', 'path' => url('rentals')],
    ['label' => 'Categories', 'path' => url('rentals/categories')],
    ['label' => 'Rental Items', 'path' => url('rentals/items')],
    ['label' => 'Services', 'path' => url('rentals/services')],
    ['label' => 'How to Rent', 'path' => url('rentals/how-to-rent')],
    ['label' => 'Support', 'path' => url('rentals/support')],
];
?>
<header class="rentals-header">
  <div class="rentals-shell rentals-header__inner">
    <a class="rentals-brand" href="<?= e_attr(url('/')) ?>" aria-label="Return to 3AM main website">
      <span class="rentals-brand__mark" aria-hidden="true"></span>
      <span>
        <strong>3AM</strong>
        <small>Rentals</small>
      </span>
    </a>

    <nav class="rentals-nav" aria-label="Rentals navigation">
      <?php foreach ($links as $link):
          $href = $link['path'];
          $active = rtrim($href, '/') === $currentPath;
      ?>
        <a href="<?= e_attr($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
      <?php endforeach ?>
    </nav>

    <a class="rentals-header__return" href="<?= e_attr(url('/')) ?>">← 3AM Main Site</a>
  </div>
</header>
