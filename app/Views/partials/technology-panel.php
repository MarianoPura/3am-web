<div class="systems__grid">
  <div class="systems__services">
    <div class="systems__intro">
      <p class="mono section__label">Technology</p>
      <h2 id="technology-heading" class="section__title"><?= e(config('app.section_technology.headline')) ?></h2>
      <p class="section__lede"><?= e(config('app.section_technology.subhead')) ?></p>
      <?php if (!empty($summary)): ?><p class="service-technology-summary"><?= e($summary) ?></p><?php endif ?>
    </div>
    <?php foreach ([
        ['Platforms & applications', ['Web Development', 'Digital Platforms', 'Digital Experiences', 'Application Development', 'Business Solutions']],
        ['Event & technical systems', ['Event Technology', 'AV / Technical Systems', 'AV Technology', 'LED / Video Systems']],
        ['Streaming infrastructure', ['Camera', 'Switcher', 'Encoder', 'CDN', 'Audience']],
    ] as [$title, $services]): ?>
      <article class="technology-service" data-reveal>
        <h3><?= e($title) ?></h3>
        <ul><?php foreach ($services as $service): ?><li><?= e($service) ?></li><?php endforeach ?></ul>
      </article>
    <?php endforeach ?>
    <a class="text-link" href="<?= e_attr(url('start/technology')) ?>">Start a tech project <span aria-hidden="true">↗</span></a>
  </div>
  <div class="systems__operations" data-reveal>
    <?= $this->partial('partials.frame', ['slot' => 'channel.technology']) ?>
    <p class="mono systems__caption">Technology in operation</p>
  </div>
</div>
