<?php
$imagePath = \App\Models\RentalCatalog::imagePath($image_path ?? null);
$placeholder = site_media('media/rentals-equipment-placeholder.svg');
?>
<figure class="frame frame--<?= e_attr((string) ($ratio ?? '4x3')) ?> is-filled">
  <img src="<?= e_attr($imagePath !== null ? site_media($imagePath) : $placeholder) ?>"
       alt="<?= e_attr($imagePath !== null ? (string) ($name ?? 'Rental equipment') : '3AM Rentals equipment image coming soon') ?>"
       data-rentals-image data-rentals-fallback="<?= e_attr($placeholder) ?>"
       width="1200" height="900" loading="lazy" decoding="async">
  <span class="frame__marks" aria-hidden="true"></span>
</figure>
