<?php
declare(strict_types=1);
// Disposable local fixtures; mocked mail only. No existing business records changed.
$_ENV['MAIL_ENABLED']='false';
$_ENV['MAIL_RENTALS_TO']='qa-owner@example.test';
$_ENV['MAIL_RENTALS_CC']='qa-team@example.test';
session_start();
$c=require dirname(__DIR__).'/bootstrap.php';
if(!in_array(config('app.env'),['local','testing'],true)||!in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost','::1'],true)) { throw new RuntimeException('Local loopback only.'); }
$db=$c->get(App\Core\Database::class);
$fake=new class implements App\Services\RentalMailTransport {
    public array $messages=[]; public bool $fail=false;
    public function isConfigured():bool { return true; }
    public function send(string $destination,string $subject,string $text,string $html,array $cc=[]):void {
        if($this->fail) { throw new RuntimeException('SIMULATED FAILURE'); }
        $this->messages[]=compact('destination','subject','text','html','cc');
    }
};
$c->set(App\Services\RentalMailTransport::class,$fake);
$mailer=$c->get(App\Services\RentalNotification::class);
$check=static function(bool $ok,string $message):void { if(!$ok) { throw new RuntimeException($message); } };
$key=bin2hex(random_bytes(8));$users=[];$category=$item=0;$ids=[];
$temp=sys_get_temp_dir().'/rentals-auth-test-'.$key;
$before=[(int)$db->selectValue('SELECT COUNT(*) FROM order_header'),(int)$db->selectValue('SELECT COUNT(*) FROM order_details')];
try {
    $inspection=(new App\Services\RentalSchema($db))->inspect();
    $check($inspection['issues']===[],'Schema contract failed: '.implode('; ',$inspection['issues']));
    foreach(['customer','customer','admin','superadmin'] as $i=>$role) {
        $users[]=$db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',['[TEST] Completion '.$i,'completion-'.$key.'-'.$i.'@example.test',password_hash('OriginalPassword123',PASSWORD_DEFAULT),$role]);
    }
    $email='completion-'.$key.'-0@example.test';
    $reset=new App\Services\RentalPasswordReset($db,$fake);
    $reset->request('no-account-'.$key.'@example.test');
    $check($fake->messages===[],'Unknown account received reset mail.');
    $reset->request($email);$message=$fake->messages[0];
    preg_match('#/rentals/account/reset/([a-f0-9]{64})#',$message['text'],$m);$token=$m[1]??'';
    $check($token!==''&&$reset->valid($token),'Reset token not usable.');
    $check($message['destination']===$email&&$message['cc']===[],'Reset recipient/CC is unsafe.');
    $stored=$db->selectValue('SELECT token_hash FROM rental_password_resets WHERE user_id=?',[$users[0]]);
    $check($stored!==$token&&hash_equals($stored,hash('sha256',$token)),'Reset token was stored in plaintext.');
    $oldHash=$db->selectValue('SELECT password FROM users WHERE id=?',[$users[0]]);
    try { $reset->reset($token,'short','short');$check(false,'Weak password accepted.'); } catch(RuntimeException $e) { $check($e->getMessage()!=='Weak password accepted.','Weak password accepted.'); }
    $reset->reset($token,'ReplacementPassword123','ReplacementPassword123');
    $check(!$reset->valid($token),'Reset token reusable.');
    $check(password_verify('ReplacementPassword123',$db->selectValue('SELECT password FROM users WHERE id=?',[$users[0]])),'Password was not changed.');
    $_SESSION=['user_id'=>$users[0],'rentals_password_stamp'=>hash('sha256',$oldHash)];
    $account=new App\Services\RentalAccount($db,new App\Services\RentalCart());
    $check($account->current()===null,'Old session survived password reset.');
    $_SESSION=['user_id'=>$users[0]];$check($account->current()===null,'Legacy session survived password reset.');
    $account->login($email,'ReplacementPassword123');$check($account->current()!==null,'New login rejected after password reset.');
    $reset->request($email);$next=end($fake->messages);preg_match('#/reset/([a-f0-9]{64})#',$next['text'],$m);
    $check($reset->valid($m[1]),'Cannot request another reset after a used token.');
    $db->update('UPDATE rental_password_resets SET expires_at=? WHERE user_id=?',[date('Y-m-d H:i:s',time()-1),$users[0]]);
    $check(!$reset->valid($m[1]),'Expired token accepted.');
    $throttle=new App\Services\RentalAuthThrottle($temp);
    for($n=0;$n<10;$n++) { $check($throttle->attempt('login','192.0.2.1',$email),'Early throttle rejection.'); }
    $check(!$throttle->attempt('login','192.0.2.2',strtoupper($email)),'Cross-IP account throttle bypassed.');
    for($n=0;$n<3;$n++) { $check($throttle->attempt('reset','192.0.2.1',$email),'Early reset throttle rejection.'); }
    $check(!$throttle->attempt('reset','192.0.2.3',$email),'Reset throttle bypassed.');
    $category=$db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)',['[TEST] Completion','completion-'.$key]);
    $item=$db->insert("INSERT INTO rental_items(category_id,name,slug,is_service,availability_status) VALUES (?,?,?,1,'inquire')",[$category,'[TEST] Event service','service-'.$key]);
    $store=new App\Services\RentalServiceRequests($db);
    $fields=['phone'=>'QA phone','start_date'=>date('Y-m-d',strtotime('+5 days')),'end_date'=>date('Y-m-d',strtotime('+6 days')),'location'=>'QA venue','details'=>'Cameras and crew <script>unsafe</script>'];
    for($n=0;$n<27;$n++) { $record=$store->submit($users[0],$item,hash('sha256',$key.'-'.$n),$fields);$ids[]=(int)$record['id']; }
    $other=$store->submit($users[1],$item,hash('sha256',$key.'other'),$fields);$ids[]=(int)$other['id'];
    $page1=$store->historyPage($users[0],'all',1);$page2=$store->historyPage($users[0],'all',2);
    $check(count($page1['rows'])===25&&count($page2['rows'])===2&&$page1['total']===27,'Services pagination incorrect.');
    $check(array_intersect(array_column($page1['rows'],'id'),array_column($page2['rows'],'id'))===[],'Pagination repeats records.');
    $check(!in_array($other['id'],array_column($page1['rows'],'id')),'Another customer leaked into history.');
    $id=$ids[0];$count=count($fake->messages);
    $check($store->review($id,$users[2],'approved','Proceed with coordination.'),'Approval failed.');
    $mailer->serviceUpdated($id,'approved');$mailer->serviceUpdated($id,'approved');
    $check(count($fake->messages)===$count+1,'Service approval mail duplicated.');
    $last=end($fake->messages);$check($last['destination']===$email&&$last['cc']===['qa-owner@example.test','qa-team@example.test'],'Service routing differs from configured Owner/CC routing.');
    $check(!$store->review($id,$users[2],'rejected',''),'Repeated review overwrote approval.');
    $check($store->quote($id,$users[2],'1250.50','Camera crew and event coverage'),'Quote failed.');
    $mailer->serviceUpdated($id,'quoted');$mailer->serviceUpdated($id,'quoted');
    $check(!$store->quote($id,$users[2],'01250.50','Camera crew and event coverage'),'Identical quotation changed revision.');
    $check((int)$store->find($id)['quote_version']===1,'Quote revision incorrect.');
    try {$store->quote($id,$users[1],'100','');$check(false,'Customer set a quote.');}catch(RuntimeException $e){$check($e->getMessage()!=='Customer set a quote.','Customer set a quote.');}
    $db->update("UPDATE rental_service_requests SET payment_status='pending',payment_proof_path=? WHERE id=?",['micro/payment/'.bin2hex(random_bytes(16)).'.jpg',$id]);
    $mailer->serviceUpdated($id,'payment_received');$mailer->serviceUpdated($id,'payment_received');
    $check(!$store->submitPayment($id,$users[0],0,'',null),'Repeated payment submission was not ignored.');
    $check($store->reviewPayment($id,$users[3],'approved','Verified.'),'Superadmin payment review failed.');
    $mailer->serviceUpdated($id,'payment_approved');$mailer->serviceUpdated($id,'payment_approved');
    $check(!$store->reviewPayment($id,$users[2],'rejected',''),'Payment decision overwritten.');
    try {$store->close($id,$users[2],'completed','');$check(false,'Future service completed.');}catch(RuntimeException $e){$check($e->getMessage()!=='Future service completed.','Future service completed.');}
    $db->update('UPDATE rental_service_requests SET event_start_date=?,event_end_date=? WHERE id=?',[date('Y-m-d'),date('Y-m-d'),$id]);
    $check($store->close($id,$users[2],'completed','Delivered.'),'Completion failed.');
    $mailer->serviceUpdated($id,'completed');$mailer->serviceUpdated($id,'completed');
    $check(!$store->close($id,$users[2],'cancelled',''),'Completed service was changed.');
    $fake->fail=true;$id2=$ids[1];$store->review($id2,$users[2],'rejected','Unavailable.');$mailer->serviceUpdated($id2,'rejected');$mailer->serviceUpdated($id2,'rejected');
    $check($store->find($id2)['status']==='rejected','SMTP failure reverted service decision.');
    $delivery=$db->selectOne("SELECT status,attempts FROM rental_notification_deliveries WHERE service_request_id=? AND event_key='service_rejected'",[$id2]);
    $check($delivery['status']==='failed'&&(int)$delivery['attempts']===1,'SMTP failure deduplication incorrect.');
    $id3=$ids[2];$check($store->close($id3,$users[2],'cancelled','Cancelled on request.'),'Pending cancellation failed.');
    $check(!$store->review($id3,$users[2],'approved',''),'Cancelled service was approved.');
    $paths=App\Services\RentalGallery::paths(json_encode(['../.env','https://example.test/image.jpg','micro/rentals/products/'.str_repeat('a',32).'.jpg','micro/rentals/products/'.str_repeat('a',32).'.jpg']));
    $check(count($paths)===1,'Gallery path validation/dedup failed.');
    $check($before===[(int)$db->selectValue('SELECT COUNT(*) FROM order_header'),(int)$db->selectValue('SELECT COUNT(*) FROM order_details')],'Services affected equipment orders.');
    echo "PASS: schema; private/single-use/expired password resets; stale-session invalidation; cross-session throttling; pagination/ownership; quotation/review/payment/terminal transitions; dynamic customer + configured CC; notification deduplication and SMTP failure safety; safe gallery references; equipment orders unchanged.\n";
} finally {
    foreach($users as $uid) { $db->delete('DELETE FROM rental_notification_deliveries WHERE service_request_id IN (SELECT id FROM rental_service_requests WHERE user_id=?)',[$uid]);$db->delete('DELETE FROM rental_service_requests WHERE user_id=?',[$uid]);$db->delete('DELETE FROM carts WHERE user_id=?',[$uid]);$db->delete('DELETE FROM users WHERE id=?',[$uid]); }
    if($item) { $db->delete('DELETE FROM rental_items WHERE id=?',[$item]); }
    if($category) { $db->delete('DELETE FROM rental_categories WHERE id=?',[$category]); }
    foreach(glob($temp.'/*.json')?:[] as $file) { unlink($file); } if(is_dir($temp)) { rmdir($temp); }
    $_SESSION=[];
}
