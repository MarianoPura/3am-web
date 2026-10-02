<?php
declare(strict_types=1);
// The existing SMTP client is exercised against a loopback-only fake server.
// No authentication credentials or external recipient/provider is used.
if (($argv[1]??'')==='--server') {
    $server=stream_socket_server('tcp://127.0.0.1:0',$code,$error);
    if(!$server) { exit(1); }
    echo stream_socket_get_name($server,false),"\n"; flush();
    $socket=stream_socket_accept($server,10); if(!$socket) { exit(1); }
    stream_set_timeout($socket,5); fwrite($socket,"220 local QA SMTP\r\n");
    $recipients=[]; $data=[];
    while(($line=fgets($socket))!==false) {
        if(str_starts_with($line,'EHLO')) { fwrite($socket,"250 local QA\r\n"); }
        elseif(str_starts_with($line,'MAIL FROM') || str_starts_with($line,'RCPT TO')) {
            if (str_starts_with($line,'RCPT TO')) { $recipients[]=trim($line); }
            fwrite($socket,"250 OK\r\n");
        }
        elseif(trim($line)==='DATA') {
            fwrite($socket,"354 Continue\r\n");
            while(($line=fgets($socket))!==false && trim($line)!=='.') { $data[]=$line; }
            if(($argv[2]??'')==='uncertain') { break; }
            fwrite($socket,($argv[2]??'')==='rejected'?"550 not accepted\r\n":"250 accepted\r\n");
        } elseif(trim($line)==='QUIT') {
            if(($argv[2]??'')!=='quit-failed') { fwrite($socket,"221 Bye\r\n"); }
            break;
        }
    }
    fclose($socket); fclose($server);
    echo json_encode(['recipients'=>$recipients,'data'=>implode('',$data)]),"\n"; exit;
}
$_ENV['MAIL_ENABLED']='false'; require dirname(__DIR__).'/bootstrap.php';
foreach(['success','quit-failed','uncertain','rejected'] as $scenario) {
    $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'--server',$scenario],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process)) { throw new RuntimeException('QA SMTP server could not start.'); }
    try {
        $address=trim((string)fgets($pipes[1])); $port=(int)substr(strrchr($address,':'),1);
        if($port<1) { throw new RuntimeException('QA SMTP listener unavailable.'); }
        $mailer=new App\Services\SmtpMailer(['enabled'=>true,'host'=>'127.0.0.1','port'=>$port,'encryption'=>'none','username'=>'','password'=>'','timeout'=>3,'from'=>['address'=>'sender@example.test','name'=>'QA sender']]);
        $exception=null;
        $transport=new App\Services\RentalSmtpTransport($mailer);
        try { $transport->send('recipient@example.test','QA SMTP test',str_repeat('Sample text ',20000),'<p>Sample HTML</p>', ['cc-one@example.test','cc-two@example.test','cc-one@example.test']); }
        catch(RuntimeException $e) { $exception=$e; }
        if(in_array($scenario,['success','quit-failed'],true) && $exception) { throw new RuntimeException('Acknowledged message incorrectly failed: '.$scenario); }
        if($scenario==='uncertain' && !$exception instanceof App\Services\SmtpDeliveryUncertain) { throw new RuntimeException('Unknown acceptance not distinguished.'); }
        if($scenario==='rejected' && (!$exception || $exception instanceof App\Services\SmtpDeliveryUncertain)) { throw new RuntimeException('Explicit rejection should be safely retryable.'); }
        $captured=json_decode((string)stream_get_contents($pipes[1]),true,512,JSON_THROW_ON_ERROR);
        if ($captured['recipients']!==['RCPT TO:<recipient@example.test>','RCPT TO:<cc-one@example.test>','RCPT TO:<cc-two@example.test>']
            || !str_contains($captured['data'],'To: recipient@example.test')
            || !str_contains($captured['data'],'Cc: cc-one@example.test, cc-two@example.test')) {
            throw new RuntimeException('Actual SMTP envelope/header CC was not forwarded or deduplicated.');
        }
    } finally { foreach($pipes as $pipe) { fclose($pipe); } proc_close($process); }
}
echo "PASS: SMTP adapter actual To/CC envelopes and headers, duplicate CC normalization, text/HTML delivery, accepted DATA despite failed QUIT, uncertain acceptance, explicit rejection; loopback only.\n";
