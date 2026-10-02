<?php
$draft = $draft ?? ['id'=>0,'name'=>'','email'=>'','is_active'=>1,'events'=>[]];
foreach ($recipients as $row) { if ((int)$row['id'] === ($editId??0)) { $draft=$row; } }
$this->extend('rentals.layouts.admin'); $this->start('title'); ?>Email Recipients — Rentals Admin<?php $this->end(); $this->start('content'); ?>
<section class="rentals-admin-page rentals-email"><div class="rentals-shell">
<?= $this->partial('rentals.partials.email-heading',['tab'=>'recipients','mailerStatus'=>$mailerStatus,'notice'=>$notice,'error'=>$error??null]) ?>
<div class="rentals-email-editor"><div class="rentals-admin__editor"><div class="rentals-admin__editor-head"><h2><?= $draft['id']?'Edit recipient':'Add recipient' ?></h2></div>
<form class="rentals-admin__form" method="post" action="<?= e_attr(url('rentals/admin/email/recipients')) ?>"><?= csrf_field() ?>
<input type="hidden" name="id" value="<?= e_attr((string)$draft['id']) ?>"><label>Name<input name="name" maxlength="150" required value="<?= e_attr($draft['name']) ?>"></label>
<label>Email<input type="email" name="email" maxlength="190" required value="<?= e_attr($draft['email']) ?>"></label>
<label>Recipient status<select name="is_active"><option value="1"<?= (int)$draft['is_active']===1?' selected':'' ?>>Active</option><option value="0"<?= (int)$draft['is_active']===0?' selected':'' ?>>Disabled</option></select></label>
<fieldset class="rentals-email-subscriptions"><legend>Event subscriptions</legend><?php foreach ($events as $event=>$label): ?><label><input type="checkbox" name="events[]" value="<?= e_attr($event) ?>"<?= in_array($event,$draft['events'],true)?' checked':'' ?>><?= e($label) ?></label><?php endforeach ?></fieldset>
<button class="rentals-btn rentals-btn--primary">Save recipient</button><?php if ($draft['id']): ?><a href="<?= e_attr(url('rentals/admin/email/recipients')) ?>">Cancel edit</a><?php endif ?></form></div>
<div><p class="rentals-admin__explain">Recipients receive separate messages. Customer emails always use the order's saved customer email.</p>
<?php if (!$recipients): ?><div class="rentals-admin__panel"><h2>No internal recipients yet</h2><p>Add the Owner or team addresses and select their events.</p></div><?php endif ?>
<?php foreach ($recipients as $row): ?><article class="rentals-admin__panel"><p class="rentals-card__meta"><?= (int)$row['is_active']?'Active':'Disabled' ?></p><h2><?= e($row['name']) ?></h2><p><?= e($row['email']) ?></p><p><?= e(implode(' · ',array_map(static fn($event)=>$events[$event]??$event,$row['events']))) ?: 'No subscriptions' ?></p>
<a class="rentals-btn rentals-btn--outline" href="<?= e_attr(url('rentals/admin/email/recipients?edit='.$row['id'])) ?>">Edit</a>
<details class="rentals-email-help"><summary>Remove recipient</summary><form method="post" action="<?= e_attr(url('rentals/admin/email/recipients/'.$row['id'].'/remove')) ?>"><?= csrf_field() ?><p>This removes subscriptions and keeps delivery history.</p><button class="rentals-btn rentals-btn--outline" name="confirm" value="remove">Confirm removal</button></form></details>
</article><?php endforeach ?></div></div></div></section><?php $this->end(); ?>
