<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;

/**
 * Public controller for the separate Rentals module.
 *
 * Only Rentals-specific public functionality belongs in this module.
 * The main 3AM website continues using its existing Web controllers.
 */
final class RentalsController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('rentals.index', $this->catalog())->noCache();
    }

    public function categories(Request $request): Response
    {
        return $this->render('rentals.categories', $this->catalog())->noCache();
    }

    public function items(Request $request): Response
    {
        return $this->render('rentals.items', $this->catalog())->noCache();
    }

    public function services(Request $request): Response
    {
        return $this->render('rentals.services', $this->catalog())->noCache();
    }

    public function howToRent(Request $request): Response
    {
        return $this->render('rentals.how-to-rent', $this->catalog())->noCache();
    }

    public function support(Request $request): Response
    {
        return $this->render('rentals.support', $this->catalog())->noCache();
    }

    private function catalog(): array
    {
        return (new RentalCatalog($this->db()))->load();
    }
}
