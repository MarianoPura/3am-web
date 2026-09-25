<?php
$imagePath = \App\Models\RentalCatalog::imagePath($image_path ?? null);
?>
<?= $this->partial('partials.frame', [
    'src' => $imagePath,
    'alt' => (string) ($name ?? 'Rental equipment'),
    'label' => (string) ($name ?? 'Equipment'),
    'ratio' => $ratio ?? '4x3',
]) ?>
