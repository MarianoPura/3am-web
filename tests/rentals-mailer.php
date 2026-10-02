<?php
declare(strict_types=1);
// Local integration QA: fake transport only. Unique fixtures are removed afterwards.
$_ENV['MAIL_ENABLED']='false';
$_ENV['RENTALS_STORAGE_ROOT']=sys_get_temp_dir().'/rentals-mailer-qa-'.bin2hex(random_bytes(6));
$container=require dirname(__DIR__).'/bootstrap.php';
if (!in_array(config('app.env'),['local','testing'],true)) { throw new RuntimeException('Local/testing only.'); }
$db=$container->get(App\Core\Database::class);
$fake=new class implements App\Services\RentalMailTransport {
    public array $messages=[];
    public string $mode='success';
    public function isConfigured(): bool { return $this->mode!=='unconfigured'; }
    public function send(string $destination,string $subject,string $text,string $html): void {
        if ($this->mode==='failure') { throw new RuntimeException('private transport failure must not be exposed'); }
        if ($this->mode==='uncertain') { throw new App\Services\SmtpDeliveryUncertain(); }
        $this->messages[]=compact('destination','subject','text','html');
    }
};
$container->set(App\Services\RentalMailTransport::class,$fake);
$mailer=$container->get(App\Services\RentalNotification::class);
$settings=new App\Models\RentalMailSettings($db);
$events=App\Services\RentalMailEvents::class;
$assert=static function(bool $condition,string $message): void { if(!$condition) { throw new RuntimeException($message); } };
$reject=static function(callable $call,string $message) use($assert): void { $rejected=false; try{$call();}catch(InvalidArgumentException $e){$rejected=true;} $assert($rejected,$message); };
$request=static function(array $post=[],string $method='POST'): App\Core\Request { $_POST=$post; $_GET=[]; $_SERVER['REQUEST_METHOD']=$method; $_SERVER['REQUEST_URI']='/rentals/admin/email'; return App\Core\Request::capture(); };
$key=bin2hex(random_bytes(6)); $email='mailer-'.$key.'@example.test';
$users=[]; $orders=[]; $recipientIds=[]; $category=$item=$method=0; $proofs=[]; $testIds=[];
$savedTemplates=$db->select('SELECT * FROM rental_email_templates');
$counts=static function() use($fake,$email): int { return count(array_filter($fake->messages,static fn($m)=>$m['destination']===$email)); };
try {
    $users[]=$db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',['[TEST] Mail customer',$email,password_hash($key,PASSWORD_DEFAULT),'customer']);
    $users[]=$db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',['[TEST] Mail admin','mailer-admin-'.$key.'@example.test',password_hash($key,PASSWORD_DEFAULT),'admin']);
    $category=$db->insert('INSERT INTO rental_categories(name,slug) VALUES (?,?)',['[TEST] Mail category','mail-'.$key]);
    $item=$db->insert('INSERT INTO rental_items(category_id,name,slug,rental_rate,available_quantity) VALUES (?,?,?,?,?)',[$category,'[TEST] Camera <script>alert(1)</script>','mail-'.$key,100,100]);
    $method=$db->insert('INSERT INTO payment_methods(name,type) VALUES (?,?)',['[TEST] Mail method '.$key,'manual']);
    // Ensure defaults for this isolated test; restore all original template rows in finally.
    foreach($events::labels() as $event=>$label) { foreach(['customer','admin'] as $audience) { $settings->saveTemplate($events::defaults($event,$audience)); } }
    $internal='ops-'.$key.'@example.test';
    $recipientIds[]=$settings->saveRecipient(0,'[TEST] Operations',$internal,true,array_keys($events::labels()));
    $proofOnly='proof-'.$key.'@example.test';
    $recipientIds[]=$settings->saveRecipient(0,'[TEST] Proof team',$proofOnly,true,[$events::PAYMENT_PROOF_RECEIVED]);
    $disabled='disabled-'.$key.'@example.test';
    $recipientIds[]=$settings->saveRecipient(0,'[TEST] Disabled',$disabled,false,array_keys($events::labels()));
    $directory=App\Services\RentalStorage::directory('payment');
    $newProof=static function() use($directory,&$proofs): string {
        $name=bin2hex(random_bytes(16)).'.png'; $path=$directory.'/'.$name;
        file_put_contents($path,'isolated test proof; never emailed'); $proofs[]=$path;
        return 'micro/payment/'.$name;
    };
    $newOrder=static function() use($db,&$orders,$users,$email,$method,$item,$newProof,$key): int {
        $id=$db->insert('INSERT INTO order_header(order_number,user_id,customer_name,customer_email,customer_phone,payment_method_id,payment_proof_path,status_token,subtotal,security_deposit,total_amount,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            ['MAIL-'.$key.'-'.count($orders),$users[0],'[TEST] Customer <b>safe</b>',$email,'Sample phone',$method,$newProof(),bin2hex(random_bytes(32)),300,100,400,'PRIVATE INTERNAL NOTE']);
        $orders[]=$id;
        $db->insert('INSERT INTO order_details(order_header_id,rental_item_id,item_name,quantity,rental_start_date,rental_end_date,line_total) VALUES (?,?,?,?,?,?,?)',[$id,$item,'Camera <script>unsafe</script>',2,'2026-11-10','2026-11-12',300]);
        return $id;
    };
    $first=$newOrder(); $mailer->rentalSubmitted($first);
    $assert($counts()===1,'Submission must send one combined customer confirmation.');
    $assert(count(array_filter($fake->messages,static fn($m)=>$m['destination']===$internal))===1,'Initial request/proof duplicated company email.');
    $assert(count(array_filter($fake->messages,static fn($m)=>$m['destination']===$proofOnly))===1,'Proof-only subscriber missed initial proof.');
    $assert(!array_filter($fake->messages,static fn($m)=>$m['destination']===$disabled),'Disabled recipient received email.');
    $message=$fake->messages[0];
    $assert(str_contains($message['subject'],'Received') && str_contains($message['text'],'₱400.00') && str_contains($message['text'],'× 2'),'Order snapshot placeholders missing.');
    $assert(!str_contains($message['text'],'PRIVATE INTERNAL NOTE') && !str_contains($message['text'],'micro/payment') && !str_contains($message['html'],'<script>'),'Sensitive notes/proof or executable HTML leaked.');
    $assert(str_contains($message['html'],'&lt;script&gt;'),'HTML order values were not escaped.');
    $mailer->rentalSubmitted($first); $assert($counts()===1,'Repeated submit sent duplicate.');
    $db->update('UPDATE users SET email=? WHERE id=?',['changed-'.$key.'@example.test',$users[0]]);
    $db->update('UPDATE order_header SET payment_proof_path=? WHERE id=?',[$newProof(),$first]);
    $mailer->paymentProofReceived($first); $assert($counts()===2,'Replacement proof acknowledgement missing or destination not from order snapshot.');
    $mailer->paymentProofReceived($first); $assert($counts()===2,'Same proof generated duplicate email.');
    $template=$events::defaults($events::RENTAL_SUBMITTED,'customer');
    $template['subject']='Custom {{order_number}}'; $template['body']='Hi {{customer_name}}\nTotal {{total_amount}}';
    $settings->saveTemplate($template); $second=$newOrder(); $mailer->rentalSubmitted($second);
    $assert((bool)array_filter($fake->messages,static fn($m)=>str_starts_with($m['subject'],'Custom MAIL-') && str_contains($m['text'],'Total ₱400.00')),'Saved subject/body not used.');
    $template['is_active']=0; $settings->saveTemplate($template); $third=$newOrder(); $before=$counts(); $mailer->rentalSubmitted($third); $assert($counts()===$before,'Disabled template sent mail.');
    $template['is_active']=1; $settings->saveTemplate($template);
    $reject(fn()=>$settings->saveRecipient(0,'Bad','invalid',true,[]),'Invalid recipient accepted.');
    $reject(fn()=>$settings->saveRecipient(0,'Bad','bad@example.test',true,[['nested']]),'Invalid subscription accepted.');
    $bad=$template; $bad['body']='{{APP_KEY}}'; $reject(fn()=>$settings->saveTemplate($bad),'Secret/unknown variable accepted.');
    $bad=$template; $bad['subject']="Injected\r\nCc: bad@example.test"; $reject(fn()=>$settings->saveTemplate($bad),'Header injection accepted.');
    $bad=$template; $bad['body']='{{admin_order_url}}'; $reject(fn()=>$settings->saveTemplate($bad),'Admin URL allowed in customer template.');
    $preview=$mailer->preview($events::defaults($events::RENTAL_REJECTED,'customer'),array_replace($mailer->sample($events::RENTAL_REJECTED),['rejection_reason'=>'Safe sample']));
    $assert(!str_contains($preview['text'],'{{') && str_contains($preview['text'],'RENT-000123') && str_contains($preview['text'],'Safe sample'),'Preview did not substitute safe sample data.');
    $_SESSION=['user_id'=>$users[1]]; $admin=new App\Controllers\Rentals\RentalAdminController($container);
    $fake->mode='failure';
    $response=$admin->reviewProof($request(['decision'=>'approved']),(string)$first);
    $assert($response->status()===302 && (int)$db->selectValue('SELECT payment_status FROM order_header WHERE id=?',[$first])===1,'SMTP failure undid successful approval.');
    $failed=$db->selectOne('SELECT * FROM rental_notification_deliveries WHERE order_id=? AND audience=\'customer\' AND event_key=?',[$first,$events::PAYMENT_APPROVED]);
    $assert($failed && $failed['status']==='failed' && $failed['failure_category']==='smtp_failure','Failed delivery not recorded safely.');
    $fake->mode='success'; $before=$counts(); $mailer->retry((int)$failed['id']); $assert($counts()===$before+1,'Failed email could not be retried.');
    $assert($db->selectValue('SELECT status FROM rental_notification_deliveries WHERE id=?',[$failed['id']])==='sent','Retry not marked sent.');
    $reject(fn()=>$mailer->retry((int)$failed['id']),'Sent email retried.');
    $before=$counts(); $admin->reviewProof($request(['decision'=>'approved']),(string)$first); $assert($counts()===$before,'Duplicate approval sent email.');
    $assert((bool)array_filter($fake->messages,static fn($m)=>str_contains($m['subject'],'Payment Confirmed') && str_contains($m['text'],'rental request is approved')),'Combined approved/payment email missing.');
    $admin->reviewProof($request(['decision'=>'rejected']),(string)$second);
    $assert((int)$db->selectValue('SELECT payment_status FROM order_header WHERE id=?',[$second])===2 && array_filter($fake->messages,static fn($m)=>str_contains($m['subject'],'Needs Attention')),'Combined rejection missing.');
    $before=$counts(); $admin->reviewProof($request(['decision'=>'rejected']),(string)$second); $assert($counts()===$before,'Duplicate rejection sent email.');
    $db->update('UPDATE order_header SET payment_proof_path=?,payment_status=0,payment_reviewed_at=NULL WHERE id=?',[$newProof(),$second]);
    $mailer->paymentProofReceived($second); $before=$counts(); $admin->reviewProof($request(['decision'=>'approved']),(string)$second); $assert($counts()===$before+1,'New proof revision blocked legitimate new review.');
    $fake->mode='uncertain'; $fourth=$newOrder(); $mailer->rentalSubmitted($fourth);
    $uncertain=$db->selectOne('SELECT id,status FROM rental_notification_deliveries WHERE order_id=? AND audience=\'customer\'',[$fourth]);
    $assert($uncertain['status']==='uncertain','Unknown SMTP acceptance incorrectly marked retryable.');
    $reject(fn()=>$mailer->retry((int)$uncertain['id']),'Uncertain delivery retried.');
    $fake->mode='success'; $orderCount=$db->selectValue('SELECT COUNT(*) FROM order_header');
    $testEmail='test-'.$key.'@example.test'; $test=$events::defaults($events::RENTAL_SUBMITTED,'customer'); $test['subject']=str_repeat('X',200);
    $mailer->sendTest($test,$testEmail); $testIds=array_column($db->select('SELECT id FROM rental_notification_deliveries WHERE event_key=\'test\' AND recipient_email=?',[$testEmail]),'id');
    $assert($db->selectValue('SELECT COUNT(*) FROM order_header')===$orderCount && str_starts_with($fake->messages[array_key_last($fake->messages)]['subject'],'[TEST] '),'Test email changed orders or lacked TEST prefix.');
    $settings->saveRecipient($recipientIds[1],'[TEST] Updated proof team',$proofOnly,false,[]);
    $assert(!$db->selectValue('SELECT COUNT(*) FROM rental_notification_recipient_events WHERE recipient_id=?',[$recipientIds[1]]),'Subscription edits not persisted.');
    $settings->removeRecipient($recipientIds[1]); $assert(!$db->selectValue('SELECT COUNT(*) FROM rental_notification_recipients WHERE id=?',[$recipientIds[1]]),'Recipient removal failed.');
    $controller=new App\Controllers\Rentals\RentalEmailController($container);
    $_SESSION=['user_id'=>$users[0]];
    $assert($controller->index($request([],'GET'))->status()===403 && $controller->saveRecipient($request())->status()===403
        && $controller->retry($request(),'1')->status()===403 && $controller->update($request(),$events::RENTAL_SUBMITTED,'customer')->status()===403,'Customer accessed mailer Admin.');
    $_SESSION=['user_id'=>$users[1]]; $db->update('UPDATE users SET role=\'superadmin\' WHERE id=?',[$users[1]]);
    $assert($controller->index($request([],'GET'))->status()===200,'Superadmin access failed.');
    $before=count($fake->messages); $db->beginTransaction(); $mailer->paymentProofReceived($third); $db->rollBack(); $assert(count($fake->messages)===$before,'Email sent before transaction commit.');
    echo "PASS: combined events, order snapshot, separate subscribers, proof revisions, editable/disabled templates, escaped preview, validation, review transition dedup, SMTP failure preserves status, retry, uncertainty, test mail, recipients, Admin/Superadmin guards and post-commit enforcement.\n";
} finally {
    foreach($orders as $id) { $db->delete('DELETE FROM rental_notification_deliveries WHERE order_id=?',[$id]); $db->delete('DELETE FROM order_header WHERE id=?',[$id]); }
    foreach($testIds as $id) { $db->delete('DELETE FROM rental_notification_deliveries WHERE id=?',[$id]); }
    foreach($recipientIds as $id) { $db->delete('DELETE FROM rental_notification_recipients WHERE id=?',[$id]); }
    if($item) { $db->delete('DELETE FROM rental_items WHERE id=?',[$item]); }
    if($category) { $db->delete('DELETE FROM rental_categories WHERE id=?',[$category]); }
    if($method) { $db->delete('DELETE FROM payment_methods WHERE id=?',[$method]); }
    foreach($users as $id) { $db->delete('DELETE FROM users WHERE id=?',[$id]); }
    foreach($events::labels() as $event=>$label) { $db->delete('DELETE FROM rental_email_templates WHERE event_key=?',[$event]); }
    foreach($savedTemplates as $r) { $db->insert('INSERT INTO rental_email_templates(id,event_key,audience,display_name,subject,body,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',array_values($r)); }
    foreach($proofs as $path) { @unlink($path); } if(isset($directory)) { @rmdir($directory); } @rmdir($_ENV['RENTALS_STORAGE_ROOT']); $_SESSION=[];
}
