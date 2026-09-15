<?php
/**
 * Media frame.
 *
 * The container for every image and video on the site — and for every slot
 * that does not have one yet. An empty frame reads as a deliberate production
 * slate, not as a broken image, so the page never looks unfinished while the
 * client is still gathering assets.
 *
 * Templates name a SLOT; everything else comes from config/assets.php. That is
 * the whole point: adding a photo means editing one config file, never a
 * template.
 *
 *     $this->partial('partials.frame', ['slot' => 'hero.showreel']);
 *
 * Direct values still work for one-off cases and override the registry:
 *
 *     $this->partial('partials.frame', ['ratio' => '1x1', 'label' => 'Team']);
 *
 * @var App\Core\View $this
 */

// Pull the slot definition, then let any explicitly passed value win over it.
//
// Fetch the whole slots array and index it directly rather than using
// config('assets.slots.hero.showreel') — config() splits on dots, so that
// would look for a nested hero → showreel path instead of the literal
// 'hero.showreel' key. Dotted slot names read better in the registry, so the
// lookup adapts rather than the naming.
$config = ($slot ?? null) !== null
    ? (array) ((config('assets.slots', [])[$slot]) ?? [])
    : [];

$ratio = $ratio ?? ($config['ratio'] ?? '16x9');
$label = $label ?? ($config['label'] ?? 'Media');
$src   = $src   ?? ($config['src']   ?? null);
$alt   = $alt   ?? ($config['alt']   ?? '');
$video = $video ?? ($config['video'] ?? null);
$meta  = $meta  ?? ($config['meta']  ?? null);
$play  = $play  ?? ($video !== null);

$caption = $caption ?? null;
$url     = site_media($src);

// Dimensions matched to the ratio so the browser reserves the correct box
// before the image arrives. Without these the page reflows as media loads,
// which is the largest single contributor to a poor CLS score.
$dimensions = [
    '21x9' => [1680, 720],
    '16x9' => [1600, 900],
    '4x3'  => [1200, 900],
    '1x1'  => [1000, 1000],
    '4x5'  => [1000, 1250],
];
[$w, $h] = $dimensions[$ratio] ?? $dimensions['16x9'];

/*
 * An image with no alt text is worse than a placeholder: a screen reader
 * announces the filename, and the picture is unlabelled for everyone who
 * cannot see it. So a src without an alt falls back to the empty state and
 * says so in the page source, where whoever added the image will find it.
 *
 * The database enforces the same rule from Phase 3 — media.alt_text is NOT NULL.
 */
$missingAlt = $url !== null && trim($alt) === '';
if ($missingAlt) {
    $url = null;
}
?>
<?php if ($missingAlt): ?>
<!-- 3AM: image for slot "<?= e($slot ?? '?') ?>" is set but has no alt text.
     Add 'alt' => '...' in config/assets.php and the image will appear. -->
<?php endif ?>
<figure class="frame frame--<?= e_attr($ratio) ?><?= $url ? ' is-filled' : '' ?>">

  <?php if ($url !== null): ?>
    <img src="<?= e_attr($url) ?>"
         alt="<?= e_attr($alt) ?>"
         width="<?= e_attr($w) ?>" height="<?= e_attr($h) ?>"
         loading="lazy" decoding="async">
  <?php else: ?>
    <?php /* Empty state. aria-hidden because it describes a slot, not
             content — a screen reader announcing "16:9 Showreel" as though
             it were a picture would be misleading. */ ?>
    <span class="frame__ph" aria-hidden="true">
      <span class="frame__ph-mark"></span>
      <span class="mono frame__ph-label"><?= e($label) ?></span>
      <span class="mono frame__ph-ratio"><?= e(str_replace('x', ':', $ratio)) ?></span>
    </span>
  <?php endif ?>

  <?php if ($play): ?>
    <?php if ($video !== null): ?>
      <?php /* Facade load: a link, not an embedded player. The iframe costs
               500KB-1MB and is only worth paying once someone chooses to
               watch. Opens the video until the in-page lightbox lands. */ ?>
      <a class="frame__play" href="<?= e_attr($video) ?>"
         target="_blank" rel="noopener noreferrer">
        <span class="frame__play-tri" aria-hidden="true"></span>
        <span class="sr-only">Play <?= e($label) ?> (opens in a new tab)</span>
      </a>
    <?php else: ?>
      <span class="frame__play" aria-hidden="true">
        <span class="frame__play-tri"></span>
      </span>
    <?php endif ?>
  <?php endif ?>

  <?php if ($meta !== null): ?>
    <span class="mono frame__meta" aria-hidden="true"><?= e($meta) ?></span>
  <?php endif ?>

  <span class="frame__marks" aria-hidden="true"></span>

  <?php if ($caption !== null): ?>
    <figcaption class="mono frame__caption"><?= e($caption) ?></figcaption>
  <?php endif ?>

</figure>
