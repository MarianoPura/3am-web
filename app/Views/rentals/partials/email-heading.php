<div class="rentals-admin-page__heading"><div><p class="rentals-kicker">3AM Rentals / Administration</p><h1>Email notifications</h1></div></div>
<nav class="rentals-email-tabs" aria-label="Email notification sections">
<?php foreach (['templates'=>'Templates','recipients'=>'Recipients','history'=>'Delivery history'] as $key=>$label): ?>
<a href="<?= e_attr(url('rentals/admin/email'.($key==='templates'?'':'/'.$key))) ?>"<?= ($tab??'templates')===$key?' aria-current="page"':'' ?>><?= e($label) ?></a>
<?php endforeach ?></nav>
<p class="rentals-email-status">SMTP configured: <strong><?= $mailerStatus['smtp']?'YES':'NO' ?></strong> · Sender configured: <strong><?= $mailerStatus['sender']?'YES':'NO' ?></strong><?php if (!$mailerStatus['smtp']): ?> · Email service is not configured on this server.<?php endif ?></p>
<?php if (is_string($notice??null)): ?><p class="rentals-admin__notice" role="status"><?= e($notice) ?></p><?php endif ?>
<?php if (is_string($error??null)): ?><p class="rentals-admin__notice" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if (is_string($success??null)): ?><p class="rentals-admin__notice" role="status"><?= e($success) ?></p><?php endif ?>
