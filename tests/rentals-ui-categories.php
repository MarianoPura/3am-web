<?php
declare(strict_types=1);
// Isolated local category business-rule QA; no live access or permanent records.
$_ENV['MAIL_ENABLED']='false';$c=require dirname(__DIR__).'/bootstrap.php';
if(!in_array(config('app.env'),['local','testing'],true)){throw new RuntimeException('Local/testing only.');}
$db=$c->get(App\Core\Database::class);$key=bin2hex(random_bytes(6));$categories=[];$items=[];
$fullName='[TEST] UI '.$key.' Cameras & Optics';
$assert=static function(bool $ok,string $why):void{if(!$ok){throw new RuntimeException($why);}};
try{
 foreach(['full','empty','inactive-items','service-only','inactive-category','duplicate-name'] as $kind){
  $categories[$kind]=$db->insert('INSERT INTO rental_categories(name,slug,is_active) VALUES (?,?,?)',
   [in_array($kind,['full','duplicate-name'],true)?$fullName:'[TEST] UI '.$key.' '.$kind,'ui-'.$key.'-'.$kind,$kind==='inactive-category'?0:1]);
 }
 foreach(['full','inactive-items','service-only','inactive-category','duplicate-name'] as $kind){
  $items[$kind]=$db->insert('INSERT INTO rental_items(category_id,name,slug,is_active,is_service) VALUES (?,?,?,?,?)',
   [$categories[$kind],'[TEST] UI '.$kind,'ui-item-'.$key.'-'.$kind,$kind==='inactive-items'?0:1,$kind==='service-only'?1:0]);
 }
 $catalog=(new App\Models\RentalCatalog($db))->load();
 $ids=array_map('intval',array_column($catalog['categories'],'db_id'));
 $assert(in_array($categories['full'],$ids,true)&&in_array($categories['duplicate-name'],$ids,true),'Populated categories, including distinct duplicate-name records, were hidden.');
 foreach(['empty','inactive-items','service-only','inactive-category'] as $kind){$assert(!in_array($categories[$kind],$ids,true),'Public empty/non-equipment category leaked: '.$kind);}
 $assert((bool)array_filter($catalog['services'],static fn($s)=>(int)$s['db_id']===$items['service-only']),'Hiding empty equipment categories removed services.');
 foreach($categories as $id){$assert($db->selectValue('SELECT id FROM rental_categories WHERE id=?',[$id])!==null,'Category data deleted.');}
 // Current Admin duplicate validation must reject a normalized name without changing the DB.
 $admin=$db->selectOne("SELECT id FROM users WHERE role IN ('admin','superadmin') ORDER BY id LIMIT 1");
 if(!$admin){throw new RuntimeException('Existing local QA administrator required.');}
 $_SESSION=['user_id'=>(int)$admin['id']];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/rentals/admin/categories';
 $_POST=['name'=>'  '.$fullName.'  ','slug'=>'unique-'.$key,'is_active'=>'1'];$_GET=[];
 $controller=new App\Controllers\Rentals\RentalAdminController($c);
 $controller->categoriesStore(App\Core\Request::capture());
 $assert((int)$db->selectValue('SELECT COUNT(*) FROM rental_categories WHERE slug=?',['unique-'.$key])===0,'Admin accepted a new duplicate category name.');
 // Legacy duplicate names must not prevent status-only edits of either existing record.
 foreach (['full','duplicate-name'] as $kind) {
  $id=$categories[$kind];
  $saved=$db->selectOne('SELECT * FROM rental_categories WHERE id=?',[$id]);
  foreach (['0','1'] as $active) {
   unset($_SESSION['rentals_admin_notice'],$_SESSION['rentals_admin_notice_error']);
   $_SERVER['REQUEST_URI']='/rentals/admin/categories/'.$id.'/edit';
   $_POST=['id'=>(string)$id,'name'=>$saved['name'],'slug'=>$saved['slug'],'description'=>$saved['description']??'','is_active'=>$active];
   $controller->categoriesUpdate(App\Core\Request::capture(),(string)$id);
   $updated=$db->selectOne('SELECT * FROM rental_categories WHERE id=?',[$id]);
   $assert(($_SESSION['rentals_admin_notice']??'')==='Changes saved.' && (int)$updated['is_active']===(int)$active,
    'Existing duplicate-name category could not change active status: '.($_SESSION['rentals_admin_notice']??''));
   foreach (['name','slug','description','image_path'] as $field) {$assert($updated[$field]===$saved[$field],'Status edit changed '.$field);}
   $other=$categories[$kind==='full'?'duplicate-name':'full'];
   $assert((int)$db->selectValue('SELECT is_active FROM rental_categories WHERE id=?',[$other])===1,'Status edit affected the other category.');
  }
 }
 // Renaming a different record to an occupied name must still fail.
 $_POST=['name'=>'  '.strtoupper($fullName).'  ','slug'=>'ui-'.$key.'-empty','is_active'=>'0'];
 $controller->categoriesUpdate(App\Core\Request::capture(),(string)$categories['empty']);
 $unchanged=$db->selectOne('SELECT name,is_active FROM rental_categories WHERE id=?',[$categories['empty']]);
 $assert(($_SESSION['rentals_admin_notice']??'')==='A category with this name already exists.'
  && $unchanged['name']==='[TEST] UI '.$key.' empty' && (int)$unchanged['is_active']===1,'Duplicate rename was accepted or changed status.');
 echo "PASS: existing duplicate-name categories can deactivate/reactivate without changing identity or the other record; duplicate create/rename still rejected.\n";
 echo "PASS: public categories require active equipment; empty/inactive/service-only excluded; services and Admin data retained; distinct duplicate-name IDs remain distinct; Admin rejects new normalized duplicate names.\n";
}finally{
 foreach($items as $id){$db->delete('DELETE FROM rental_items WHERE id=?',[$id]);}
 foreach($categories as $id){$db->delete('DELETE FROM rental_categories WHERE id=?',[$id]);}
 $_SESSION=[];
}
