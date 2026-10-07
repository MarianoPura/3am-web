<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
$command=$argv[1]??'--check';
$sqlPath=dirname(__DIR__).'/docs/rentals-completion-additive.sql';
if($command==='--sql') { echo file_get_contents($sqlPath); exit; }
if(!in_array($command,['--check','--apply-local'],true)) { fwrite(STDERR,"Use --check, --sql, or --apply-local. No automatic live migration is supported.\n"); exit(1); }
$container=require dirname(__DIR__).'/bootstrap.php';
try {
    $db=$container->get(App\Core\Database::class);
    if($command==='--apply-local') {
        if(!in_array(config('app.env'),['local','testing'],true) || !in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost','::1'],true)) { throw new RuntimeException('Explicit local loopback database only.'); }
        $schema=new App\Services\RentalSchema($db);
        if(!$schema->hasTable('rental_service_requests')) { $db->statement(file_get_contents(BASE_PATH.'/docs/rentals-service-requests-additive.sql')); }
        $source=preg_replace('/^--.*$/m','',file_get_contents($sqlPath));
        foreach(explode(';',$source) as $statement) {
            $statement=trim($statement);
            if($statement==='') { continue; }
            if(str_starts_with($statement,'CREATE TABLE IF NOT EXISTS rental_password_resets')) {
                if(!$schema->hasTable('rental_password_resets')) { $db->statement($statement); echo "Created rental_password_resets.\n"; }
            } elseif(str_starts_with($statement,'ALTER TABLE rental_items')) {
                if(!$schema->hasColumn('rental_items','additional_image_paths')) { $db->statement($statement); echo "Added rental_items.additional_image_paths.\n"; }
            } elseif(preg_match('/^ALTER TABLE (rental_service_requests|rental_notification_deliveries)\s/', $statement,$target)) {
                $table=$target[1];
                $body=trim(substr($statement,strlen('ALTER TABLE '.$table)));
                $clauses=preg_split('/,\s*\n\s*(?=ADD )/',$body);
                $missing=[];
                foreach($clauses as $clause) {
                    if(preg_match('/^ADD COLUMN ([a-z_]+)/',$clause,$m)) { $exists=$schema->hasColumn($table,$m[1]); }
                    elseif(preg_match('/^ADD KEY ([a-z_]+)/',$clause,$m)) { $exists=(int)$db->selectValue('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',[$table,$m[1]])>0; }
                    elseif(preg_match('/^ADD CONSTRAINT ([a-z_]+)/',$clause,$m)) { $exists=(int)$db->selectValue('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=?',[$table,$m[1]])>0; }
                    else { throw new RuntimeException('Unrecognized additive clause.'); }
                    if(!$exists) { $missing[]=$clause; }
                }
                if($missing!==[]) { $db->statement('ALTER TABLE '.$table.' '.implode(', ',$missing)); echo "Added missing service workflow fields/keys.\n"; }
            } else { throw new RuntimeException('Unexpected migration statement.'); }
        }
    }
    $inspection=(new App\Services\RentalSchema($db))->inspect();
    foreach($inspection['issues'] as $issue) { fwrite(STDERR,"Review: $issue\n"); }
    if($inspection['issues']!==[]) { exit(1); }
    echo "PASS: Rentals schema contract.\n";
} catch(Throwable $e) { fwrite(STDERR,"Completion schema action failed. Verify local permissions/schema privately. No secrets printed.\n"); exit(1); }
