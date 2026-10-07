<p class="rentals-card__meta">Service quotation</p><h2><?= ($record['quote_amount']??null)!==null?'₱'.e(number_format((float)$record['quote_amount'],2)):'Awaiting quotation' ?></h2>
<?php if(!empty($record['quote_notes'])): ?><p class="rentals-service-request-text"><?= nl2br(e($record['quote_notes'])) ?></p><?php endif ?>
<?php if(($record['quote_amount']??null)!==null): ?>
  <p>Payment: <strong><?= (float)$record['quote_amount']===0.0?'No payment required':e(ucfirst($record['payment_status']??'unpaid')) ?></strong></p>
<?php endif ?>
<?php if(!empty($record['payment_method_name'])): ?><p>Payment method: <?= e($record['payment_method_name']) ?></p><?php endif ?>
<?php if(!empty($record['payment_reference'])): ?><p>Reference: <?= e($record['payment_reference']) ?></p><?php endif ?>
<?php if(!empty($record['payment_message'])): ?><div class="rentals-service-request-message"><strong>Payment review message</strong><p><?= nl2br(e($record['payment_message'])) ?></p></div><?php endif ?>
<?php if(!empty($record['payment_proof_path'])):
  $proofUrl=url('rentals/service-requests/'.(int)$record['id'].'/proof');
  $pdf=str_ends_with($record['payment_proof_path'],'.pdf')||str_ends_with($record['payment_proof_path'],'.pdf.php'); ?>
  <div class="rentals-service-proof"><a href="<?= e_attr($proofUrl) ?>" target="_blank" rel="noopener">View uploaded proof<?= $pdf?' (PDF)':'' ?> →</a>
    <?php if(!$pdf): ?><img src="<?= e_attr($proofUrl) ?>" alt="Submitted service payment proof" loading="lazy" data-service-proof-image><p role="status" data-service-proof-error hidden>Payment proof could not be opened. Contact support.</p><?php endif ?>
  </div>
<?php endif ?>
