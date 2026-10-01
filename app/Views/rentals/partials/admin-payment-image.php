<?php if (!empty($payment['qr_image_path']) && \App\Services\RentalManagedImage::publicPath($payment['qr_image_path'], 'qr') !== null): ?>
  <img class="rentals-admin__image-preview" src="<?= e_attr(url('rentals/payment-qr/' . (int) $payment['id'])) ?>" alt="Current payment QR code">
<?php endif ?>
<label>Payment QR image (optional)
  <input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp">
  <small>JPG, PNG or WebP. Maximum <?= e(number_format(\App\Services\RentalManagedImage::maxUploadBytes() / 1048576, 2)) ?> MB. Leave blank to keep the existing image.</small>
</label>
