<?php
declare(strict_types=1);

// Local-only fixtures; no schema changes, real mail, or edits to existing records.
$_ENV['MAIL_ENABLED'] = 'false';
$container = require dirname(__DIR__) . '/bootstrap.php';
if (!in_array(config('app.env'), ['local', 'testing'], true)) { throw new RuntimeException('Local/testing only.'); }
$db = $container->get(App\Core\Database::class);
$key = bin2hex(random_bytes(6));
$userId = $categoryId = $itemId = $methodId = 0;
$orders = [];
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$request = static function (array $fields, bool $get = false): App\Core\Request {
    $_SERVER['REQUEST_METHOD'] = $get ? 'GET' : 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = $get ? [] : $fields; $_GET = $get ? $fields : [];
    return App\Core\Request::capture();
};
try {
    $userId = $db->insert('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)',
        ['[TEST] Latest QA '.$key, 'latest-'.$key.'@example.test', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'admin']);
    $_SESSION = ['user_id' => $userId];
    $categoryId = $db->insert('INSERT INTO rental_categories(name,slug) VALUES(?,?)', ['[TEST] Latest QA '.$key, 'latest-'.$key]);
    $itemId = $db->insert('INSERT INTO rental_items(category_id,name,slug,available_quantity,availability_status,rental_rate,rental_unit) VALUES(?,?,?,?,?,?,?)',
        [$categoryId, '[TEST] Latest QA equipment '.$key, 'latest-item-'.$key, 5, 'available', 100, 'day']);
    $methodId = $db->insert('INSERT INTO payment_methods(name,type,account_name,account_number) VALUES(?,?,?,?)',
        ['[TEST] Latest QA '.$key, 'manual', 'Test account', '000000']);
    $start = (new DateTimeImmutable('today'))->modify('+8 days')->format('Y-m-d');
    $end = (new DateTimeImmutable($start))->modify('+1 day')->format('Y-m-d');
    $addOrder = static function (int $status, float $subtotal, string $created, int $quantity = 2) use ($db, $key, $userId, $itemId, $methodId, $start, $end, &$orders): int {
        $id = $db->insert('INSERT INTO order_header(order_number,user_id,customer_name,customer_email,payment_method_id,payment_status,status_token,subtotal,security_deposit,total_amount,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
            ['QA-LATEST-'.$key.'-'.count($orders), $userId, '[TEST] Latest QA', 'latest-'.$key.'@example.test', $methodId,
                $status, bin2hex(random_bytes(32)), $subtotal, 1000, $subtotal+1000, $created]);
        $orders[] = $id;
        $db->insert('INSERT INTO order_details(order_header_id,rental_item_id,item_name,quantity,rental_start_date,rental_end_date,unit_rate,line_total) VALUES(?,?,?,?,?,?,?,?)',
            [$id, $itemId, '[TEST] Latest equipment', $quantity, $start, $end, 100, $subtotal]);
        return $id;
    };
    $reservation = $addOrder(0, 400, date('Y-m-d H:i:s'));
    $cartController = new App\Controllers\Rentals\RentalCartController($container);
    $admin = new App\Controllers\Rentals\RentalAdminController($container);
    $cart = new App\Services\RentalCart();
    $catalog = new App\Models\RentalCatalog($db);
    $record = $catalog->find((string)$itemId);
    $availability = static function (int|string $quantity, ?string $line = null) use ($cartController, $request, $itemId, $start): App\Core\Response {
        return $cartController->availability($request(['id'=>(string)$itemId, 'month'=>substr($start,0,7), 'quantity'=>(string)$quantity, 'line'=>$line??''], true));
    };
    $dayFor = static function (App\Core\Response $response, string $date): array {
        return array_values(array_filter(json_decode($response->body(),true)['days'],static fn($day)=>$day['date']===$date))[0];
    };
    $day = $dayFor($availability(1),$start);
    $assert($day['remaining']===3 && $day['reserved']===2 && $day['available'], 'Stock 5/reserved 2 did not leave 3 units.');
    $adminCalendar = static fn(string $date): App\Core\Response => $admin->availability($request(['month'=>substr($date,0,7)],true),(string)$itemId);
    $today = new DateTimeImmutable('today');
    $yesterday = $today->modify('-1 day')->format('Y-m-d');
    $pastDay = $dayFor($adminCalendar($yesterday),$yesterday);
    $assert($pastDay['past'] && !$pastDay['available'] && $pastDay['remaining']===5,
        'Admin treated a past date as rentable or lost historical stock information.');
    $todayDay = $dayFor($adminCalendar($today->format('Y-m-d')),$today->format('Y-m-d'));
    $assert(!$todayDay['past'] && $todayDay['available'], 'Admin incorrectly marked today as a past date.');
    $adminReserved = $dayFor($adminCalendar($start),$start);
    $assert(!$adminReserved['past'] && $adminReserved['available'] && $adminReserved['remaining']===3 && $adminReserved['reserved']===2,
        'Admin lost remaining units on a partially reserved date.');
    echo "PASS: Admin past dates unavailable; today still available; partial reservations retain remaining capacity and historical stock.\n";
    $assert(!$dayFor($availability(4),$start)['available'], 'Availability API allowed more than remaining stock.');
    $assert($availability('1.5')->status()===422 && $availability('1000')->status()===422, 'Availability accepted an invalid quantity.');
    $fields = ['id'=>(string)$itemId,'quantity'=>'6','rental_start_date'=>$start,'rental_end_date'=>$end];
    $assert($cartController->add($request($fields))->status()===422 && $cart->empty(), 'Manipulated Add to Cart oversold.');
    $fields['quantity']='1.5';
    $assert($cartController->add($request($fields))->status()===422 && $cart->empty(), 'Fractional quantity accepted.');
    $fields['quantity']='3';
    $assert($cartController->add($request($fields))->status()===200, 'Valid remaining quantity rejected.');
    $line = array_values($cart->contents())[0];
    $assert($dayFor($availability(1),$start)['remaining']===0, 'Modal ignored units already in own cart.');
    $assert($dayFor($availability(1,$line['line_id']),$start)['remaining']===3, 'Cart self-exclusion lost its own capacity.');
    $cartPage = $cartController->index($request([],true))->body();
    $assert(str_contains($cartPage,'max="3"') && str_contains($cartPage,'3 units available'), 'Cart did not show selected-date maximum.');
    $fields['line']=$line['line_id'];$fields['quantity']='4';
    $cartController->update($request($fields));
    $assert(array_values($cart->contents())[0]['quantity']===3, 'Cart update exceeded selected-date stock.');
    $fields['quantity']='0';$cartController->update($request($fields));
    $assert(array_values($cart->contents())[0]['quantity']===3, 'Cart silently converted invalid quantity.');
    $db->update('UPDATE rental_items SET available_quantity=2 WHERE id=?',[$itemId]);
    $beforeOrders = (int)$db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id=?',[$userId]);
    try {
        (new App\Services\RentalCheckout($db,$cart))->createOrder($userId,['name'=>'[TEST] QA','email'=>'ignored@example.test','phone'=>'','payment_method_id'=>$methodId]);
        throw new LogicException('Stale checkout unexpectedly succeeded.');
    } catch (RuntimeException $e) {
        $assert(str_contains($e->getMessage(),'no longer available'), 'Checkout failed for a reason other than stock.');
    }
    $assert((int)$db->selectValue('SELECT COUNT(*) FROM order_header WHERE user_id=?',[$userId])===$beforeOrders, 'Failed checkout saved an order.');
    $cart->clear();$db->update('UPDATE rental_items SET available_quantity=5 WHERE id=?',[$itemId]);
    echo "PASS: date capacity 5-2=3; API invalid/overstock quantities; cart self-exclusion; Add/Update tampering; stale checkout rejected without new order.\n";

    $base = new DateTimeImmutable('2026-10-10');
    $d = static fn(int $offset): string => $base->modify(($offset>=0?'+':'').$offset.' days')->format('Y-m-d');
    $cases = [
        'full'=>[[0,5],[0,5],[]], 'trim-start'=>[[0,5],[0,1],[[2,5]]],
        'trim-end'=>[[0,5],[4,5],[[0,3]]], 'split'=>[[0,5],[2,3],[[0,1],[4,5]]],
        'one-day'=>[[0,0],[0,0],[]], 'month-boundary'=>[[20,23],[21,22],[[20,20],[23,23]]],
    ];
    $reservationBefore=$db->select('SELECT * FROM order_details WHERE order_header_id=?',[$reservation]);
    foreach ($cases as $name=>[$block,$remove,$expected]) {
        $db->delete('DELETE FROM rental_item_blackouts WHERE rental_item_id=?',[$itemId]);
        $admin->saveBlackout($request(['start_date'=>$d($block[0]),'end_date'=>$d($block[1]),'availability_action'=>'blocked']),(string)$itemId);
        $admin->saveBlackout($request(['start_date'=>$d($remove[0]),'end_date'=>$d($remove[1]),'availability_action'=>'available']),(string)$itemId);
        $actual=$db->select('SELECT start_date,end_date FROM rental_item_blackouts WHERE rental_item_id=? AND is_active=1 ORDER BY start_date',[$itemId]);
        $want=array_map(static fn($range)=>['start_date'=>$d($range[0]),'end_date'=>$d($range[1])],$expected);
        $assert($actual===$want,'Blackout '.$name.' removal changed coverage.');
    }
    $db->delete('DELETE FROM rental_item_blackouts WHERE rental_item_id=?',[$itemId]);
    foreach ([[0,4],[2,6]] as $block) {$admin->saveBlackout($request(['start_date'=>$d($block[0]),'end_date'=>$d($block[1])]),(string)$itemId);}
    $admin->saveBlackout($request(['start_date'=>$d(3),'end_date'=>$d(3),'availability_action'=>'available']),(string)$itemId);
    $days=$catalog->availabilityByDate($record,$d(0),$d(6));
    foreach ($days as $date=>$day) {$assert($day['admin_blocked']===($date!==$d(3)),'Overlapping blackout removal coverage mismatch.');}
    $assert($reservationBefore===$db->select('SELECT * FROM order_details WHERE order_header_id=?',[$reservation]),'Unblock changed customer reservation.');
    $db->delete('DELETE FROM rental_item_blackouts WHERE rental_item_id=?',[$itemId]);
    $admin->saveBlackout($request(['start_date'=>'2026-10-10','end_date'=>'2026-10-12']),(string)$itemId);
    $days=$catalog->availabilityByDate($record,'2026-10-09','2026-10-13');
    $assert(array_keys(array_filter($days,static fn($day)=>$day['admin_blocked']))===['2026-10-10','2026-10-11','2026-10-12'],'Exact October 10–12 parity expanded.');
    echo "PASS: manual unblock full/start/end/split/single/month-boundary/overlapping rows; reservation snapshots unchanged; exact October 10–12 coverage.\n";

    // Choose an empty historical interval so real local transactions are never modified.
    $year=2002;
    while ($year<2030 && (int)$db->selectValue('SELECT COUNT(*) FROM order_header WHERE created_at>=? AND created_at<?',[$year.'-02-01',($year+1).'-01-01'])>0) {$year++;}
    $assert($year<2030,'No isolated analytics fixture interval.');
    $from=$year.'-02-01';$to=$year.'-02-05';
    foreach ([[1,100,'01 00:00:00'],[1,50,'01 23:59:59'],[0,9000,'02 12:00:00'],[2,8000,'02 13:00:00'],[1,.5,'05 23:59:59'],[1,10000,'06 00:00:00']] as [$state,$amount,$day]) {
        $addOrder($state,$amount,$year.'-02-'.$day,1);
    }
    $insights=new App\Services\RentalAdminInsights($db);
    $report=$insights->analytics($request(['period'=>'custom','from'=>$from,'to'=>$to],true));
    $assert(count($report['daily'])===5 && (float)$report['kpis']['sales']===150.5 && (int)$report['kpis']['approved_orders']===3,'Revenue totals/range wrong.');
    $assert(array_map(static fn($day)=>(float)$day['sales'],$report['daily'])===[150.0,0.0,0.0,0.0,.5], 'Missing zero days or wrong approved-only aggregation.');
    $assert(array_sum(array_column($report['daily'],'sales'))==(float)$report['kpis']['sales'],'Chart does not sum to KPI.');
    $sales=$insights->salesReport($request(['from'=>$from,'to'=>$to],true));
    $payment=$insights->paymentReport($request(['from'=>$from,'to'=>$to],true));
    $assert((float)$sales['totals']['approved_sales']===150.5 && (float)$payment['totals']['rental_amount']===150.5,'Chart/report revenue definitions differ.');
    $view=$container->get(App\Core\View::class);
    $html=$view->partial('rentals.partials.admin-analytics',['insights'=>$report]);
    $assert(str_contains($html,'Highest day '.$from.': ₱150.00') && str_contains($html,'viewBox="0 0 600 232"') && str_contains($html,'data-chart-period'),'Highest day or responsive date axis incorrect.');
    $small=$insights->analytics($request(['period'=>'custom','from'=>$to,'to'=>$to],true));
    $assert(str_contains($view->partial('rentals.partials.admin-analytics',['insights'=>$small]),'₱0.50'),'Highest revenue artificially rounded up to ₱1.00.');
    $empty=$insights->analytics($request(['period'=>'custom','from'=>$year.'-03-01','to'=>$year.'-03-02'],true));
    $assert(str_contains($view->partial('rentals.partials.admin-analytics',['insights'=>$empty]),'No approved rental revenue'),'Empty revenue chart lacks intentional state.');
    $monthly=$insights->analytics($request(['period'=>'custom','from'=>$from,'to'=>($year+1).'-03-01'],true));
    $assert($monthly['aggregation']==='month' && array_sum(array_column($monthly['daily'],'sales'))==(float)$monthly['kpis']['sales'],'Monthly buckets changed totals.');
    echo "PASS: real approved subtotals; start/end boundaries; Pending/Rejected/deposits excluded; zero days filled; report totals match; highest/sub-peso/empty states; monthly grouping deterministic.\n";
} finally {
    if ($itemId) {$db->delete('DELETE FROM rental_item_blackouts WHERE rental_item_id=?',[$itemId]);}
    foreach ($orders as $id) {$db->delete('DELETE FROM order_details WHERE order_header_id=?',[$id]);$db->delete('DELETE FROM order_header WHERE id=?',[$id]);}
    if ($userId) {$db->delete('DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id=?)',[$userId]);$db->delete('DELETE FROM carts WHERE user_id=?',[$userId]);}
    if ($itemId) {$db->delete('DELETE FROM rental_items WHERE id=?',[$itemId]);}
    if ($categoryId) {$db->delete('DELETE FROM rental_categories WHERE id=?',[$categoryId]);}
    if ($methodId) {$db->delete('DELETE FROM payment_methods WHERE id=?',[$methodId]);}
    if ($userId) {$db->delete('DELETE FROM users WHERE id=?',[$userId]);}
    $_SESSION=[];
}
