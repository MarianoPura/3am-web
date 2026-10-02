<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Services\RentalDiagnostic;

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
    public function productImage(Request $request, string $filename): Response
    {
        RentalDiagnostic::trace('product-image', 'start', ['filename_valid' => preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $filename) === 1]);
        $reference = \App\Services\RentalManagedImage::publicPath('micro/rentals/products/' . $filename, 'product', 'product-image');
        $path = $reference !== null ? \App\Services\RentalStorage::path($reference, 'product-image') : null;
        if ($path === null || ($bytes = @file_get_contents($path)) === false) { RentalDiagnostic::failure('product-image', 'file-read', ['file_exists' => $path !== null, 'file_readable' => $path !== null && is_readable($path), 'response_status' => 404]); return Response::notFound(); }
        $mime = match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream',
        };
        return Response::make($bytes)->withHeader('Content-Type', $mime)->withHeader('X-Content-Type-Options', 'nosniff');
    }

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
