<div class="rentals-status-qr" data-rentals-qr data-qr-url="<?= e_attr(absolute_url('rentals/order-status/' . $statusToken)) ?>">
  <div data-rentals-qr-image role="img" aria-label="QR code linking to this rental status page"></div>
  <button type="button" class="rentals-btn rentals-btn--dark" data-rentals-qr-download>Download QR</button>
  <p><a href="<?= e_attr(url('rentals/order-status/' . $statusToken)) ?>">Open status link</a></p>
</div>
