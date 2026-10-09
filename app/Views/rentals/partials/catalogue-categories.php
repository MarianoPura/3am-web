<?php
$sidebarCategories=array_values(array_filter(is_array($categories??null)?$categories:[],static fn(array $category):bool=>($category['id']??'')!=='' && ($category['name']??'')!==''));
$initialCategories=array_slice($sidebarCategories,0,6);
$extraCategories=array_slice($sidebarCategories,6);
$serverCatalogue=(bool)($serverCatalogue??false);
$selectedCategory=(string)($selectedCategory??'');
$search=(string)($search??'');
$isServiceCatalogue=(bool)($isServiceCatalogue??false);
$pageSize=(int)($pageSize??12);
$cataloguePath=(string)($cataloguePath??'rentals/items');
$categoryLink=static function(string $id) use($search,$isServiceCatalogue,$pageSize,$cataloguePath):string {
    $query=[];
    if($search!=='') { $query['q']=$search; }
    if(!empty($isServiceCatalogue)) { $query['type']='services'; }
    if(!empty($pageSize) && $pageSize!==12) { $query['per_page']=$pageSize; }
    if($id!=='all') { $query['category']=$id; }
    return url($cataloguePath??'rentals/items').($query!==[]?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
};
$filter=static function(string $id,string $name) use($serverCatalogue,$selectedCategory,$categoryLink):string {
    $active=$selectedCategory===$id || ($id==='all' && ($selectedCategory==='' || $selectedCategory==='all'));
    $common=' class="rentals-filter'.($active?' is-active':'').'" data-rentals-filter="'.e_attr($id).'"';
    return $serverCatalogue
        ? '<a'.$common.' href="'.e_attr($categoryLink($id)).'"'.($active?' aria-current="true"':'').'>'.e($name).'</a>'
        : '<button'.$common.' type="button" aria-pressed="'.($active?'true':'false').'">'.e($name).'</button>';
};
?>
<aside class="rentals-catalogue-categories" aria-labelledby="rentals-categories-title">
  <h2 id="rentals-categories-title">Product Categories</h2>
  <nav aria-label="Product categories">
    <?= $filter('all','All categories') ?>
    <?php foreach($initialCategories as $category): ?>
      <?= $filter((string)$category['id'],(string)$category['name']) ?>
    <?php endforeach ?>
    <?php if($extraCategories!==[]): ?>
      <details class="rentals-catalogue-categories__more" data-rentals-category-more<?= in_array($selectedCategory,array_column($extraCategories,'id'),true)?' open':'' ?>>
        <summary class="rentals-filter"><span class="rentals-catalogue-categories__show-more">Show More</span><span class="rentals-catalogue-categories__show-less">Show Less</span></summary>
        <div class="rentals-catalogue-categories__extra">
          <?php foreach($extraCategories as $category): ?>
            <?= $filter((string)$category['id'],(string)$category['name']) ?>
          <?php endforeach ?>
        </div>
      </details>
    <?php endif ?>
    <?php if($sidebarCategories===[]): ?><p>No categories available.</p><?php endif ?>
  </nav>
</aside>
