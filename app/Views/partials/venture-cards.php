<div class="ventures" role="region" aria-label="Ventures">
  <?php foreach (config('app.ventures') as $index => $venture): ?>
    <article class="venture-card" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => $venture['slot']]) ?>
      <div class="venture-card__body">
        <?php if ($index === 0 && isset($intro)): ?>
          <div class="service-section__meta">
            <p class="mono section__label">Ventures</p>
            <span class="service-section__rule" aria-hidden="true"></span>
            <span class="mono service-section__index" aria-hidden="true">04</span>
          </div>
          <h2 id="ventures-heading" class="section__title"><?= e($intro['headline']) ?></h2>
          <p class="section__lede"><?= e($intro['subhead']) ?></p>
        <?php endif ?>
        <h3 class="venture-card__name"><?= e($venture['name']) ?></h3>
        <p class="venture-card__text"><?= e($venture['body']) ?></p>
      </div>
    </article>
  <?php endforeach ?>
</div>
