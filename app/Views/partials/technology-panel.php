<?php $index = $index ?? null; ?>
<div class="systems__grid">
  <div class="systems__services">
    <div class="systems__intro">
      <div class="service-section__meta">
        <p class="mono section__label">Technology</p>
        <span class="service-section__rule" aria-hidden="true"></span>
        <?php if ($index !== null): ?><span class="mono service-section__index" aria-hidden="true"><?= e(str_pad((string) $index, 2, '0', STR_PAD_LEFT)) ?></span><?php endif ?>
      </div>
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
  </div>
  <div class="systems__operations" data-reveal>
    <?= $this->partial('partials.frame', ['slot' => 'channel.technology']) ?>
    <p class="mono systems__caption">Technology in operation</p>
  </div>
</div>
