<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class PageController extends Controller
{
    public function services(Request $request): Response
    {
        return $this->render('pages.services')->cacheFor(300);
    }

    public function rentals(Request $request): Response
    {
        return $this->render('pages.rentals', [
            'categories' => config('rentals.categories', []),
            'items' => config('rentals.items', []),
        ])->cacheFor(300);
    }

    public function about(Request $request): Response
    {
        return $this->render('pages.about')->cacheFor(300);
    }

    public function contact(Request $request): Response
    {
        return $this->render('pages.contact')->cacheFor(300);
    }
}
