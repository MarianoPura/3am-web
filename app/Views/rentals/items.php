<?php
$isServiceCatalogue=(bool)($isServiceCatalogue??false);
$serverCatalogue=(bool)($serverCatalogue??false);
$search=(string)($search??'');
$selectedCategory=(string)($selectedCategory??'');
$cataloguePath=(string)($cataloguePath??'rentals/items');
$pageSize=(int)($pagination['perPage']??12);
$filteredEmpty=$serverCatalogue && empty($catalogUnavailable) && ($pagination['total']??0)===0 && ($search!=='' || ($selectedCategory!=='' && $selectedCategory!=='all'));
$itemsList=array_values(array_filter(is_array($items??null)?$items:[],static fn(array $item):bool=>(int)($item['is_service']??0)===0));
$servicesList=array_values(array_filter(is_array($services??null)?$services:[],static fn(array $item):bool=>(int)($item['is_service']??0)===1));
$activeRecords=$isServiceCatalogue?$servicesList:$itemsList;
$categoriesList=is_array($categories??null)?$categories:[];
if($isServiceCatalogue && !$serverCatalogue) {
    // Service-only categories must not depend on equipment category visibility.
    $serviceCategories=[];
    foreach($servicesList as $service) {
        $key=(string)($service['category']??'');
        if($key!=='') { $serviceCategories[$key]=['id'=>$key,'name'=>(string)($service['category_name']??'Services')]; }
    }
    $categoriesList=array_values($serviceCategories);
}
$notice=$_SESSION['rentals_notice']??null;unset($_SESSION['rentals_notice']);
$this->extend('rentals.layouts.base');
?>
<?php $this->start('title') ?><?= $isServiceCatalogue?'Production Services':'Rental Equipment' ?> — 3AM<?php $this->end() ?>
<?php $this->start('canonical') ?><?= e_attr(absolute_url('rentals/items').($isServiceCatalogue?'?type=services':'')) ?><?php $this->end() ?>
<?php $this->start('description') ?><?= $isServiceCatalogue?'Event, crew and production support services through 3AM Rentals.':'Browse production equipment available through 3AM Rentals.' ?><?php $this->end() ?>
<?php $this->start('content') ?>
<section class="rentals-section rentals-section--catalogue" aria-labelledby="rentals-catalogue-title">
  <div class="rentals-shell">
    <h1 class="sr-only" id="rentals-catalogue-title">Equipment &amp; Services — <?= $isServiceCatalogue?'Services':'Equipment' ?></h1>
    <?php if(is_string($notice)): ?><p class="rentals-support-panel" role="alert"><?= e($notice) ?></p><?php endif ?>
    <div class="rentals-toolbar" aria-label="<?= $isServiceCatalogue?'Services':'Equipment' ?> catalogue controls">
      <div class="rentals-catalogue-search">
        <form method="get" action="<?= e_attr(url($cataloguePath)) ?>" class="rentals-catalogue-search__form"<?= $serverCatalogue?' data-rentals-server-search':'' ?>>
        <?php if($isServiceCatalogue): ?><input type="hidden" name="type" value="services"><?php endif ?>
        <?php if($selectedCategory!=='' && $selectedCategory!=='all'): ?><input type="hidden" name="category" value="<?= e_attr($selectedCategory) ?>"><?php endif ?>
        <label class="rentals-toolbar__search">
          <span class="sr-only">Search <?= $isServiceCatalogue?'services':'equipment' ?></span>
          <svg class="rentals-catalogue-search__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
          <input type="search" name="q" value="<?= e_attr($search) ?>" maxlength="190" placeholder="Search <?= $isServiceCatalogue?'services':'equipment' ?>…" data-rentals-search>
        </label>
        <?php if($serverCatalogue): ?>
          <label class="rentals-catalogue-page-size"><span class="sr-only">Products per page</span><select name="per_page" aria-label="Products per page"><?php foreach(array_unique([12,24,48,$pageSize]) as $size): ?><option value="<?= $size ?>"<?= $pageSize===$size?' selected':'' ?>><?= $size ?> per page</option><?php endforeach ?></select></label>
          <button class="rentals-btn rentals-btn--dark" type="submit">Search</button>
        <?php endif ?>
        </form>
        <nav class="rentals-catalogue-choice" aria-label="Browse rental type">
          <a href="<?= e_attr(url('rentals/items')) ?>"<?= !$isServiceCatalogue?' aria-current="page"':'' ?>>Equipment <span aria-label="<?= (int)($catalogueTotals[0]??count($itemsList)) ?> listed"><?= (int)($catalogueTotals[0]??count($itemsList)) ?></span></a>
          <a href="<?= e_attr(url('rentals/items').'?type=services') ?>"<?= $isServiceCatalogue?' aria-current="page"':'' ?>>Services <span aria-label="<?= (int)($catalogueTotals[1]??count($servicesList)) ?> listed"><?= (int)($catalogueTotals[1]??count($servicesList)) ?></span></a>
        </nav>
      </div>
    </div>
    <div class="rentals-catalogue-layout">
      <?= $this->partial('rentals.partials.catalogue-categories',['categories'=>$categoriesList,'serverCatalogue'=>$serverCatalogue,'selectedCategory'=>$selectedCategory,'search'=>$search,'isServiceCatalogue'=>$isServiceCatalogue,'pageSize'=>$pageSize,'cataloguePath'=>$cataloguePath]) ?>
      <div class="rentals-catalogue-layout__content">
        <?php if($activeRecords!==[]): ?><p class="rentals-results" data-rentals-results data-rentals-result-noun="<?= $isServiceCatalogue?'service':'item' ?>" role="status" aria-live="polite"><?= $serverCatalogue?e((string)($pagination['total']??0).' '.($isServiceCatalogue?'services':'items').' found'):'' ?></p><?php endif ?>
        <?php if($isServiceCatalogue): ?>
          <?= $this->partial('rentals.partials.service-catalogue',['services'=>$servicesList,'catalogUnavailable'=>!empty($catalogUnavailable),'filteredEmpty'=>$filteredEmpty]) ?>
        <?php else: ?>
          <?= $this->partial('rentals.partials.equipment-catalogue',['items'=>$itemsList,'categories'=>$categoriesList,'catalogUnavailable'=>!empty($catalogUnavailable),'filteredEmpty'=>$filteredEmpty]) ?>
        <?php endif ?>
        <?= $this->partial('rentals.partials.pagination',['pagination'=>$pagination??null,'pagePath'=>$cataloguePath]) ?>
      </div>
    </div>
  </div>
</section>
<?php $this->end() ?>
