<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

final class RentalPagination
{
    /** SQL/bindings are internal constants; never accept SQL from browser input. */
    public static function fetch(Database $db,string $sql,array $bindings,int $requestedPage,int $perPage=25): array
    {
        $perPage=max(1,min(100,$perPage));
        $total=(int)$db->selectValue('SELECT COUNT(*) FROM (' . $sql . ') rental_page_count',$bindings);
        $pages=max(1,(int)ceil($total/$perPage));
        $page=max(1,min($pages,$requestedPage));
        $offset=($page-1)*$perPage;
        return ['rows'=>$db->select($sql.' LIMIT '.$perPage.' OFFSET '.$offset,$bindings),
            'total'=>$total,'page'=>$page,'pages'=>$pages,'perPage'=>$perPage];
    }
}
