<?php /* Editable content is plain text; every value is escaped for HTML output. */ ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($subject) ?></title></head>
<body style="margin:0;background:#edf2f7;color:#132b45;font-family:Arial,sans-serif">
<?= $this->partial('rentals.emails.content',compact('subject','message','values','cta','audience')) ?>
</body></html>
