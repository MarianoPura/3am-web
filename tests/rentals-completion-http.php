<?php
declare(strict_types=1);
// Real multipart uploads and protected HTTP responses on a local mail-disabled QA server.
$base=rtrim($argv[1]??'','/');
if(!preg_match('#^http://127\.0\.0\.1:[0-9]+(?:/[A-Za-z0-9_/-]+)?$#D',$base)) { throw new RuntimeException('Loopback QA URL only.'); }
$_ENV['MAIL_ENABLED']='false';
$c=require dirname(__DIR__).'/bootstrap.php';
if(!in_array(config('app.env'),['local','testing'],true)||!in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost','::1'],true)) { throw new RuntimeException('Local QA database only.'); }
$db=$c->get(App\Core\Database::class);
$check=static function(bool $ok,string $message):void { if(!$ok) { throw new RuntimeException($message); } };
$key=bin2hex(random_bytes(8));$password=bin2hex(random_bytes(16));$users=[];$jars=[];$ownedFiles=[];$category=$equipment=$service=$method=$sharedItem=$requestId=0;
$keep=in_array('--keep-visual',$argv,true);
$metadata=sys_get_temp_dir().'/rentals-completion-visual.json';
$cleanup=static function(array $data)use($db):void {
    foreach($data['users'] as $id) {
        // Only disposable, explicitly named local QA users may be removed.
        $name=$db->selectValue('SELECT name FROM users WHERE id=?',[$id]);
        if($name!==null && !str_starts_with($name,'[TEST] Completion HTTP ')) { throw new RuntimeException('Cleanup fixture identity mismatch.'); }
        $db->delete('DELETE FROM rental_notification_deliveries WHERE service_request_id IN (SELECT id FROM rental_service_requests WHERE user_id=?)',[$id]);
        $db->delete('DELETE FROM rental_service_requests WHERE user_id=?',[$id]);
        $db->delete('DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id=?)',[$id]);
        $db->delete('DELETE FROM carts WHERE user_id=?',[$id]);
        $db->delete('DELETE FROM users WHERE id=?',[$id]);
    }
    foreach($data['items'] as $id) { if($id) {$db->delete("DELETE FROM rental_items WHERE id=? AND name LIKE '[TEST] Completion HTTP %'",[$id]);} }
    if($data['method']) {$db->delete("DELETE FROM payment_methods WHERE id=? AND name LIKE '[TEST] Completion HTTP %'",[$data['method']]);}
    if($data['category']) {$db->delete("DELETE FROM rental_categories WHERE id=? AND name LIKE '[TEST] Completion HTTP %'",[$data['category']]);}
    foreach($data['files'] as $path) {
        if(str_starts_with($path,'micro/payment/qr/')) { App\Services\RentalManagedImage::remove($path,'qr'); }
        elseif(str_starts_with($path,'micro/rentals/products/')) { App\Services\RentalManagedImage::remove($path,'product'); }
        elseif(str_starts_with($path,'micro/payment/')) { App\Services\RentalPaymentProof::remove($path); }
    }
};
if(in_array('--cleanup-visual',$argv,true)) {
    if(is_file($metadata)) { $cleanup(json_decode(file_get_contents($metadata),true,512,JSON_THROW_ON_ERROR));unlink($metadata); }
    echo "PASS: visual fixtures cleaned.\n"; exit;
}
foreach(['guest','customer','other','admin','stale-cart'] as $who) { $jars[$who]=tempnam(sys_get_temp_dir(),'rentals-http-'); }
$http=static function(string $who,string $path,?array $post=null,bool $multipart=false,array $requestHeaders=[])use($base,$jars):array {
    $curl=curl_init($base.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$jars[$who],CURLOPT_COOKIEJAR=>$jars[$who],CURLOPT_TIMEOUT=>20,CURLOPT_HEADER=>true]);
    if($requestHeaders!==[]) { curl_setopt($curl,CURLOPT_HTTPHEADER,$requestHeaders); }
    if($post!==null) { curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$multipart?$post:http_build_query($post)]); }
    $response=curl_exec($curl);if($response===false){throw new RuntimeException('Local QA request failed.');}
    $size=curl_getinfo($curl,CURLINFO_HEADER_SIZE);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    return [$code,substr($response,$size),substr($response,0,$size)];
};
$token=static function(string $html):string { if(!preg_match('/name="_token" value="([^"<>]+)"/',$html,$m)){throw new RuntimeException('Missing CSRF token.');}return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); };
$login=static function(string $who,int $i)use($http,$token,$key,$password,$check):void {
    [, $page]=$http($who,'/rentals/account');[$code]=$http($who,'/rentals/account',['_token'=>$token($page),'action'=>'login','email'=>'completion-http-'.$key.'-'.$i.'@example.test','password'=>$password]);$check($code===302,'QA login failed.');
};
try {
    foreach(['customer','customer','admin'] as $i=>$role) { $users[]=$db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',['[TEST] Completion HTTP '.$i,'completion-http-'.$key.'-'.$i.'@example.test',password_hash($password,PASSWORD_DEFAULT),$role]); }
    $category=$db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)',['[TEST] Completion HTTP category','http-completion-'.$key]);
    $login('admin',2);$login('customer',0);$login('other',1);
    [, $editor]=$http('admin','/rentals/admin/items/new');
    $fields=['_token'=>$token($editor),'category_id'=>$category,'name'=>'[TEST] Completion HTTP camera','slug'=>'http-camera-'.$key,'sku'=>'','description'=>'Gallery test camera','ideal_use'=>'Events','is_service'=>'0','availability_status'=>'available','rental_unit'=>'day','rental_rate'=>'500','security_deposit'=>'100','available_quantity'=>'5','is_active'=>'1'];
    $jpg=BASE_PATH.'/media/rentals-sony-camera.jpg';$second=BASE_PATH.'/media/rentals-camera-lineup.jpg';
    $upload=$fields+['product_image'=>new CURLFile($jpg,'image/jpeg','camera.jpg'),'additional_images[0]'=>new CURLFile($second,'image/jpeg','lineup.jpg'),'additional_images[1]'=>new CURLFile($jpg,'image/jpeg','angle.jpg')];
    [$code]=$http('admin','/rentals/admin/items',$upload,true);$check(in_array($code,[302,303],true),'Product upload response failed.');
    $item=$db->selectOne('SELECT * FROM rental_items WHERE slug=?',['http-camera-'.$key]);$check($item!==null,'Uploaded product not saved.');$equipment=(int)$item['id'];
    $paths=App\Services\RentalGallery::paths($item['additional_image_paths']);$ownedFiles=[$item['image_path'],...$paths];
    $check(count($paths)===2,'Additional uploads not saved.');
    foreach($ownedFiles as $path) {
        $check(App\Services\RentalStorage::path($path)!==null,'Uploaded image missing from storage.');
        [$code,,$headers]=$http('guest','/rentals/product-image/'.basename($path));$check($code===200&&str_contains(strtolower($headers),'content-type: image/jpeg'),'Managed image not served.');
        $check(str_contains($headers,'Cache-Control: private, max-age=86400, immutable'),'Product image browser cache missing.');
        $check(preg_match('/^ETag: (.+)\r?$/mi',$headers,$tag)===1,'Product image ETag missing.');
        [$cached,$body]=$http('guest','/rentals/product-image/'.basename($path),null,false,['If-None-Match: '.trim($tag[1])]);
        $check($cached===304&&$body==='','Unchanged image must not resend bytes.');
        $check(preg_match('/^Last-Modified: (.+)\r?$/mi',$headers,$modified)===1,'Product image modified date missing.');
        [$cached,$body]=$http('guest','/rentals/product-image/'.basename($path),null,false,['If-Modified-Since: '.trim($modified[1])]);
        $check($cached===304&&$body==='','Modified-date image revalidation failed.');
        [$cached]=$http('guest','/rentals/product-image/'.basename($path),null,false,['If-None-Match: "different"','If-Modified-Since: '.trim($modified[1])]);
        $check($cached===200,'ETag must take precedence over modified date.');
    }
    [$code,,$headers]=$http('guest','/rentals/product-image/'.str_repeat('0',32).'.jpg');
    $check($code===404&&str_contains(strtolower($headers),'no-store'),'Missing image must not cache a failed response.');
    [, $catalog]=$http('guest','/rentals/items');$check(str_contains($catalog,'/rentals/product-image/')&&!str_contains($catalog,'src="/micro/'),'Catalogue uses raw storage URLs.');
    $sharedItem=$db->insert('INSERT INTO rental_items(category_id,name,slug,image_path) VALUES (?,?,?,?)',[$category,'[TEST] Completion HTTP shared image','http-shared-'.$key,$paths[0]]);
    $http('admin','/rentals/admin/items/'.$equipment.'/edit',$fields+['remove_gallery[0]'=>'0']);
    $check(App\Services\RentalStorage::path($paths[0])!==null,'A shared image was incorrectly deleted.');
    $item=$db->selectOne('SELECT * FROM rental_items WHERE id=?',[$equipment]);$check(count(App\Services\RentalGallery::paths($item['additional_image_paths']))===1,'Gallery removal not persisted.');
    $bad=tempnam(sys_get_temp_dir(),'not-image-');file_put_contents($bad,'This is not an image.');
    try { $http('admin','/rentals/admin/items/'.$equipment.'/edit',$fields+['additional_images[0]'=>new CURLFile($bad,'text/plain','bad.jpg')],true); }
    finally { unlink($bad); }
    $unchanged=$db->selectOne('SELECT image_path,additional_image_paths FROM rental_items WHERE id=?',[$equipment]);
    $check($unchanged['image_path']===$item['image_path']&&$unchanged['additional_image_paths']===$item['additional_image_paths'],'Invalid upload changed saved images.');
    // Separate service payment using the same encrypted file service, never order_header.
    $service=$db->insert("INSERT INTO rental_items(category_id,name,slug,is_service,availability_status) VALUES (?,?,?,1,'inquire')",[$category,'[TEST] Completion HTTP event crew','http-service-'.$key]);
    [$code,$catalog]=$http('guest','/rentals/items');
    $check($code===200&&str_contains($catalog,'Browse rental type')&&str_contains($catalog,'[TEST] Completion HTTP camera')&&!str_contains($catalog,'[TEST] Completion HTTP event crew'),'Equipment catalogue mixes service records.');
    $check(str_contains($catalog,'data-detail-form')&&str_contains($catalog,'loading="lazy"')&&str_contains($catalog,'decoding="async"'),'Equipment actions or image loading changed.');
    foreach(['/rentals/items?type=services','/rentals/services'] as $serviceUrl) {
        [$code,$catalog]=$http('guest',$serviceUrl);
        $check($code===200&&str_contains($catalog,'Browse rental type')&&str_contains($catalog,'[TEST] Completion HTTP event crew')&&!str_contains($catalog,'[TEST] Completion HTTP camera'),'Service catalogue mixes equipment records.');
        $check(str_contains($catalog,'/rentals/services/'.$service.'/request')&&str_contains($catalog,'?type=services" aria-current="page"')&&!str_contains($catalog,'data-detail-form'),'Service selection/actions are incorrect.');
        $check(str_contains($catalog,'data-rentals-filter="http-completion-'.$key.'"'),'Service category filter missing.');
    }
    [$code,$catalog]=$http('guest','/rentals/items?type=invalid');
    $check($code===200&&str_contains($catalog,'data-detail-form'),'Unknown catalogue type must default to equipment.');
    $store=new App\Services\RentalServiceRequests($db);
    $record=$store->submit($users[0],$service,hash('sha256',$key),['phone'=>'QA phone','start_date'=>date('Y-m-d',strtotime('+5 days')),'end_date'=>date('Y-m-d',strtotime('+6 days')),'location'=>'QA event venue','details'=>'Event camera crew, lighting and audio coverage.']);$requestId=(int)$record['id'];
    $db->update('UPDATE rental_service_requests SET notification_attempted_at=CURRENT_TIMESTAMP WHERE id=?',[$requestId]);
    $method=$db->insert("INSERT INTO payment_methods(name,type,account_name,account_number) VALUES (?,'manual',?,?)",['[TEST] Completion HTTP payment','QA account','TEST-ONLY']);
    $qr='micro/payment/qr/'.bin2hex(random_bytes(16)).'.jpg';$qrDir=App\Services\RentalStorage::directory('payment/qr');copy($jpg,$qrDir.'/'.basename($qr));$ownedFiles[]=$qr;$db->update('UPDATE payment_methods SET qr_image_path=? WHERE id=?',[$qr,$method]);
    [, $page]=$http('admin','/rentals/admin/service-requests/'.$requestId);$adminToken=$token($page);
    [$code]=$http('admin','/rentals/admin/service-requests/'.$requestId.'/review',['_token'=>$adminToken,'decision'=>'approved','customer_message'=>'We can arrange the crew.']);$check($code===303,'Service review failed.');
    $http('admin','/rentals/admin/service-requests/'.$requestId.'/manage',['_token'=>$adminToken,'action'=>'quote','quote_amount'=>'1250.50','quote_notes'=>'Crew and event coverage.']);
    [$code,$page]=$http('customer','/rentals/service-requests/'.$requestId);$check($code===200&&str_contains($page,'1,250.50')&&str_contains($page,'/rentals/payment-qr/'.$method),'Customer quotation/QR missing.');$customerToken=$token($page);
    [, $otherPage]=$http('other','/rentals/account');$otherToken=$token($otherPage);
    [$code]=$http('other','/rentals/service-requests/'.$requestId);$check($code===403,'Another customer saw request details.');
    [$code]=$http('other','/rentals/service-requests/'.$requestId.'/payment',['_token'=>$otherToken,'payment_method_id'=>$method]);$check($code===403,'Another customer submitted service payment.');
    [$code]=$http('customer','/rentals/service-requests/'.$requestId.'/payment',['_token'=>'bad','payment_method_id'=>$method]);$check($code===419,'Service payment bypassed CSRF.');
    $check(preg_match('/name="quote_version" value="([0-9]+)"/',$page,$versionMatch)===1,'Quotation revision missing from customer form.');
    $quoteVersion=(int)$versionMatch[1];
    $payment=['_token'=>$customerToken,'payment_method_id'=>$method,'quote_version'=>$quoteVersion,'payment_reference'=>'QA PAYMENT','payment_proof'=>new CURLFile($jpg,'image/jpeg','proof.jpg')];
    // Revise the quote after the customer's page loaded, then replay that stale form.
    $http('admin','/rentals/admin/service-requests/'.$requestId.'/manage',['_token'=>$adminToken,'action'=>'quote','quote_amount'=>'1300.50','quote_notes'=>'Revised event scope.']);
    [$code]=$http('customer','/rentals/service-requests/'.$requestId.'/payment',$payment,true);
    $check($code===303 && $db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId])===null,'Stale quotation accepted a proof.');
    [, $page]=$http('customer','/rentals/service-requests/'.$requestId);
    $check(str_contains($page,'quotation has changed') && str_contains($page,'1,300.50'),'Stale payment did not show latest quote and safe guidance.');
    $missingVersion=$payment;unset($missingVersion['quote_version']);
    $http('customer','/rentals/service-requests/'.$requestId.'/payment',$missingVersion,true);
    $check($db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId])===null,'Missing revision accepted a proof.');
    $check(preg_match('/name="quote_version" value="([0-9]+)"/',$page,$versionMatch)===1,'Revised quotation missing version.');
    $quoteVersion=(int)$versionMatch[1];$payment['quote_version']=$quoteVersion;
    [$code]=$http('customer','/rentals/service-requests/'.$requestId.'/payment',$payment,true);$check($code===303,'Payment upload failed.');
    $proof=$db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId]);$check(is_string($proof)&&$proof!=='','Proof path not saved.');$ownedFiles[]=$proof;
    $bytes=file_get_contents(App\Services\RentalStorage::path($proof));$check(str_starts_with($bytes,'3AMPROOF1')&&$bytes!==file_get_contents($jpg),'Service proof is not encrypted.');
    $http('customer','/rentals/service-requests/'.$requestId.'/payment',$payment,true);
    $check($proof===$db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId]),'Repeated POST replaced proof.');
    [$code,$plain,$headers]=$http('admin','/rentals/service-requests/'.$requestId.'/proof');$check($code===200&&hash_equals(hash('sha256',file_get_contents($jpg)),hash('sha256',$plain))&&str_contains(strtolower($headers),'content-type: image/jpeg'),'Protected proof decryption/response failed.');
    [$code]=$http('other','/rentals/service-requests/'.$requestId.'/proof');$check($code===403,'Proof ownership bypassed.');
    [$code]=$http('guest','/rentals/service-requests/'.$requestId.'/proof');$check($code===302,'Guest proof access not protected.');
    // A rejected image can be replaced with an encrypted PDF, without affecting orders.
    $http('admin','/rentals/admin/service-requests/'.$requestId.'/manage',['_token'=>$adminToken,'action'=>'payment','decision'=>'rejected','payment_message'=>'Please provide a clearer receipt.']);
    $pdf=tempnam(sys_get_temp_dir(),'rentals-proof-pdf-');
    file_put_contents($pdf,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    try {
        $pdfPayment=['_token'=>$customerToken,'payment_method_id'=>$method,'quote_version'=>$quoteVersion,'payment_reference'=>'QA PDF PAYMENT','payment_proof'=>new CURLFile($pdf,'application/pdf','receipt.pdf')];
        [$code]=$http('customer','/rentals/service-requests/'.$requestId.'/payment',$pdfPayment,true);$check($code===303,'Rejected proof replacement failed.');
        $nextProof=$db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId]);$ownedFiles[]=$nextProof;
        $check($nextProof!==$proof && App\Services\RentalPaymentProof::path($proof)===null,'Old proof was not safely replaced.');
        [$code,$plain,$headers]=$http('admin','/rentals/service-requests/'.$requestId.'/proof');
        $check($code===200 && str_contains(strtolower($headers),'content-type: application/pdf') && $plain===file_get_contents($pdf),'Protected PDF response failed.');
        [$code,$pdfView]=$http('admin','/rentals/admin/service-requests/'.$requestId);
        $check($code===200 && str_contains($pdfView,'View uploaded proof') && !str_contains($pdfView,'<img class="rentals-service-proof"'),'PDF rendered as an image.');
        $http('customer','/rentals/service-requests/'.$requestId.'/payment',$pdfPayment,true);
        $check($nextProof===$db->selectValue('SELECT payment_proof_path FROM rental_service_requests WHERE id=?',[$requestId]),'PDF replay replaced proof.');
    } finally { unlink($pdf); }
    $review=['_token'=>$adminToken,'action'=>'payment','decision'=>'approved','payment_message'=>'Verified.'];
    $http('admin','/rentals/admin/service-requests/'.$requestId.'/manage',$review);$http('admin','/rentals/admin/service-requests/'.$requestId.'/manage',$review);
    $count=(int)$db->selectValue("SELECT COUNT(*) FROM rental_notification_deliveries WHERE service_request_id=? AND event_key='service_payment_approved'",[$requestId]);$check($count===1,'Repeated payment review duplicated mail ledger.');
    // Keep an approved request with a proof for visual QA; pure integration covers terminal transitions.
    foreach(['/','/information','/rentals/items','/rentals/services','/rentals/account/forgot'] as $path) { [$code]=$http('guest',$path);$check($code===200,'Public page failed: '.$path); }
    [$code,$dashboard]=$http('admin','/rentals/admin?period=7d');$check($code===200&&str_contains($dashboard,'id="rental-analytics"')&&!str_contains($dashboard,'href="/rentals/admin/analytics"'),'Dashboard integration failed.');
    [$code]=$http('admin','/rentals/admin/analytics?period=7d');$check($code===302,'Old Analytics route not redirected.');
    foreach(['/rentals/admin/items?page=999','/rentals/admin/orders?page=2','/rentals/admin/customers?page=2','/rentals/admin/sales-report?page=2','/rentals/admin/payment-report?page=2','/rentals/orders?page=2','/rentals/service-requests?page=2'] as $path) { [$code]=$http(str_contains($path,'/admin/')?'admin':'customer',$path);$check($code===200,'Paginated page failed: '.$path); }
    if(!$keep) {
        // Two independently authenticated browser sessions must both expire after a password change.
        $login('stale-cart',0);[, $cartPage]=$http('stale-cart','/rentals/items');$cartToken=$token($cartPage);
        $rentalDay=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $cartFields=['_token'=>$customerToken,'id'=>'http-camera-'.$key,'quantity'=>1,'rental_start_date'=>$rentalDay,'rental_end_date'=>$rentalDay];
        [$code]=$http('customer','/rentals/cart/add',$cartFields);$check($code===302,'Valid dated cart failed.');
        $cartId=(int)$db->selectValue('SELECT id FROM carts WHERE user_id=?',[$users[0]]);
        $beforeLines=(int)$db->selectValue('SELECT COUNT(*) FROM cart_items WHERE cart_id=?',[$cartId]);
        $extreme=$cartFields;$extreme['rental_end_date']='9999-12-31';
        $http('customer','/rentals/cart/add',$extreme);
        $check((int)$db->selectValue('SELECT COUNT(*) FROM cart_items WHERE cart_id=?',[$cartId])===$beforeLines,'Extreme date POST changed saved Cart.');
        $newPassword=bin2hex(random_bytes(16));
        $db->update('UPDATE users SET password=? WHERE id=?',[password_hash($newPassword,PASSWORD_DEFAULT),$users[0]]);
        $orderCount=(int)$db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id=?',[$users[0]]);
        [$code,,$headers]=$http('customer','/rentals/checkout');
        $check($code===302&&str_contains($headers,'/rentals/account'),'Stale session reached Checkout GET.');
        [$code,,$headers]=$http('customer','/rentals/checkout',['_token'=>$customerToken,'customer_name'=>'QA']);
        $check($code===302&&str_contains($headers,'/rentals/account'),'Stale session reached Checkout POST.');
        $http('stale-cart','/rentals/cart/clear',['_token'=>$cartToken]);
        $check((int)$db->selectValue('SELECT COUNT(*) FROM cart_items WHERE cart_id=?',[$cartId])===$beforeLines,'Independent stale session cleared saved Cart.');
        $check((int)$db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id=?',[$users[0]])===$orderCount,'Stale Checkout created an order.');
        [, $freshLogin]=$http('customer','/rentals/account');
        [$code]=$http('customer','/rentals/account',['_token'=>$token($freshLogin),'action'=>'login','email'=>'completion-http-'.$key.'-0@example.test','password'=>$newPassword]);
        $check($code===302,'Fresh login failed after password change.');
        [$code]=$http('customer','/rentals/checkout');$check($code===200,'Freshly authenticated Checkout failed.');
    }
    echo "PASS: unified Equipment/Services catalogue; managed product/gallery uploads and caching; stale/missing/current quotation versions; encrypted image/PDF proof uploads and replacement; ownership/CSRF; repeated POST/review deduplication; stale Checkout GET/POST and independent Cart sessions; valid re-login; extreme date rejection; pagination/dashboard/public pages. No real emails.\n";
    if($keep) { file_put_contents($metadata,json_encode(['users'=>$users,'items'=>[$equipment,$service,$sharedItem],'category'=>$category,'method'=>$method,'files'=>$ownedFiles,'base'=>$base,'key'=>$key,'password'=>$password,'requestId'=>$requestId],JSON_THROW_ON_ERROR));@chmod($metadata,0600);echo "Visual fixtures retained temporarily; run --cleanup-visual after browser checks.\n"; }
} finally {
    if(!$keep || !is_file($metadata)) { $cleanup(['users'=>$users,'items'=>[$equipment,$service,$sharedItem],'category'=>$category,'method'=>$method,'files'=>$ownedFiles]); }
    foreach($jars as $jar) { @unlink($jar); }
}
