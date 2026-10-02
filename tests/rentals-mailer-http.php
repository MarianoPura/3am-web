<?php
declare(strict_types=1);
// HTTP settings QA against the local QA router (MAIL_ENABLED=false).
$base=rtrim((string)($argv[1]??''),'/');
if(!preg_match('#^http://127\.0\.0\.1:[0-9]+(?:/[A-Za-z0-9_/-]+)?$#',$base)) { throw new RuntimeException('Pass the loopback QA URL.'); }
$_ENV['MAIL_ENABLED']='false'; $container=require dirname(__DIR__).'/bootstrap.php';
if(!in_array(config('app.env'),['local','testing'],true)) { throw new RuntimeException('Local/testing only.'); }
$db=$container->get(App\Core\Database::class); $key=bin2hex(random_bytes(6));
$jar=tempnam(sys_get_temp_dir(),'mailer-http-'); $ids=[]; $recipient=0; $testEmail='http-test-'.$key.'@example.test';
$assert=static function(bool $ok,string $message): void { if(!$ok) { throw new RuntimeException($message); } };
$http=static function(string $path,?array $post=null) use($base,$jar): array {
    $curl=curl_init($base.$path); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15]);
    if($post!==null) { curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]); }
    $body=curl_exec($curl); if($body===false) { throw new RuntimeException('Local QA HTTP request failed.'); }
    $r=[(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($curl,CURLINFO_EFFECTIVE_URL)]; curl_close($curl); return $r;
};
$token=static function(string $html): string { if(!preg_match('/name="_token" value="([^"<>]+)"/',$html,$m)) { throw new RuntimeException('Missing CSRF token.'); } return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); };
$event=App\Services\RentalMailEvents::RENTAL_SUBMITTED; $settings=new App\Models\RentalMailSettings($db);
$original=$db->selectOne('SELECT * FROM rental_email_templates WHERE event_key=? AND audience=\'customer\'',[$event]);
$counts=$db->selectValue('SELECT COUNT(*) FROM order_header');
try {
    foreach(['admin','customer'] as $role) { $ids[$role]=$db->insert('INSERT INTO users(name,email,password,role) VALUES (?,?,?,?)',['[TEST] Mail HTTP '.$key,$role.'-'.$key.'@example.test',password_hash($key,PASSWORD_DEFAULT),$role]); }
    [,,$url]=$http('/rentals/admin/email'); $assert($url===$base.'/rentals/account','Guest mailer Admin access allowed.');
    [, $login]=$http('/rentals/account'); $http('/rentals/account',['_token'=>$token($login),'action'=>'login','email'=>'admin-'.$key.'@example.test','password'=>$key]);
    [$status,$page]=$http('/rentals/admin/email'); $assert($status===200 && str_contains($page,'Email notifications') && str_contains($page,'SMTP configured: <strong>NO'),'Templates/status page failed or attempted real SMTP.');
    $path='/rentals/admin/email/templates/'.$event.'/customer';
    [$status,$edit]=$http($path); $assert($status===200 && str_contains($edit,'name="subject"'),'Event route with underscore failed.');
    $template=App\Services\RentalMailEvents::defaults($event,'customer');
    $post=['_token'=>$token($edit),'display_name'=>$template['display_name'],'subject'=>'HTTP custom {{order_number}}','body'=>'Hi {{customer_name}}. Total {{total_amount}}','is_active'=>'1'];
    foreach([$path,'/rentals/admin/email/recipients','/rentals/admin/email/recipients/1/remove','/rentals/admin/email/deliveries/1/retry'] as $write) {
        [$status]=$http($write,['action'=>'save']); $assert($status===419,'CSRF not enforced for '.$write);
    }
    [$status,$preview]=$http($path,$post+['action'=>'preview']);
    $assert($status===200 && str_contains($preview,'HTTP custom RENT-000123') && str_contains($preview,'Juan Dela Cruz') && !str_contains($preview,'<iframe'),'Sample preview failed under production CSP.');
    $assert($db->selectOne('SELECT * FROM rental_email_templates WHERE event_key=? AND audience=\'customer\'',[$event])===$original,'Preview saved the draft.');
    [$status,$saved]=$http($path,$post+['action'=>'save']); $assert($status===200 && str_contains($saved,'Template saved.'),'Template save failed.');
    $bad=$post; $bad['body']='{{SMTP_PASSWORD}}'; [$status,$error]=$http($path,$bad+['action'=>'save']); $assert($status===422 && str_contains($error,'Unsupported variable'),'Unknown variable did not produce visible error.');
    [$status,$failed]=$http($path,$post+['action'=>'test','test_email'=>$testEmail]);
    $assert($status===422 && str_contains($failed,'not confirmed sent'),'Unconfigured SMTP test not reported safely.');
    $assert($db->selectValue('SELECT COUNT(*) FROM order_header')===$counts,'Preview/test changed business orders.');
    [, $recipientPage]=$http('/rentals/admin/email/recipients');
    $data=['_token'=>$token($recipientPage),'id'=>0,'name'=>'[TEST] Recipient '.$key,'email'=>'recipient-'.$key.'@example.test','is_active'=>'1','events'=>[$event]];
    [$status,$recipientPage]=$http('/rentals/admin/email/recipients',$data); $recipient=(int)$db->selectValue('SELECT id FROM rental_notification_recipients WHERE email=?',[$data['email']]);
    $assert($status===200 && $recipient>0 && str_contains($recipientPage,'Recipient and subscriptions saved.'),'Recipient create failed.');
    $data['id']=$recipient; $data['is_active']='0'; $data['events']=[App\Services\RentalMailEvents::PAYMENT_APPROVED]; $http('/rentals/admin/email/recipients',$data);
    $assert((int)$db->selectValue('SELECT is_active FROM rental_notification_recipients WHERE id=?',[$recipient])===0,'Recipient disable failed.');
    $data['email']='bad email'; [$status,$error]=$http('/rentals/admin/email/recipients',$data); $assert($status===422 && str_contains($error,'valid recipient email'),'Invalid recipient not rejected visibly.');
    [$status,$history]=$http('/rentals/admin/email/history'); $assert($status===200 && str_contains($history,'smtp_not_configured') && str_contains($history,$testEmail),'History missing safe test result.');
    [$status]=$http('/rentals/admin/email/recipients/'.$recipient.'/remove',['_token'=>$token($history),'confirm'=>'remove']); $assert($status===200 && !$db->selectValue('SELECT id FROM rental_notification_recipients WHERE id=?',[$recipient]),'Recipient removal failed.');
    $http('/rentals/logout',['_token'=>$token($history)]); [, $login]=$http('/rentals/account');
    $http('/rentals/account',['_token'=>$token($login),'action'=>'login','email'=>'customer-'.$key.'@example.test','password'=>$key]);
    foreach(['/rentals/admin/email',$path,'/rentals/admin/email/recipients','/rentals/admin/email/history'] as $read) { [$status]=$http($read); $assert($status===403,'Customer accessed '.$read); }
    [, $account]=$http('/rentals/account'); $post['_token']=$token($account);
    [$status]=$http($path,$post+['action'=>'test','test_email'=>$testEmail]); $assert($status===403,'Customer could invoke test mail.');
    echo "PASS: mounted email routes, status booleans, edit/save/preview, test failure history, recipient CRUD/subscriptions, visible validation, guest/customer restrictions and CSRF on every write.\n";
} finally {
    $db->delete('DELETE FROM rental_notification_deliveries WHERE event_key=\'test\' AND recipient_email=?',[$testEmail]);
    if($recipient) { $db->delete('DELETE FROM rental_notification_recipients WHERE id=?',[$recipient]); }
    $db->delete('DELETE FROM rental_email_templates WHERE event_key=? AND audience=\'customer\'',[$event]);
    if($original) { $db->insert('INSERT INTO rental_email_templates(id,event_key,audience,display_name,subject,body,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',array_values($original)); }
    foreach($ids as $id) { $db->delete('DELETE FROM carts WHERE user_id=?',[$id]); $db->delete('DELETE FROM users WHERE id=?',[$id]); }
    @unlink($jar);
}
