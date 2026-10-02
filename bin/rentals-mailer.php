<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$command = $argv[1] ?? '--check';
$sql = file_get_contents(dirname(__DIR__) . '/database/rentals-mailer-additive.sql');
if ($command === '--sql') { echo $sql; exit; }
if (!in_array($command, ['--check','--migrate'], true)) { fwrite(STDERR,"Use --check, --sql, or --migrate (local/testing only).\n"); exit(1); }
$container = require dirname(__DIR__) . '/bootstrap.php';
if ($command === '--migrate' && !in_array(config('app.env'), ['local','testing'], true)) { fwrite(STDERR,"Local/testing only. No SQL executed.\n"); exit(1); }
try {
    $db = $container->get(App\Core\Database::class);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)` \((.*?)\) ENGINE=InnoDB[^;]*;/s',$sql,$definitions,PREG_SET_ORDER);
    if (count($definitions)!==1) { throw new RuntimeException('Invalid additive schema.'); }
    $existing = array_map(static fn($r)=>(string)reset($r),$db->select('SHOW TABLES'));
    $missing=[]; $issues=[];
    foreach ($definitions as $definition) {
        $table=$definition[1];
        if (!in_array($table,$existing,true)) { $missing[]=$definition; continue; }
        $columns=array_column($db->select('SHOW COLUMNS FROM `'.$table.'`'),null,'Field');
        preg_match_all('/^\s*`([a-z_]+)` ([^\n]+)/m',$definition[2],$fields,PREG_SET_ORDER);
        foreach ($fields as $field) {
            if (!isset($columns[$field[1]])) { $issues[]=$table.'.'.$field[1].' missing'; }
            elseif (str_contains($field[2],'AUTO_INCREMENT') && !str_contains($columns[$field[1]]['Extra'],'auto_increment')) { $issues[]=$table.'.'.$field[1].' must auto-increment'; }
        }
        $indexes=$db->select('SHOW INDEX FROM `'.$table.'`');
        preg_match_all('/(?:UNIQUE KEY|PRIMARY KEY)(?: `[^`]+`)? \(([^)]+)\)/',$definition[2],$unique,PREG_SET_ORDER);
        foreach ($unique as $key) {
            preg_match_all('/`([a-z_]+)`/',$key[1],$parts); $found=false; $grouped=[];
            foreach ($indexes as $index) { if ((int)$index['Non_unique']===0) { $grouped[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name']; } }
            foreach ($grouped as $group) { ksort($group); if (array_values($group)===$parts[1]) { $found=true; } }
            if (!$found) { $issues[]=$table.' missing unique key '.implode(',',$parts[1]); }
        }
        preg_match_all('/FOREIGN KEY \(`([a-z_]+)`\)\s+REFERENCES `([a-z_]+)` \(`([a-z_]+)`\)/',$definition[2],$foreign,PREG_SET_ORDER);
        foreach ($foreign as $fk) {
            if (!$db->selectValue('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=?',[$table,$fk[1],$fk[2],$fk[3]])) { $issues[]=$table.'.'.$fk[1].' missing foreign key'; }
        }
    }
    if ($issues) { foreach($issues as $issue) { fwrite(STDERR,'Review: '.$issue."\n"); } exit(1); }
    foreach ($missing as $definition) {
        if ($command==='--migrate') { $db->statement($definition[0]); echo 'Created '.$definition[1]."\n"; }
        else { echo 'Missing: '.$definition[1]."\n"; }
    }
    if ($missing && $command==='--check') { exit(1); }
    echo "Mailer delivery ledger, unique delivery key and order relationship checked. Existing business records preserved.\n";
} catch (Throwable $e) { fwrite(STDERR,"Mailer schema check failed. Verify database configuration and permissions privately.\n"); exit(1); }
