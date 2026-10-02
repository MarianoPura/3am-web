<?php
$activeSection = (string) ($section ?? 'dashboard');
$links = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => url('rentals/admin')],
    ['key' => 'analytics', 'label' => 'Analytics', 'href' => url('rentals/admin/analytics')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => url('rentals/admin/orders')],
    ['key' => 'items', 'label' => 'Products', 'href' => url('rentals/admin/items')],
    ['key' => 'categories', 'label' => 'Categories', 'href' => url('rentals/admin/categories')],
    ['key' => 'sales-report', 'label' => 'Sales Report', 'href' => url('rentals/admin/sales-report')],
    ['key' => 'payment-report', 'label' => 'Payment Report', 'href' => url('rentals/admin/payment-report')],
    ['key' => 'payments', 'label' => 'Payment Methods', 'href' => url('rentals/admin/payments')],
    ['key' => 'customers', 'label' => 'Customers', 'href' => url('rentals/admin/customers')],
];
?>
<header class="rentals-admin-header">
  <div class="rentals-shell rentals-admin-header__top">
    <a class="rentals-admin-header__brand" href="<?= e_attr(url('rentals/admin')) ?>" aria-label="3AM Rentals Admin dashboard">
      <img src="<?= e_attr(site_media('media/logo-mark.png')) ?>" alt="" width="28" height="28">
      <span><strong>3AM</strong><small>Rentals Admin</small></span>
    </a>
    <div class="rentals-admin-header__account">
      <span><?= e((string) ($adminUser['name'] ?? 'Administrator')) ?></span>
      <form method="post" action="<?= e_attr(url('rentals/logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </div>
  </div>
  <div class="rentals-admin-header__nav-wrap">
    <div class="rentals-shell rentals-admin-header__nav-row">
      <nav class="rentals-admin-header__nav" aria-label="Rentals Admin navigation">
        <?php foreach ($links as $link): ?>
          <a href="<?= e_attr($link['href']) ?>"<?= $activeSection === $link['key'] ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        <?php endforeach ?>
      </nav>
      <a class="rentals-admin-header__back" href="<?= e_attr(url('rentals')) ?>">← Back to Rentals</a>
    </div>
  </div>
</header>
