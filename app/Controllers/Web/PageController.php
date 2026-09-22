<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;

final class PageController extends Controller
{
    public function services(Request $request): Response
    {
        return $this->render('pages.services')->cacheFor(300);
    }

    public function information(Request $request): Response
    {
        return $this->render('pages.information')->cacheFor(300);
    }

    public function rentals(Request $request): Response
    {
        $catalog = (new RentalCatalog($this->db()))->load();

        return $this->render('pages.rentals', $catalog)->noCache();
    }

    public function legacyInformation(Request $request): Response
    {
        return $this->redirect('/rentals', 301);
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
