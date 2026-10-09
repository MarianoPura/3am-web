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
    public function productImage(Request $request, string $filename): Response
    {
        $reference = \App\Services\RentalManagedImage::publicPath('micro/rentals/products/' . $filename, 'product');
        $path = $reference !== null ? \App\Services\RentalStorage::path($reference) : null;
        if ($path === null) { return Response::notFound()->noCache(); }
        $modified=@filemtime($path); $size=@filesize($path);
        if ($modified===false || $size===false) { return Response::notFound()->noCache(); }
        // Uploads receive a new random filename on replacement. Browser-private
        // caching avoids sharing PHP session cookies through a public proxy cache.
        $etag='W/"'.$filename.'-'.$modified.'-'.$size.'"';
        $headers=[
            'Cache-Control'=>'private, max-age=86400, immutable',
            'ETag'=>$etag,
            'Last-Modified'=>gmdate('D, d M Y H:i:s',$modified).' GMT',
            'Expires'=>gmdate('D, d M Y H:i:s',time()+86400).' GMT',
            'X-Content-Type-Options'=>'nosniff',
        ];
        $matches=$request->header('If-None-Match');
        $since=$request->header('If-Modified-Since');
        $unchanged=$matches!==null
            ? ($matches==='*' || in_array($etag,array_map('trim',explode(',',$matches)),true))
            : ($since!==null && ($time=strtotime($since))!==false && $modified<=$time);
        if ($unchanged) { return Response::make('',304)->withHeaders($headers); }
        if (($bytes=@file_get_contents($path))===false) { return Response::notFound()->noCache(); }
        $mime=match(pathinfo($filename,PATHINFO_EXTENSION)) {
            'jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',default=>'application/octet-stream',
        };
        return Response::make($bytes)->withHeader('Content-Type',$mime)->withHeaders($headers);
    }

    public function index(Request $request): Response
    {
        return $this->render('rentals.index', $this->catalog())->noCache();
    }

    public function categories(Request $request): Response
    {
        return $this->render('rentals.categories', (new RentalCatalog($this->db()))->categoryPage($request->query('page', 1), $request->query('per_page', 12)))->noCache();
    }

    public function items(Request $request): Response
    {
        return $this->listing($request, $request->string('type')==='services');
    }

    public function services(Request $request): Response
    {
        // Preserve existing Services URLs while using the shared catalogue.
        return $this->listing($request, true);
    }

    public function howToRent(Request $request): Response
    {
        return $this->render('rentals.how-to-rent')->noCache();
    }

    public function support(Request $request): Response
    {
        return $this->render('rentals.support')->noCache();
    }

    private function catalog(): array
    {
        return (new RentalCatalog($this->db()))->load();
    }

    private function listing(Request $request, bool $services): Response
    {
        $data = (new RentalCatalog($this->db()))->page($services, $request->string('q'), $request->string('category'),
            $request->query('page', 1), $request->query('per_page', 12));
        return $this->render('rentals.items', $data+['isServiceCatalogue'=>$services, 'serverCatalogue'=>true, 'cataloguePath'=>ltrim($request->path(), '/')])->noCache();
    }
}
