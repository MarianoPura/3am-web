<?php

declare(strict_types=1);

/**
 * Route table.
 *
 * The complete, readable list of every URL this application answers. No
 * annotations, no attribute scanning, no cache to invalidate — if a URL is not
 * in this file, it does not exist.
 *
 * ★ Paths belonging to the legacy applications (/armonyx, /trebl, ...) must
 *   never appear here. They are refused by ReservedPath before routing runs;
 *   see config/reserved.php.
 *
 * Routes are written against the apex. When the site is mounted at a
 * subdirectory (/web during development) Request strips the prefix, so these
 * match unchanged in both places.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Rentals\RentalsController;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\InquiryController;
use App\Controllers\Web\PageController;
use App\Controllers\Web\ProjectController;

// ── Home ─────────────────────────────────────────────────────
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/services', [PageController::class, 'services'])->name('services');
$router->get('/information', [PageController::class, 'information'])->name('information');
$router->get('/rentals', [RentalsController::class, 'index'])->name('rentals');
$router->get('/rentals/cart', [\App\Controllers\Rentals\RentalCartController::class, 'index'])->name('rentals.cart');
$router->get('/rentals/availability', [\App\Controllers\Rentals\RentalCartController::class, 'availability'])->name('rentals.availability');
$router->post('/rentals/cart/add', [\App\Controllers\Rentals\RentalCartController::class, 'add'])->name('rentals.cart.add');
$router->post('/rentals/cart/update', [\App\Controllers\Rentals\RentalCartController::class, 'update'])->name('rentals.cart.update');
$router->post('/rentals/cart/remove', [\App\Controllers\Rentals\RentalCartController::class, 'remove'])->name('rentals.cart.remove');
$router->post('/rentals/cart/clear', [\App\Controllers\Rentals\RentalCartController::class, 'clear'])->name('rentals.cart.clear');
$router->get('/rentals/checkout', [\App\Controllers\Rentals\RentalCheckoutController::class, 'show'])->name('rentals.checkout');
$router->post('/rentals/checkout', [\App\Controllers\Rentals\RentalCheckoutController::class, 'submit'])->name('rentals.checkout.submit');
$router->get('/rentals/order-status/{token:slug}', [\App\Controllers\Rentals\RentalCheckoutController::class, 'status'])->name('rentals.order-status');
$router->get('/rentals/account', [\App\Controllers\Rentals\RentalAccountController::class, 'show'])->name('rentals.account');
$router->post('/rentals/account', [\App\Controllers\Rentals\RentalAccountController::class, 'submit'])->name('rentals.account.submit');
$router->post('/rentals/logout', [\App\Controllers\Rentals\RentalAccountController::class, 'logout'])->name('rentals.logout');
$router->get('/rentals/orders', [\App\Controllers\Rentals\RentalAccountController::class, 'orders'])->name('rentals.orders');
$router->post('/rentals/orders/{id:int}/proof', [\App\Controllers\Rentals\RentalAccountController::class, 'uploadProof'])->name('rentals.orders.proof');
$router->get('/rentals/orders/{id:int}/proof', [\App\Controllers\Rentals\RentalAccountController::class, 'viewProof'])->name('rentals.orders.proof.view');
$router->get('/rentals/categories', [RentalsController::class, 'categories'])->name('rentals.categories');
$router->get('/rentals/items', [RentalsController::class, 'items'])->name('rentals.items');
$router->get('/rentals/services', [RentalsController::class, 'services'])->name('rentals.services');
$router->get('/rentals/how-to-rent', [RentalsController::class, 'howToRent'])->name('rentals.how-to-rent');
$router->get('/rentals/support', [RentalsController::class, 'support'])->name('rentals.support');
$router->get('/rentals/admin', [\App\Controllers\Rentals\RentalAdminController::class, 'index'])->name('rentals.admin');
$router->post('/rentals/admin/items/{id:int}/blackouts', [\App\Controllers\Rentals\RentalAdminController::class, 'saveBlackout'])->name('rentals.admin.blackouts.save');
$router->post('/rentals/admin/items/{id:int}/blackouts/{blackoutId:int}/toggle', [\App\Controllers\Rentals\RentalAdminController::class, 'toggleBlackout'])->name('rentals.admin.blackouts.toggle');
$router->get('/rentals/admin/proof/{id:int}', [\App\Controllers\Rentals\RentalAdminController::class, 'viewProof'])->name('rentals.admin.proof');
$router->post('/rentals/admin/orders/{id:int}/review', [\App\Controllers\Rentals\RentalAdminController::class, 'reviewProof'])->name('rentals.admin.review');
$router->get('/rentals/admin/{section:slug}', [\App\Controllers\Rentals\RentalAdminController::class, 'section'])->name('rentals.admin.section');
$router->post('/rentals/admin/{section:slug}', [\App\Controllers\Rentals\RentalAdminController::class, 'save'])->name('rentals.admin.save');
$router->post('/rentals/admin/{section:slug}/toggle', [\App\Controllers\Rentals\RentalAdminController::class, 'toggle'])->name('rentals.admin.toggle');
$router->get('/equipment-rentals', [PageController::class, 'legacyInformation'])->name('rentals.legacy');
$router->get('/about', [PageController::class, 'about'])->name('about');
$router->get('/contact', [PageController::class, 'contact'])->name('contact');

// ── Projects showcase ────────────────────────────────────────
$router->get('/projects', [ProjectController::class, 'index'])->name('projects');

// ── Project inquiries ────────────────────────────────────────
// {type} is constrained to a slug at the routing layer, and the controller
// then checks it against config/forms.php — so an unknown form 404s before
// any work happens rather than rendering an empty select.
$router->get('/start/received', [InquiryController::class, 'received'])->name('inquiry.received');
$router->get('/start', [InquiryController::class, 'show'])->name('inquiry');
$router->post('/start', [InquiryController::class, 'submit'])->name('inquiry.send');
$router->get('/start/{type:slug}', [InquiryController::class, 'show'])->name('inquiry.show');
$router->post('/start/{type:slug}', [InquiryController::class, 'submit'])->name('inquiry.submit');

// ── Operational ──────────────────────────────────────────────
// Used by bin/deploy.sh to verify a release before the symlink swap.
$router->get('/health', [HomeController::class, 'health'])->name('health');


/*
 * ── Still to build ───────────────────────────────────────────
 * Kept here as the shape of the sitemap, so the URL structure is agreed before
 * the controllers exist rather than emerging from them.
 *
 * Phase 2 — public shell
 *   GET  /services/{channel:slug}        ServiceController@channel
 *   GET  /services/{channel:slug}/{slug} ServiceController@show
 *   GET  /clients                        ClientController@index
 *
 * Phase 3 — portfolio
 *   GET  /work                           ProjectController@index
 *   GET  /work/{slug}                    ProjectController@show
 *   GET  /ventures                       VentureController@index
 *
 * Phase 5 — lead generation
 *   POST /contact                        ContactController@submit    (throttled)
 *   GET  /contact/received               ContactController@received
 *
 * Phase 7 — SEO
 *   GET  /sitemap.xml                    SitemapController@index
 *
 * Phase 4 — admin. Grouped so auth, the IP allowlist and 2FA attach to every
 * route by construction; a new admin route cannot be added without them.
 *
 *   $router->group(config('app.admin_path'), ['auth', 'admin.ip'], function ($router) {
 *       $router->get('/dashboard', [DashboardController::class, 'index']);
 *       ...
 *   });
 */
