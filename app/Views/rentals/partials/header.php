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
      <img class="rentals-brand__mark" src="<?= e_attr(site_media('media/logo-mark.png')) ?>" alt="" width="32" height="32">
      <span class="rentals-brand__text">
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
      <?php
      $cartCount = 0;
      if (isset($_SESSION['rentals_cart']) && is_array($_SESSION['rentals_cart'])) {
          foreach ($_SESSION['rentals_cart'] as $entry) {
              if (is_array($entry)) {
                  $cartCount += max(0, (int) ($entry['quantity'] ?? 0));
              }
          }
      }
      ?>
      <a href="<?= e_attr(url('rentals/cart')) ?>" aria-label="Rental cart" style="display:inline-flex; align-items:center; gap:0.4rem;">
        Cart
        <?php if ($cartCount > 0): ?>
          <span style="display:inline-flex; align-items:center; justify-content:center; min-width:1.35rem; height:1.35rem; border-radius:999px; background: var(--rentals-accent); color: var(--c-ink); font-size:0.72rem; font-weight:800; line-height:1; padding:0 0.28rem;">
            <?= e((string) $cartCount) ?>
          </span>
        <?php endif ?>
      </a>
    </nav>

    <a class="rentals-header__return" href="<?= e_attr(url('/')) ?>" aria-label="Return to 3AM main site">
      <span aria-hidden="true">←</span> Main Site
    </a>
  </div>
</header>
