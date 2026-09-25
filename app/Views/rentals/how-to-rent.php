<?php $this->extend('rentals.layouts.base'); ?>
<?php $this->start('title') ?>How to Rent — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/how-to-rent')) ?><?php $this->end() ?>
<?php $this->start('description') ?>Learn how to request rental equipment and production support from 3AM.<?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-page-hero">
  <div class="rentals-shell rentals-page-hero__inner">
    <div><p class="rentals-kicker">How to rent</p><h1>From browse to rental request.</h1></div>
    <p>Choose equipment, set dates and quantity, check availability, then review your Cart before submitting.</p>
  </div>
</section>

<section class="rentals-section">
  <div class="rentals-shell">
    <div class="rentals-section__header">
      <p class="rentals-kicker">How to rent</p>
      <h2>A clear path to your equipment.</h2>
      <p>Your request is recorded as Pending Review. You can return to your account to track it.</p>
    </div>

    <div class="rentals-steps-grid">
      <?php foreach ([
        ['Browse', 'Search equipment or filter by category to find a suitable item.'],
        ['Add to cart', 'Open equipment details, choose quantity and dates, then add the item to your Cart.'],
        ['Checkout', 'Sign in or create an account, choose a payment method and upload your payment proof.'],
        ['Pending review', 'Submit your request and track payment review under My Rentals.'],
      ] as $stepIndex => [$title, $body]): ?>
        <article class="rentals-step">
          <p class="rentals-step__number"><?= e(str_pad((string) ($stepIndex + 1), 2, '0', STR_PAD_LEFT)) ?></p>
          <h3><?= e($title) ?></h3>
          <p><?= e($body) ?></p>
        </article>
      <?php endforeach ?>
    </div>
    <div class="rentals-inline-actions"><a class="rentals-btn rentals-btn--primary" href="<?= e_attr(url('rentals/items')) ?>">Browse rental items</a></div>
  </div>
</section>
<?php $this->end() ?>
