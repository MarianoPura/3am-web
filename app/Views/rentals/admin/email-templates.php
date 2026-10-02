<?php $this->extend('rentals.layouts.admin'); $this->start('title'); ?>Email Notifications — Rentals Admin<?php $this->end(); $this->start('content'); ?>
<section class="rentals-admin-page rentals-email"><div class="rentals-shell">
<?= $this->partial('rentals.partials.email-heading',compact('mailerStatus','notice')+['tab'=>'templates','error'=>$error??null]) ?>
<p class="rentals-admin__explain">Edit customer confirmations and company notifications. Internal emails go only to active subscribers.</p>
<p class="rentals-email-help">Customers receive one combined request/payment review email. Edit Payment confirmed for approval and Payment proof needs attention for rejection. Internal recipients subscribed to both review events receive one email.</p>
<div class="rentals-email-grid">
<?php foreach ($templates as $template): ?><article class="rentals-admin__panel rentals-email-card">
<p class="rentals-card__meta"><?= e($template['audience']==='admin'?'Owner / Admin':'Customer') ?> · <?= (int)$template['is_active']?'Enabled':'Disabled' ?></p>
<?php if ($template['audience']==='customer' && in_array($template['event_key'],[\App\Services\RentalMailEvents::RENTAL_APPROVED,\App\Services\RentalMailEvents::RENTAL_REJECTED],true)): ?><p class="rentals-email-help">Reserved for a future separate rental review. Current customer reviews use the combined payment template.</p><?php endif ?>
<h2><?= e($template['display_name']) ?></h2><p><?= e($template['subject']) ?></p>
<a class="rentals-btn rentals-btn--outline" href="<?= e_attr(url('rentals/admin/email/templates/'.$template['event_key'].'/'.$template['audience'])) ?>">Edit template ↗</a>
</article><?php endforeach ?></div></div></section><?php $this->end(); ?>
