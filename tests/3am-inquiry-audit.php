<?php
declare(strict_types=1);

// Captured loopback SMTP only. This listener never loads real mail settings.
if (($argv[1] ?? '') === '--smtp-server') {
    $scenario = $argv[2] ?? 'success';
    $server = stream_socket_server('tcp://127.0.0.1:0', $code, $error);
    if (!$server) { exit(1); }
    echo stream_socket_get_name($server, false), "\n"; flush();
    $messages = [];
    for ($attempt = 0; $attempt < ($scenario === 'success' ? 1 : 2); $attempt++) {
        $socket = @stream_socket_accept($server, 4);
        if (!$socket) { break; }
        stream_set_timeout($socket, 4);
        fwrite($socket, "220 local captured SMTP\r\n");
        $recipients = []; $data = '';
        while (($line = fgets($socket)) !== false) {
            if (str_starts_with($line, 'EHLO')) { fwrite($socket, "250 local QA\r\n"); }
            elseif (str_starts_with($line, 'MAIL FROM')) { fwrite($socket, "250 OK\r\n"); }
            elseif (str_starts_with($line, 'RCPT TO')) {
                $recipients[] = trim($line);
                $reject = $scenario === 'rejected' || ($scenario === 'fallback' && $attempt === 0);
                fwrite($socket, $reject ? "550 fixture destination rejected\r\n" : "250 OK\r\n");
                if ($reject) { break; }
            } elseif (trim($line) === 'DATA') {
                fwrite($socket, "354 Continue\r\n");
                while (($line = fgets($socket)) !== false && trim($line) !== '.') { $data .= $line; }
                if ($scenario === 'uncertain') { break; }
                fwrite($socket, "250 captured locally\r\n");
            } elseif (trim($line) === 'QUIT') { fwrite($socket, "221 Bye\r\n"); break; }
        }
        fclose($socket); $messages[] = compact('recipients', 'data');
    }
    fclose($server); echo json_encode($messages, JSON_THROW_ON_ERROR), "\n"; exit;
}

define('RENTALS_DIAGNOSTIC_READ_ONLY', true);
$c = require dirname(__DIR__) . '/bootstrap.php';
$connection = config('database.connections.mysql');
if (PHP_SAPI !== 'cli' || config('app.env') !== 'local' || $connection['host'] !== '127.0.0.1'
    || (int)$connection['port'] !== 3306 || $connection['database'] !== 'd3am_rentals_new' || config('mail.enabled') !== false) {
    throw new RuntimeException('Exact HOME target and disabled outgoing mail required.');
}
$db = $c->get(App\Core\Database::class);
if ($db->selectValue('SELECT DATABASE()') !== 'd3am_rentals_new') { throw new RuntimeException('Database identity mismatch.'); }
$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void {
    if (!$value) { throw new RuntimeException($message); } $checks++;
};
$root = BASE_PATH . '/storage/tmp/inquiry-audit-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$originalBackup = BASE_PATH . '/storage/inquiries/inquiries.jsonl';
$backupHash = hash_file('sha256', $originalBackup);
$ip = '2001:db8:' . bin2hex(random_bytes(2)) . ':' . bin2hex(random_bytes(2)) . '::1';
$rateFile = BASE_PATH . '/storage/cache/throttle/' . hash('sha256', $ip) . '.json';
if (is_file($rateFile)) { throw new RuntimeException('Refusing to overwrite existing throttle fixture.'); }
    $directories = ['capture', 'file-only', 'success', 'fallback', 'rejected', 'uncertain'];
$baseline = [];
foreach ($db->select('SHOW TABLES') as $row) {
    $table = (string) reset($row);
    if (!preg_match('/^[a-z_]+$/D', $table)) { throw new RuntimeException('Unexpected table identifier.'); }
    $baseline[$table] = (int)$db->selectValue('SELECT COUNT(*) FROM ' . $table);
}
$transaction = false;
try {
    $view = $c->get(App\Core\View::class);
    $referenceStore = new App\Services\InquiryStore($root . '/file-only', 'QA');
    $referenceMethod = new ReflectionMethod($referenceStore, 'reference'); $seen = [];
    for ($n = 0; $n < 4096; $n++) {
        $reference = $referenceMethod->invoke($referenceStore);
        $assert((bool)preg_match('/^3AM-[0-9]{4}-[A-F0-9]{12}$/D', $reference), 'Reference entropy/format incorrect.');
        $seen[$reference] = true;
    }
    $assert(count($seen) === 4096, 'Generated references collided in the regression sample.');
    $assert((int)$db->selectValue("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inquiries' AND COLUMN_NAME='reference'") >= strlen($reference), 'New reference needs a schema change.');

    $db->beginTransaction(); $transaction = true; $_SESSION = [];
    $tracking = new App\Services\TrackingService($db);
    $store = new App\Services\InquiryStore($root . '/capture', 'QA company', $db, null, $tracking, [], [], $view);
    $c->set(App\Services\InquiryStore::class, $store);
    $c->set(App\Services\TrackingService::class, $tracking);
    $controller = new App\Controllers\Web\InquiryController($c);
    $request = static function (array $fields = [], bool $ajax = true, array $query = []) use ($ip): App\Core\Request {
        $_GET = $query; $_POST = $fields; $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = $fields === [] ? 'GET' : 'POST';
        $_SERVER['REQUEST_URI'] = '/3am-web/start'; $_SERVER['SCRIPT_NAME'] = '/3am-web/index.php';
        $_SERVER['REMOTE_ADDR'] = $ip; $_SERVER['HTTP_USER_AGENT'] = 'Local enquiry audit';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
        return App\Core\Request::capture('/3am-web');
    };
    $valid = ['type'=>'Media / Production','name'=>'[TEST] General enquiry','email'=>'inquiry-audit@example.test',
        'phone'=>'+63 900 000 0000','details'=>'Fictional project, location, requirements and event dates.','_t'=>time()-10];
    foreach (['media'=>'Media / Production','technology'=>'Technology / Event Systems','rentals'=>'Equipment Rentals','other'=>'Other / General Inquiry'] as $preset=>$label) {
        $html = $controller->show($request([], false, ['type'=>$preset]))->body();
        $assert(substr_count($html, 'type="radio"') === 4 && str_contains($html, 'value="'.$label.'" required checked'), 'Preset/options incorrect.');
    }
    foreach ([['email'=>'bad'],['type'=>'unsupported'],['name'=>''],['phone'=>''],['details'=>'short'],['email'=>['unexpected']]] as $change) {
        $response = $controller->submit($request(array_replace($valid, $change)));
        $assert($response->status() === 422 && json_decode($response->body(),true)['ok'] === false, 'Invalid field was accepted.');
    }
    $assert($controller->submit($request(array_replace($valid, ['_t'=>time()])))->status() === 422, 'Minimum-time check failed.');
    $before = (int)$db->selectValue('SELECT COUNT(*) FROM inquiries');
    $response = $controller->submit($request(array_replace($valid, ['company_website'=>'fixture bot'])));
    $assert($response->status() === 200 && (int)$db->selectValue('SELECT COUNT(*) FROM inquiries') === $before, 'Honeypot persisted a lead.');
    $references = [];
    foreach (config('forms.project.types') as $label) {
        $response = $controller->submit($request(array_replace($valid, ['type'=>$label])));
        $result = json_decode($response->body(),true,512,JSON_THROW_ON_ERROR); $references[]=$result['reference']??'';
        $assert($response->status() === 200 && ($result['ok']??false), 'Valid category failed persistence.');
        $assert((int)$db->selectValue('SELECT COUNT(*) FROM inquiries WHERE reference=? AND type=?',[$result['reference'],$label]) === 1, 'Inquiry row/type not persisted.');
    }
    $assert(count(array_unique($references)) === 4, 'Current option submissions reused a reference.');
    $assert((int)$db->selectValue("SELECT COUNT(*) FROM tracking_events WHERE event_name IN ('Lead','Contact')") === 8, 'Attribution events missing.');
    $response = $controller->submit($request($valid, false));
    $assert($response->status() === 302 && str_ends_with($response->headers()['Location'], '/start/received'), 'Post/redirect/get confirmation failed.');
    $assert(str_contains($controller->received($request())->body(), '3AM-'), 'Confirmation lost its reference.');
    $assert(!isset($_SESSION['_inquiry_reference']), 'Confirmation session reference not consumed.');
    $assert($controller->submit($request($valid))->status() === 429, 'Five-per-hour limit failed.');
    $assert(count(file($root.'/capture/inquiries.jsonl',FILE_IGNORE_NEW_LINES)) === 5, 'JSONL mirror missing or invalid requests persisted.');
    $csrf = $c->get(App\Core\Csrf::class); $token=$csrf->token();
    $assert(!$csrf->verify($request(['_token'=>'invalid'])) && $csrf->verify($request(['_token'=>$token])), 'CSRF verification failed.');
    $db->rollBack(); $transaction = false;

    $record = ['type'=>'Technology / Event Systems','name'=>'<script>fixture</script>','email'=>'client@example.test',
        'phone'=>'+63 900 000 0000','details'=>'Fictional technology and general project requirements.'];
    $a=$referenceStore->capture($record); $b=$referenceStore->capture($record);
    $assert($a !== $b && count(file($root.'/file-only/inquiries.jsonl',FILE_IGNORE_NEW_LINES)) === 2, 'File fallback/repeated-submission characterization failed.');
    foreach (['success','fallback','rejected','uncertain'] as $scenario) {
        $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'--smtp-server',$scenario],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) { throw new RuntimeException('Local SMTP fixture could not start.'); }
        try {
            $address=trim((string)fgets($pipes[1])); $port=(int)substr(strrchr($address,':'),1);
            if ($port < 1 || !str_starts_with($address,'127.0.0.1:')) { throw new RuntimeException('SMTP fixture not loopback.'); }
            $mailer=new App\Services\SmtpMailer(['enabled'=>true,'host'=>'127.0.0.1','port'=>$port,'encryption'=>'none','username'=>'','password'=>'','timeout'=>3,'from'=>['address'=>'sender@example.test','name'=>'3AM Digital Media']]);
            $mailStore=new App\Services\InquiryStore($root.'/'.$scenario,'QA company',null,$mailer,null,['to'=>'owner@example.test','cc'=>['owner@example.test','team@example.test'],'reply_to'=>'reply@example.test'],[],$view);
            $ref=$mailStore->capture($record);
            $captured=json_decode((string)stream_get_contents($pipes[1]),true,512,JSON_THROW_ON_ERROR);
            $assert(count($captured) === (in_array($scenario,['success','uncertain'],true) ? 1 : 2), 'Expected confirmation/fallback attempts missing or uncertain delivery resent.');
            if ($scenario === 'success') {
                $message=$captured[0];
                $assert($message['recipients'] === ['RCPT TO:<client@example.test>','RCPT TO:<owner@example.test>','RCPT TO:<team@example.test>'], 'Customer/company envelope incorrect.');
                $assert(str_contains($message['data'],'From: 3AM Digital Media <sender@example.test>') && str_contains($message['data'],'Reply-To: reply@example.test'), 'Sender or Reply-To incorrect.');
                preg_match('/^Subject: (.+)$/m',$message['data'],$subject);
                $assert(str_contains(mb_decode_mimeheader(trim($subject[1])), 'We received your quote inquiry — '.$ref), 'Dynamic confirmation subject incorrect.');
                preg_match_all('/Content-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+?)(?=\r\n--)/',$message['data'],$parts);
                $decoded=implode("\n",array_map('base64_decode',$parts[1]));
                $html=base64_decode($parts[1][1]??'');
                $assert(str_contains($decoded,'Project Details:') && !str_contains($decoded,'our production team') && str_contains($html,'&lt;script&gt;fixture&lt;/script&gt;') && !str_contains($html,'<script>fixture</script>'), 'General wording or HTML escaping incorrect.');
            } elseif ($scenario === 'fallback') {
                $assert($captured[1]['recipients'] === ['RCPT TO:<owner@example.test>','RCPT TO:<team@example.test>'] && $captured[1]['data'] !== '', 'Owner-in-CC fallback failed or duplicated owner envelope.');
            } elseif ($scenario === 'uncertain') { $assert($captured[0]['data'] !== '' && is_file($root.'/'.$scenario.'/inquiries.jsonl'), 'Uncertain acceptance lost the lead.'); }
            else { $assert($captured[1]['data'] === '' && is_file($root.'/'.$scenario.'/inquiries.jsonl'), 'SMTP failure lost persisted inquiry.'); }
        } finally { foreach($pipes as $pipe) { fclose($pipe); } proc_close($process); }
    }
    foreach ($baseline as $table=>$count) { $assert((int)$db->selectValue('SELECT COUNT(*) FROM '.$table) === $count, 'Existing table count changed.'); }
    $assert(hash_file('sha256',$originalBackup) === $backupHash, 'Existing inquiry backup changed.');
    $assert(config('mail.enabled') === false, 'Local outgoing mail became enabled.');
    echo 'PASS: '.$checks.' enquiry assertions; four options/presets, validation, spam/CSRF, transactional persistence/tracking, PRG, rate limit, collision-resistant references, captured SMTP/owner fallback/failure; existing rows and backup preserved. Repeated valid POSTs remain separate leads; no deduplication policy invented.',"\n";
} finally {
    if ($transaction) { $db->rollBack(); }
    if (is_file($rateFile)) { unlink($rateFile); }
    foreach ($directories as $directory) {
        $path=$root.'/'.$directory;
        if (is_file($path.'/inquiries.jsonl')) { unlink($path.'/inquiries.jsonl'); }
        if (is_dir($path)) { rmdir($path); }
    }
    if (is_dir($root)) { rmdir($root); } $_SESSION=[];
}
