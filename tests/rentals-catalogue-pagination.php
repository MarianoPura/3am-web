<?php
declare(strict_types=1);

// Uniquely named LOCAL fixtures in a transaction. No schema changes or mail.
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$_ENV['MAIL_ENABLED']='false';
$container=require dirname(__DIR__).'/bootstrap.php';
$settings=config('database.connections.mysql');
if (!in_array(config('app.env'),['local','testing'],true) || $settings['host']!=='127.0.0.1'
    || (int)$settings['port']!==3306 || $settings['database']!=='d3am_rentals_new' || config('mail.enabled')) {
    throw new RuntimeException('Only the approved HOME PC database with mail disabled is allowed.');
}
$db=new App\Core\Database($settings,true);
$container->set(App\Core\Database::class,$db);
$assertions=0;
$check=static function(bool $ok,string $message) use (&$assertions):void {
    ++$assertions;
    if (!$ok) { throw new RuntimeException($message); }
};
$request=static function(array $query=[]):App\Core\Request {
    $_GET=$query; $_POST=[]; $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/rentals/items';
    return App\Core\Request::capture();
};
$before=[(int)$db->selectValue('SELECT COUNT(*) FROM rental_items'),(int)$db->selectValue('SELECT COUNT(*) FROM rental_categories'),(int)$db->selectValue('SELECT COUNT(*) FROM users')];
$key='pagination-'.bin2hex(random_bytes(6));
$model=new App\Models\RentalCatalog($db);
$db->beginTransaction();
try {
    $category=$db->insert('INSERT INTO rental_categories(name,slug,is_service) VALUES(?,?,0)',['[TEST] '.$key,$key]);
    $serviceCategory=$db->insert('INSERT INTO rental_categories(name,slug,is_service) VALUES(?,?,1)',['[TEST] Services '.$key,'service-'.$key]);
    $count=0; $ids=[];
    foreach ([0,1,12,13,100,1001] as $target) {
        while ($count<$target) {
            $name=sprintf('[TEST] %s %04d',$key,$count);
            $ids[]=$db->insert("INSERT INTO rental_items(category_id,name,slug,sku,rental_rate,security_deposit,rental_unit,available_quantity,availability_status) VALUES(?,?,?,?,25,5,'day',4,'available')",
                [$category,$name,$key.'-'.$count,'PG-'.$key.'-'.$count]);
            ++$count;
        }
        $result=$model->page(false,'',$key,1,12);
        $check(!$result['catalogUnavailable'],'Valid pagination was marked unavailable.');
        $check($result['pagination']['total']===$target,'Count query differs from matching population.');
        $check(count($result['items'])===min(12,$target),'Query returned more than the page limit.');
        $check($result['pagination']['pages']===max(1,(int)ceil($target/12)),'Page count is wrong.');
        $last=$model->page(false,'',$key,999999,12);
        $check($last['pagination']['page']===max(1,(int)ceil($target/12)),'Out-of-range page must clamp.');
    }
    $first=$model->page(false,'',$key,1,12);
    $second=$model->page(false,'',$key,2,12);
    $check(array_intersect(array_column($first['items'],'db_id'),array_column($second['items'],'db_id'))===[],'Adjacent pages repeat rows.');
    $check(array_column($first['items'],'db_id')===array_slice($ids,0,12),'Stable name/id ordering failed.');
    foreach ([-8,0,'','abc','1.5','1e3',[],str_repeat('9',50)] as $bad) {
        $check($model->page(false,'',$key,$bad)['pagination']['page']===1,'Invalid page input did not fall back.');
    }
    foreach ([-4,0,'abc',[],str_repeat('9',50)] as $bad) {
        $check($model->page(false,'',$key,1,$bad)['pagination']['perPage']===12,'Invalid size did not fall back.');
    }
    $check(count($model->page(false,'',$key,1,9999)['items'])===48,'Page size must have an enforced upper bound.');
    $check($model->page(false,'0005',$key)['pagination']['total']===1,'Database search/category intersection failed.');
    $check($model->page(false,"' OR 1=1 --",$key)['pagination']['total']===0,'Search changed SQL meaning.');
    $check($model->page(false,'%',$key)['pagination']['total']===0,'Literal percent became a wildcard.');
    $check($model->page(false,'_',$key)['pagination']['total']===0,'Literal underscore became a wildcard.');
    $check($model->page(false,'','unknown-'.$key)['pagination']['total']===0,'Unknown category bypassed filtering.');
    $service=$db->insert("INSERT INTO rental_items(category_id,name,slug,availability_status) VALUES(?,?,?,'inquire')",[$serviceCategory,'[TEST] Service '.$key,'request-'.$key]);
    $check($model->page(true,'','service-'.$key)['pagination']['total']===1,'Service-only category missing.');
    $check($model->page(false,'','service-'.$key)['pagination']['total']===0,'Services leaked into equipment.');
    $check($model->page(true,'',$key)['pagination']['total']===0,'Equipment leaked into services.');
    $check(!$model->isAvailable($model->find((string)$service),1,null,null),'Service permitted equipment reservation.');

    $db->update('UPDATE rental_items SET is_active=0 WHERE id=?',[$ids[999]]);
    $check($model->page(false,'',$key)['pagination']['total']===1000,'Inactive product remained public.');
    $check($model->find((string)$ids[999])===null,'Inactive product returned by active lookup.');
    $check($model->findMany([(string)$ids[999]],true)[(string)$ids[999]]['is_unavailable']===true,'Inactive cart row must remain removable.');
    $db->update('UPDATE rental_items SET slug=NULL WHERE id=?',[$ids[998]]);
    $check($model->find('item-'.$ids[998])['db_id']===$ids[998] && $model->find((string)$ids[998])['db_id']===$ids[998],'Slugless IDs differ between card/cart lookup.');
    $db->update('UPDATE rental_items SET slug=? WHERE id=?',['987654321',$ids[997]]);
    $check($model->find('987654321')['db_id']===$ids[997],'Numeric slug was confused with an ID.');
    $check($model->find($key.'-900')['db_id']===$ids[900],'Lookup cannot reach a product beyond the first page.');
    $check(count($model->load()['items'])<=12 && count($model->load()['services'])<=12 && count($model->load()['categories'])<=6,'Homepage preview is unbounded.');
    $categories=$model->categoryPage();
    $counts=array_column($categories['categories'],'item_count','db_id');
    $check((int)($counts[$category]??0)===1000,'Category count is based on preview rows.');

    // Independent cart/checkout batch retrieval for equipment outside the preview.
    $_SESSION=['pagination_cart'=>[['item_id'=>$key.'-900','quantity'=>2,'rental_start_date'=>date('Y-m-d',strtotime('+3 days')),'rental_end_date'=>date('Y-m-d',strtotime('+4 days'))]]];
    $cart=new App\Services\RentalCart('pagination_cart');
    $summary=(new App\Services\RentalCheckout($db,$cart))->summary();
    $check(count($summary['items'])===1 && $summary['subtotal']===100.0 && $summary['security_deposit']===10.0,'Off-page checkout changed inclusive days/deposit arithmetic.');
    $db->update('UPDATE rental_categories SET is_active=0 WHERE id=?',[$category]);
    $check($model->page(false,'',$key)['pagination']['total']===0 && $model->find($key.'-900')===null,'Inactive category leaked into public/checkout.');
    $check($cart->items()[0]['is_unavailable']===true,'Inactive category cart entry cannot be removed.');
    $db->update('UPDATE rental_categories SET is_active=1 WHERE id=?',[$category]);

    $password=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
    $admin=$db->insert("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin')",['[TEST] '.$key,$key.'@example.test',$password]);
    $_SESSION=['user_id'=>$admin,'rentals_password_stamp'=>hash('sha256',$password)];
    $controller=new App\Controllers\Rentals\RentalAdminController($container);
    $html=$controller->items($request(['q'=>$key,'category'=>$category,'type'=>'equipment','page'=>2]))->body();
    $check(str_contains($html,'Page 2 of 41') && substr_count($html,'<tr>')===26,'Admin backend pagination/filter intersection failed.');
    $check(!str_contains($html,'request-'.$key),'Admin equipment filter includes services.');
    $extraCategories=[];
    for ($i=0;$i<27;++$i) {
        $extraCategory=$db->insert('INSERT INTO rental_categories(name,slug,is_service) VALUES(?,?,0)',['[TEST] Category '.$key.' '.$i,'cat-'.$key.'-'.$i]);
        $extraCategories[]=$extraCategory;
        $db->insert('INSERT INTO rental_items(category_id,name,slug) VALUES(?,?,?)',[$extraCategory,'[TEST] Category item '.$i,'cat-item-'.$key.'-'.$i]);
    }
    $categoryFirst=$model->categoryPage(1,12);
    $categorySecond=$model->categoryPage(2,12);
    $check(count($categoryFirst['categories'])===12 && count($categorySecond['categories'])===12,'Public category page is unbounded or truncated.');
    $check(array_intersect(array_column($categoryFirst['categories'],'db_id'),array_column($categorySecond['categories'],'db_id'))===[],'Public category pages repeat rows.');
    $html=$controller->categories($request(['q'=>$key,'page'=>2]))->body();
    $check(str_contains($html,'Page 2 of 2') && str_contains($html,'of 29') && substr_count($html,'<tr>')===5,'Admin category pagination/search/count failed.');
    $public=new App\Controllers\Rentals\RentalsController($container);
    $html=$public->items($request(['q'=>'000','category'=>$key,'page'=>1,'per_page'=>12]))->body();
    $check(str_contains($html,'data-rentals-server-search') && str_contains($html,'name="q" value="000"'),'Public search is not a server form.');
    $check(str_contains($html,'q=000&amp;category=') && !str_contains($html,'<title><nav'),'Filter links lost search or corrupted metadata.');
    $sidebar=$container->get(App\Core\View::class)->partial('rentals.partials.catalogue-categories',['categories'=>[['id'=>'test','name'=>'Test']], 'serverCatalogue'=>true,'search'=>'0','selectedCategory'=>'test','pageSize'=>24]);
    $check(str_contains($sidebar,'q=0') && str_contains($sidebar,'per_page=24') && !preg_match('/(?:\?|&amp;)page=/',$sidebar),'Category change dropped search/size or retained page.');

    $from=' FROM rental_items i JOIN rental_categories c ON c.id=i.category_id WHERE i.is_active=1 AND c.is_active=1 AND c.is_service=0 AND i.category_id=? ORDER BY i.name,i.id';
    $start=hrtime(true); $old=$db->select('SELECT i.*'.$from,[$category]); $oldMs=(hrtime(true)-$start)/1e6;
    $start=hrtime(true); $new=$db->select('SELECT i.id,i.name,i.slug,i.rental_rate'.$from.' LIMIT 12 OFFSET 0',[$category]); $newMs=(hrtime(true)-$start)/1e6;
    $plans=$db->select('EXPLAIN SELECT i.id,i.name'.$from.' LIMIT 12',[$category]);
    $metrics=['population'=>count($old),'oldRows'=>count($old),'newRows'=>count($new),'oldPayloadBytes'=>strlen(json_encode($old)),'newPayloadBytes'=>strlen(json_encode($new)),
        'oldFetchMs'=>round($oldMs,3),'limitedFetchMs'=>round($newMs,3),'plan'=>array_map(static fn(array $row):array=>array_intersect_key($row,array_flip(['table','type','key','rows','Extra'])),$plans)];
    $check(count($old)===1000 && count($new)===12,'Benchmark population differs.');
    echo 'METRICS: '.json_encode($metrics,JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    $db->rollBack(); $_SESSION=[]; $_GET=[];
}
$after=[(int)$db->selectValue('SELECT COUNT(*) FROM rental_items'),(int)$db->selectValue('SELECT COUNT(*) FROM rental_categories'),(int)$db->selectValue('SELECT COUNT(*) FROM users')];
$check($before===$after,'Fixture rollback did not preserve row counts.');
echo "PASS: $assertions assertions; 0/1/12/13/100/1001 products, SQL filters/counts/limits, invalid inputs, separate types, slug aliases, inactive cart removal, off-page checkout, admin pages, preserved links and rollback.\n";
