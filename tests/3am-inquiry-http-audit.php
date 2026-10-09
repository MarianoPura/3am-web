<?php
declare(strict_types=1);
define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$c=require dirname(__DIR__).'/bootstrap.php';
$base='http://127.0.0.1/3am-web';
$connection=config('database.connections.mysql');
if(PHP_SAPI!=='cli'||config('app.env')!=='local'||$connection['host']!=='127.0.0.1'||$connection['database']!=='d3am_rentals_new'||(int)$connection['port']!==3306||config('mail.enabled')!==false) { throw new RuntimeException('Exact local target and disabled mail required.'); }
$db=$c->get(App\Core\Database::class);
if($db->selectValue('SELECT DATABASE()')!=='d3am_rentals_new') { throw new RuntimeException('Identity mismatch.'); }
$before=(int)$db->selectValue('SELECT COUNT(*) FROM inquiries');
$backupHash=hash_file('sha256',BASE_PATH.'/storage/inquiries/inquiries.jsonl');
$jar=tempnam(BASE_PATH.'/storage/tmp','inquiry-http-');
$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void { if(!$ok)throw new RuntimeException($message);$checks++; };
$http=static function(string $path,?array $post=null)use($base,$jar):array {
    $curl=curl_init($base.$path);$headers=[];
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>10,
        CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$headers):int { $parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line); }]);
    if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($curl);if($body===false)throw new RuntimeException('Loopback enquiry request failed.');
    $result=[(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$body,$headers];curl_close($curl);return $result;
};
try {
    [$status,$home]=$http('/rentals');
    $assert($status===200&&preg_match('~href="[^"]*/start\?type=rentals"[^>]*>\s*Contact Rental Support~',$home)===1,'Rental Support CTA does not reach enquiry.');
    $homeDocument=new DOMDocument();
    @$homeDocument->loadHTML($home);
    $homeLinks=[];
    foreach($homeDocument->getElementsByTagName('a')as$link){
        $homeLinks[]=['text'=>trim(preg_replace('/\s+/u',' ',$link->textContent)??''),'href'=>$link->getAttribute('href')];
    }
    $removedLinks=array_filter($homeLinks,static fn(array $link):bool=>preg_match('/^(All categories|Browse inventory)(?:\s|$)/i',$link['text'])===1);
    $assert($removedLinks===[],'Removed homepage section actions are still rendered.');
    $catalogueLinks=array_filter($homeLinks,static fn(array $link):bool=>$link['text']==='Equipment & Services'&&str_ends_with($link['href'],'/rentals/items'));
    $assert($catalogueLinks!==[],'Main Equipment & Services catalogue navigation lost.');
    foreach(['media'=>'Media / Production','technology'=>'Technology / Event Systems','rentals'=>'Equipment Rentals','other'=>'Other / General Inquiry']as$preset=>$label){
        [$status,$form,$headers]=$http('/start?type='.$preset);
        $assert($status===200&&substr_count($form,'type="radio"')===4&&str_contains($form,'value="'.$label.'" required checked'),'HTTP category preset failed.');
        $assert(str_contains($headers['cache-control']??'','no-store'),'Inquiry response is cacheable.');
    }
    preg_match('/name="_token" value="([^"<>]+)"/',$form,$token);
    $assert(isset($token[1]),'Missing enquiry CSRF field.');
    [$status]=$http('/start',['type'=>'invalid']);
    $assert($status===419,'Enquiry POST bypassed global CSRF protection.');
    [$status,,$headers]=$http('/start',['_token'=>$token[1],'_t'=>time()-10,'type'=>'invalid','name'=>'<script>audit</script>','email'=>'invalid','phone'=>'','details'=>'']);
    $assert($status===302&&str_ends_with($headers['location']??'','/3am-web/start'),'Invalid native form did not redirect safely.');
    [$status,$invalid]=$http('/start');
    $assert($status===200&&str_contains($invalid,'id="email-error"')&&str_contains($invalid,'id="phone-error"')&&str_contains($invalid,'id="details-error"'),'Linked validation errors absent.');
    $assert(!str_contains($invalid,'<script>audit</script>')&&str_contains($invalid,'&lt;script&gt;audit&lt;/script&gt;'),'Old form fields are not escaped.');
    [$status,,$headers]=$http('/start/media');
    $assert($status===302&&str_ends_with($headers['location']??'','/start?type=media'),'Legacy nested redirect incorrect.');
    [$status]=$http('/storage/inquiries/inquiries.jsonl');
    $assert($status===403,'Private inquiry backup is publicly accessible.');
    $assert((int)$db->selectValue('SELECT COUNT(*) FROM inquiries')===$before&&hash_file('sha256',BASE_PATH.'/storage/inquiries/inquiries.jsonl')===$backupHash,'Read/invalid-submission HTTP checks changed inquiry data.');
    echo 'PASS: '.$checks.' local HTTP assertions; Rental Support destination, homepage action removal and retained catalogue navigation, four presets, no-cache, CSRF419, invalid-form feedback/XSS escaping, mounted legacy redirect and private backup403; no valid enquiry submitted.',"\n";
} finally { if(is_file($jar))unlink($jar); }
