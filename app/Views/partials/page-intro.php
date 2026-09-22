<section class="section page-intro" data-theme="light" aria-labelledby="page-heading">
  <div class="shell">
    <p class="mono section__label"><?= e($label) ?></p>
    <h1 id="page-heading" class="page-intro__title"><?= e($title) ?></h1>
    <p class="section__lede"><?= e($description) ?></p>
    <?php if (!empty($cta)): ?>
      <a class="btn page-intro__action" href="<?= e_attr($cta['href']) ?>"><?= e($cta['label']) ?><span aria-hidden="true">↗</span></a>
    <?php endif ?>
  </div>
</section>
