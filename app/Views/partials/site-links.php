<?php
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
foreach (config('navigation', []) as $link):
    $href = url($link['path']);
    $isCurrent = $currentPath === rtrim($href, '/')
        || ($link['path'] === '/' && $currentPath === rtrim($href, '/') . '/index.php');
?>
  <a href="<?= e_attr($href) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
<?php endforeach ?>
