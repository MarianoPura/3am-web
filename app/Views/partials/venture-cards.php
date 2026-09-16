<div class="ventures" tabindex="0" role="region" aria-label="Ventures">
  <?php foreach (config('app.ventures') as $venture): ?>
    <article class="venture-card" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => $venture['slot']]) ?>
      <div class="venture-card__body">
        <h3 class="venture-card__name"><?= e($venture['name']) ?></h3>
        <p class="venture-card__text"><?= e($venture['body']) ?></p>
        <a class="text-link" href="<?= e_attr(url($venture['name'] === 'Rentals' ? 'equipment-rentals' : 'start/ventures')) ?>">
          <?= $venture['name'] === 'Rentals' ? 'Explore rentals' : 'Inquire' ?> <span aria-hidden="true">↗</span>
        </a>
      </div>
    </article>
  <?php endforeach ?>
</div>
