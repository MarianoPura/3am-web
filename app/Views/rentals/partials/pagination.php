<?php
$pagination=$pagination??null;
if (!is_array($pagination) || ($pagination['total']??0)===0) { return; }
$query=[];
foreach($_GET as $key=>$value) { if(is_string($key) && is_scalar($value) && $key!=='page') {$query[$key]=$value;} }
$href=static fn(int $page):string=>url($pagePath??'rentals').'?'.http_build_query($query+['page'=>$page],'','&',PHP_QUERY_RFC3986);
$first=($pagination['page']-1)*$pagination['perPage']+1;
$last=min($pagination['total'],$first+$pagination['perPage']-1);
?>
<nav class="rentals-pagination" aria-label="Results pages">
  <span>Showing <?= $first ?>–<?= $last ?> of <?= (int)$pagination['total'] ?> · Page <?= (int)$pagination['page'] ?> of <?= (int)$pagination['pages'] ?></span>
  <div><?php if($pagination['page']>1): ?><a class="rentals-btn" href="<?= e_attr($href($pagination['page']-1)) ?>" rel="prev">← Previous</a><?php endif ?>
  <?php if($pagination['page']<$pagination['pages']): ?><a class="rentals-btn" href="<?= e_attr($href($pagination['page']+1)) ?>" rel="next">Next →</a><?php endif ?></div>
</nav>
