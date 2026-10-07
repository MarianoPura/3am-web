<?php
$galleryItem=$galleryItem??[];
$galleryReady=(bool)($galleryReady??false);
$galleryPaths=\App\Services\RentalGallery::paths($galleryItem['additional_image_paths']??null);
$mainImage=\App\Models\RentalCatalog::imagePath($galleryItem['image_path']??null);
$imageLimit=\App\Services\RentalManagedImage::maxUploadBytes();
?>
<fieldset class="rentals-admin-gallery">
  <legend>Attach images</legend>
  <p>Choose one main image for the product card, then attach additional images for the gallery.</p>
  <div class="rentals-admin-gallery__main">
    <h3>Main image</h3>
    <?php if($mainImage!==null): ?><img class="rentals-admin__image-preview" src="<?= e_attr(\App\Models\RentalCatalog::imageUrl($mainImage)) ?>" alt="Current main image"><?php endif ?>
    <label><?= !empty($galleryItem['image_path'])?'Replace main image (optional)':'Upload main image (optional)' ?>
      <input type="file" name="product_image" accept="image/jpeg,image/png,image/webp" data-product-image-limit="<?= e_attr((string)$imageLimit) ?>">
      <small>This image appears first. JPG, PNG or WebP; up to <?= e(number_format($imageLimit/1048576,1)) ?> MB.</small>
    </label>
    <?php if(!empty($galleryItem['image_path'])): ?><label><input type="checkbox" name="remove_primary_image" value="1"> Remove main image when saving</label><?php endif ?>
  </div>
  <div class="rentals-admin-gallery__additional">
    <h3>Additional images</h3>
  <?php if (!$galleryReady): ?><p>Additional image uploads are currently unavailable. You can still upload or replace the main image.</p><?php else: ?>
    <p>Attach up to <?= \App\Services\RentalGallery::MAX_ADDITIONAL ?> additional images. Select multiple files together; check a saved thumbnail to remove it when saving.</p>
    <div class="rentals-admin-gallery__images">
      <?php foreach($galleryPaths as $index=>$path): ?>
        <label><img src="<?= e_attr(url('rentals/product-image/'.basename($path))) ?>" alt="Additional product image <?= $index+1 ?>" loading="lazy"><span><input type="checkbox" name="remove_gallery[]" value="<?= $index ?>"> Remove image <?= $index+1 ?></span></label>
      <?php endforeach ?>
    </div>
    <label>Attach multiple additional images<input type="file" name="additional_images[]" multiple accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP; each file up to <?= e(number_format($imageLimit/1048576,1)) ?> MB. <?= count($galleryPaths) ?> / <?= \App\Services\RentalGallery::MAX_ADDITIONAL ?> additional images saved. The server's total form-upload limit also applies.</small></label>
  <?php endif ?>
  </div>
</fieldset>
