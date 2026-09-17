<div class="ventures" role="region" aria-label="Ventures">
  <?php foreach (config('app.ventures') as $venture): ?>
    <article class="venture-card" data-reveal>
      <?= $this->partial('partials.frame', ['slot' => $venture['slot']]) ?>
      <div class="venture-card__body">
        <h3 class="venture-card__name"><?= e($venture['name']) ?></h3>
        <p class="venture-card__text"><?= e($venture['body']) ?></p>
      </div>
    </article>
  <?php endforeach ?>
</div>
