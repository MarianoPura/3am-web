<?php
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = [
    ['label' => 'Home', 'path' => url('rentals')],
    ['label' => 'Equipment', 'path' => url('rentals/items')],
    ['label' => 'Cart', 'path' => url('rentals/cart')],
    ['label' => 'Services', 'path' => url('rentals/services')],
    ['label' => 'How to Rent', 'path' => url('rentals/how-to-rent')],
    ['label' => 'Support', 'path' => url('rentals/support')],
];
try { $cartCount = (new \App\Services\RentalCart())->count(); }
catch (\Throwable $e) { $cartCount = 0; }
$navCustomer = null;
try {
    $navCustomer = (new \App\Services\RentalAccount(
        app(\App\Core\Database::class),
        new \App\Services\RentalCart()
    ))->current();
} catch (\Throwable $e) {
    $navCustomer = null;
}
$navInitial = '?';
if (preg_match('/\p{L}/u', trim((string) ($navCustomer['name'] ?? '')), $firstLetter) === 1) {
    $navInitial = function_exists('mb_strtoupper')
        ? mb_strtoupper($firstLetter[0], 'UTF-8')
        : strtoupper($firstLetter[0]);
}
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
        <a href="<?= e_attr($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?><?php if ($link['label'] === 'Cart'): ?> <span class="rentals-nav__count" data-rentals-cart-count<?= $cartCount > 0 ? '' : ' hidden' ?>><?= e((string) $cartCount) ?></span><?php endif ?></a>
      <?php endforeach ?>
    </nav>

    <a class="rentals-header__return" href="<?= e_attr(url('/')) ?>">← 3AM Main Site</a>

    <a class="rentals-header__account" href="<?= e_attr(url('rentals/account')) ?>"<?= $navCustomer !== null ? ' aria-label="Customer account"' : '' ?>>
      <?php if ($navCustomer !== null): ?><span class="rentals-header__avatar" aria-hidden="true"><?= e($navInitial) ?></span><?php endif ?>
      <span><?= $navCustomer !== null ? 'Account' : 'Sign In' ?></span>
    </a>
  </div>
</header>
