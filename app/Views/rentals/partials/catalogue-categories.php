<?php
$sidebarCategories=array_values(array_filter(is_array($categories??null)?$categories:[],static fn(array $category):bool=>($category['id']??'')!=='' && ($category['name']??'')!==''));
$initialCategories=array_slice($sidebarCategories,0,6);
$extraCategories=array_slice($sidebarCategories,6);
?>
<aside class="rentals-catalogue-categories" aria-labelledby="rentals-categories-title">
  <h2 id="rentals-categories-title">Product Categories</h2>
  <nav aria-label="Product categories">
    <button class="rentals-filter is-active" type="button" data-rentals-filter="all" aria-pressed="true">All categories</button>
    <?php foreach($initialCategories as $category): ?>
      <button class="rentals-filter" type="button" data-rentals-filter="<?= e_attr((string)$category['id']) ?>" aria-pressed="false"><?= e((string)$category['name']) ?></button>
    <?php endforeach ?>
    <?php if($extraCategories!==[]): ?>
      <details class="rentals-catalogue-categories__more" data-rentals-category-more>
        <summary class="rentals-filter"><span class="rentals-catalogue-categories__show-more">Show More</span><span class="rentals-catalogue-categories__show-less">Show Less</span></summary>
        <div class="rentals-catalogue-categories__extra">
          <?php foreach($extraCategories as $category): ?>
            <button class="rentals-filter" type="button" data-rentals-filter="<?= e_attr((string)$category['id']) ?>" aria-pressed="false"><?= e((string)$category['name']) ?></button>
          <?php endforeach ?>
        </div>
      </details>
    <?php endif ?>
    <?php if($sidebarCategories===[]): ?><p>No categories available.</p><?php endif ?>
  </nav>
</aside>
