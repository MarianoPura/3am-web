<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$view=new App\Core\View(BASE_PATH.'/app/Views');
$assert=static function(bool $ok,string $message):void { if(!$ok) { throw new RuntimeException($message); } };
$categories=[];
for($i=1;$i<=9;$i++) { $categories[]=['id'=>'category-'.$i,'name'=>'Category '.$i]; }
$html=$view->partial('rentals.partials.catalogue-categories',['categories'=>$categories]);
[$initial,$extra]=explode('<details',$html,2);
$assert(substr_count($initial,'data-rentals-filter=')===7,'Initially show All plus six categories.');
$assert(substr_count($extra,'data-rentals-filter=')===3,'Additional categories must remain in the disclosure.');
$assert(str_contains($extra,'Show More')&&str_contains($extra,'Show Less')&&!preg_match('/^[^>]*\bopen(?:[\s=>])/', $extra),'Disclosure must start collapsed.');
$html=$view->partial('rentals.partials.catalogue-categories',['categories'=>array_slice($categories,0,6)]);
$assert(!str_contains($html,'<details'),'Do not show More when all categories fit.');
$html=$view->partial('rentals.partials.catalogue-categories',['categories'=>[['id'=>'quoted"slug','name'=>'<script>not markup</script>'],['id'=>'','name'=>'Invalid']]]);
$assert(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>')&&str_contains($html,'quoted&quot;slug')&&!str_contains($html,'>Invalid<'),'Escape database labels/IDs and omit invalid entries.');
$html=$view->partial('rentals.partials.catalogue-categories',['categories'=>[]]);
$assert(str_contains($html,'All categories')&&str_contains($html,'No categories available.')&&!str_contains($html,'<details'),'Empty categories must remain usable.');
echo "PASS: six-category limit, More/Less disclosure, complete category list, short/empty lists and escaped labels. No database changes.\n";
