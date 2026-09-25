<?php
$statusValue = (int) ($status ?? 0);
$statusName = \App\Services\RentalPaymentStatus::label($statusValue);
$statusClass = strtolower($statusName);
?>
<span class="rentals-payment-badge rentals-payment-badge--<?= e_attr($statusClass) ?>"><?= e($statusName) ?></span>
