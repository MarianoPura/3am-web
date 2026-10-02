<?php /* Fixed, escaped transactional email content. */ ?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="padding:24px 12px">
<table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="max-width:620px;margin:auto;background:#fff">
<tr><td style="padding:24px;background:#0b1622;color:#fff;border-bottom:3px solid #ffb400"><strong style="font-size:24px">3AM</strong> <span style="color:#ffb400">RENTALS</span></td></tr>
<tr><td style="padding:28px 24px"><p style="margin:0 0 16px;font-size:12px;font-weight:bold;color:#526b86"><?= e($values['order_status']) ?> · <?= e($values['order_number']) ?></p>
<h1 style="font-size:24px;line-height:1.3;margin:0 0 24px"><?= e($subject) ?></h1>
<div style="font-size:16px;line-height:1.7;overflow-wrap:anywhere"><?= nl2br(e($message)) ?></div>
<p style="margin:28px 0 0"><a href="<?= e_attr($cta) ?>" style="display:inline-block;padding:14px 20px;background:#ffb400;color:#0b1622;text-decoration:none;font-weight:bold"><?= $audience === 'admin' ? 'Review in Admin' : 'View rental status' ?></a></p></td></tr>
<tr><td style="padding:20px 24px;border-top:1px solid #d0dceb;font-size:12px;line-height:1.6"><?= e($values['company_name']) ?><br><?= e($values['support_email']) ?></td></tr>
</table></td></tr></table>
