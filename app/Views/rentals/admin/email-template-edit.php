<?php $this->extend('rentals.layouts.admin'); $this->start('title'); ?>Edit Email Template — Rentals Admin<?php $this->end(); $this->start('content'); ?>
<section class="rentals-admin-page rentals-email"><div class="rentals-shell">
<?= $this->partial('rentals.partials.email-heading',['tab'=>'templates','mailerStatus'=>$mailerStatus,'notice'=>$notice,'error'=>$error??null,'success'=>$success??null]) ?>
<a class="rentals-admin__back-link" href="<?= e_attr(url('rentals/admin/email')) ?>">← All templates</a>
<?php if ($template['audience']==='customer' && in_array($template['event_key'],[\App\Services\RentalMailEvents::RENTAL_APPROVED,\App\Services\RentalMailEvents::RENTAL_REJECTED],true)): ?>
<p class="rentals-admin__notice">This customer template is reserved for future separate rental review. Current reviews use the <a href="<?= e_attr(url('rentals/admin/email/templates/'.($template['event_key']===\App\Services\RentalMailEvents::RENTAL_APPROVED?'payment_approved':'payment_rejected').'/customer')) ?>">combined request/payment template</a>.</p>
<?php endif ?>
<div class="rentals-email-editor"><div class="rentals-admin__editor">
<div class="rentals-admin__editor-head"><p class="rentals-card__meta"><?= e($template['event_key']) ?> · <?= e($template['audience']) ?></p><h2>Edit template</h2></div>
<form class="rentals-admin__form" method="post" action="<?= e_attr(url('rentals/admin/email/templates/'.$template['event_key'].'/'.$template['audience'])) ?>">
<?= csrf_field() ?><label>Template name<input name="display_name" maxlength="150" required value="<?= e_attr($template['display_name']) ?>"></label>
<label>Notification<select name="is_active"><option value="1"<?= (int)$template['is_active']===1?' selected':'' ?>>Enabled</option><option value="0"<?= (int)$template['is_active']===0?' selected':'' ?>>Disabled</option></select></label>
<label>Subject<input name="subject" maxlength="200" required value="<?= e_attr($template['subject']) ?>"></label>
<label>Message<textarea name="body" rows="16" maxlength="20000" required><?= e($template['body']) ?></textarea></label>
<div class="rentals-email-actions"><button class="rentals-btn rentals-btn--primary" name="action" value="save">Save template</button><button class="rentals-btn rentals-btn--outline" name="action" value="preview">Preview sample</button></div>
<details class="rentals-email-help"><summary>Reset or send a test email</summary><p>Preview and tests use sample data. Sending a test does not save template edits or change an order.</p>
<label>Test recipient email<input type="email" name="test_email" maxlength="190" value="<?= e_attr($adminUser['email']??'') ?>"></label>
<div class="rentals-email-actions"><button class="rentals-btn rentals-btn--outline" name="action" value="test">Send test email</button><button class="rentals-btn rentals-btn--outline" name="action" value="reset" formnovalidate>Reset to default</button></div><p>Reset replaces this saved subject, message, name and enabled setting with the default.</p></details>
</form></div><aside class="rentals-admin__panel rentals-email-variables"><h2>Available variables</h2><p>Messages are plain text. Variables are substituted safely; code and HTML are never executed.</p>
<?php foreach (\App\Services\RentalMailEvents::placeholders($template['audience']) as $variable): ?><code>{{<?= e($variable) ?>}}</code><?php endforeach ?>
<p>Each item includes its quantity, dates and line total. Internal order notes are never included. A customer-visible rejection reason is currently unavailable.</p>
<p>Last saved: <?= e($template['updated_at']??'Default template') ?></p></aside></div>
<?php if (isset($preview)): ?><section class="rentals-admin__panel"><h2>Sample preview</h2><p><strong><?= e($preview['subject']) ?></strong></p><div class="rentals-email-preview"><?= $preview['fragment'] ?></div><details><summary>Plain text version</summary><pre class="rentals-email-text"><?= e($preview['text']) ?></pre></details></section><?php endif ?>
</div></section><?php $this->end(); ?>
