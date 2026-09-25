<?php
/**
 * Base layout.
 *
 * ESCAPING: every variable below goes through e() or e_attr(). There is no
 * auto-escaping in this template layer — see App\Core\View.
 *
 * @var App\Core\View $this
 * @var array $site
 */

$company   = config('app.company');
$pageTitle = $this->section('title') ?: config('app.name');
$pageDesc  = $this->section('description')
    ?: 'Media production, technology and event systems. Quezon City, Philippines.';
?>
<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e_attr($pageDesc) ?>">

<?php /* Canonical, Open Graph and JSON-LD are the only places that require a
         fully-qualified URL — a relative canonical is invalid, and a relative
         og:url will not resolve when a crawler or a chat client fetches it.
         Everything else on the page uses root-relative url(). */ ?>
<link rel="canonical" href="<?= e_attr($this->section('canonical') ?: absolute_url('/')) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e_attr(config('app.brand')) ?>">
<meta property="og:title" content="<?= e_attr($pageTitle) ?>">
<meta property="og:description" content="<?= e_attr($pageDesc) ?>">
<meta property="og:url" content="<?= e_attr($this->section('canonical') ?: absolute_url('/')) ?>">
<meta property="og:locale" content="en_PH">
<meta name="twitter:card" content="summary_large_image">

<meta name="theme-color" content="#0B1622">

<link rel="icon" href="<?= e_attr(url('favicon.svg')) ?>" type="image/svg+xml">

<?php /* Fonts are self-hosted in Phase 6 (subset WOFF2, <120KB budget).
         Google Fonts here is an interim measure for the Phase 1 shell only —
         it costs a third-party connection the performance budget cannot keep. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;800;900&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap">

<?php /* versioned() stamps each URL with the file's mtime, so an edit is live
         immediately instead of waiting out a browser cache. */ ?>
<link rel="stylesheet" href="<?= e_attr(versioned('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e_attr(versioned('css/app.css')) ?>">

<?php
/*
 * Runs synchronously before first paint, so animated elements start hidden
 * rather than painting and then flashing to invisible when the deferred GSAP
 * tweens initialise.
 *
 * The timeout is the safety net that makes this acceptable. Hiding content in
 * CSS and relying on JS to reveal it is normally how a motion-heavy site ends
 * up blank when a script fails — so if nothing has revealed anything within
 * 2.5s, the gate lifts and everything shows. Elements GSAP has already taken
 * over carry inline styles, which win over the class, so lifting the gate
 * cannot make un-revealed sections appear early.
 */
?>
<script nonce="<?= e_attr($nonce ?? '') ?>">
  document.documentElement.classList.add('js-motion');
  setTimeout(function () {
    document.documentElement.classList.remove('js-motion');
  }, 2500);
</script>

<?= $this->section('head') ?>

<?php /* Organization schema. LocalBusiness with surveyed coordinates lands in
         Phase 7 — publishing approximate geo data is worse than publishing none. */ ?>
<script type="application/ld+json">
<?= e_js([
    '@context'      => 'https://schema.org',
    '@type'         => 'Organization',
    'name'          => $company['legal_name'],
    'alternateName' => $company['brand'],
    'url'           => absolute_url('/'),
    'foundingDate'  => (string) $company['founded'],
    'address'       => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $company['address']['street'],
        'addressLocality' => $company['address']['locality'],
        'addressRegion'   => $company['address']['region'],
        'addressCountry'  => $company['address']['country'],
    ],
]) ?>
</script>
</head>

<body>
<a class="skip-link" href="#main">Skip to content</a>

<?php $minimalChrome = $this->section('chrome') === 'minimal'; ?>
<?php if ($minimalChrome): ?>
<?= $this->partial('partials.nav-minimal', ['cta' => $this->section('chrome-cta')]) ?>
<?php else: ?>
<?= $this->partial('partials.nav') ?>
<?php endif ?>

<main id="main">
<?= $this->section('content') ?>
</main>

<?php if ($minimalChrome): ?>
<footer class="footer footer--minimal">
  <div class="shell">
    <p class="mono"><?= e($company['legal_name']) ?></p>
    <p class="footer__line">
      <?= e($company['address']['street']) ?>, <?= e($company['address']['locality']) ?>, <?= e($company['address']['region']) ?>
      &middot; <a href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
    </p>
  </div>
</footer>
<?php else: ?>
<footer class="footer">
  <div class="shell">
    <div class="footer__grid">
    <div>
    <p class="mono"><?= e($company['legal_name']) ?></p>
    <?php if ($this->section('address-in-content') !== 'yes'): ?>
    <p style="margin-top:var(--s-2);color:var(--fg-muted)">
      <?= e($company['address']['street']) ?><br>
      <?= e($company['address']['locality']) ?>, <?= e($company['address']['region']) ?>
    </p>
    <?php endif ?>
    <a class="footer__email" href="mailto:<?= e_attr(config('app.contact_email')) ?>"><?= e(config('app.contact_email')) ?></a>
    </div>
    <nav class="footer__nav" aria-label="Footer"><?= $this->partial('partials.site-links') ?></nav>
    </div>

    <div class="footer__status">
      <span class="mono">&copy; <?= e(date('Y')) ?> <?= e($company['legal_name']) ?></span>
    </div>
  </div>
</footer>
<?php endif ?>

<?php
/*
 * GSAP from CDN, pinned. Both tags carry the CSP nonce — the policy uses
 * 'strict-dynamic', so an allowlisted host is not enough on its own.
 *
 * Deferred, and app.js degrades to CSS-only reveals if GSAP fails to load.
 * A blocked CDN must never leave the page invisible, which is exactly what
 * happens when content starts at opacity:0 and only JS can reveal it.
 */
$nonce = $nonce ?? '';   // shared by the front controller from SecureHeaders
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
        nonce="<?= e_attr($nonce) ?>" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"
        nonce="<?= e_attr($nonce) ?>" defer></script>
<script src="<?= e_attr(versioned('js/app.js')) ?>" nonce="<?= e_attr($nonce) ?>" defer></script>

<?= $this->section('scripts') ?>
</body>
</html>
