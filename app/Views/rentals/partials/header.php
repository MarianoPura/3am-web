<?php
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$links = [
    ['label' => 'Home', 'path' => url('rentals')],
    ['label' => 'Equipment', 'path' => url('rentals/items')],
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

    <button class="rentals-nav-toggle" type="button" data-rentals-nav-toggle
            aria-controls="rentals-primary-nav" aria-expanded="false" aria-label="Open Rentals menu">
      <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
    </button>

    <nav class="rentals-nav" id="rentals-primary-nav" aria-label="Rentals navigation">
      <?php foreach ($links as $link):
          $href = $link['path'];
          $active = rtrim($href, '/') === $currentPath;
      ?>
        <a href="<?= e_attr($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
      <?php endforeach ?>
      <a class="rentals-header__return" href="<?= e_attr(url('/')) ?>">← 3AM Main Site</a>
    </nav>

    <div class="rentals-header__utilities" aria-label="Rental account controls">
      <a class="rentals-header__cart" href="<?= e_attr(url('rentals/cart')) ?>" aria-label="View Cart" title="View Cart"<?= $currentPath === url('rentals/cart') ? ' aria-current="page"' : '' ?>>
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h3l2.4 12h11.8l2-8H6M8 15l-1 3h12"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
        <span class="rentals-nav__count" data-rentals-cart-count<?= $cartCount > 0 ? '' : ' hidden' ?>><?= e((string) $cartCount) ?></span>
      </a>
      <details class="rentals-account-menu" data-account-menu>
        <summary class="rentals-header__account" aria-controls="rentals-account-popover" aria-expanded="false">
          <?php if ($navCustomer !== null): ?><span class="rentals-header__avatar" aria-hidden="true"><?= e($navInitial) ?></span><?php endif ?>
          <span><?= $navCustomer !== null ? 'Account' : 'Sign In' ?></span><span aria-hidden="true">⌄</span>
        </summary>
        <div class="rentals-account-menu__panel" id="rentals-account-popover">
          <?php if ($navCustomer === null): ?>
            <p class="rentals-card__meta">Your rental account</p>
            <a href="<?= e_attr(url('rentals/account')) ?>">Sign in <span aria-hidden="true">→</span></a>
            <a href="<?= e_attr(url('rentals/account?mode=register')) ?>">Create account <span aria-hidden="true">→</span></a>
          <?php else: ?>
            <p class="rentals-account-menu__name"><?= e((string) $navCustomer['name']) ?></p>
            <a href="<?= e_attr(url('rentals/orders')) ?>">My Rentals</a>
            <a href="<?= e_attr(url('rentals/account')) ?>">Account</a>
            <?php if (in_array(strtolower((string) $navCustomer['role']), ['admin', 'superadmin'], true)): ?><a href="<?= e_attr(url('rentals/admin')) ?>">Rentals Admin</a><?php endif ?>
            <form method="post" action="<?= e_attr(url('rentals/logout')) ?>"><?= csrf_field() ?><button type="submit">Log out</button></form>
          <?php endif ?>
        </div>
      </details>
    </div>
  </div>
</header>
