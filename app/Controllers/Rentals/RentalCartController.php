<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;
use App\Services\RentalCart;

final class RentalCartController extends Controller
{
    public function index(Request $request): Response
    {
        $cart = new RentalCart();

        return $this->render('rentals.cart', [
            'cart' => $cart,
            'items' => $cart->items(),
            'count' => $cart->count(),
            'empty' => $cart->empty(),
            'summary' => $this->summary($cart),
        ])->noCache();
    }

    public function add(Request $request): Response
    {
        $itemId = $request->string('id', '');
        $quantity = max(1, $request->int('quantity', 1));
        $start = $request->string('rental_start_date');
        $end = $request->string('rental_end_date');

        if ($itemId === '') {
            return $this->redirect(url('rentals/items'));
        }

        $catalog = new RentalCatalog($this->db());
        $item = $catalog->find($itemId);

        if ($item === null) {
            return $this->redirect(url('rentals/items'));
        }

        $cart = new RentalCart();
        $cart->add($itemId, $quantity, $start !== '' ? $start : null, $end !== '' ? $end : null);

        return $this->redirect(url('rentals/cart'));
    }

    public function remove(Request $request): Response
    {
        $itemId = $request->string('id', '');
        $cart = new RentalCart();

        if ($itemId !== '') {
            $cart->remove($itemId);
        }

        return $this->redirect(url('rentals/cart'));
    }

    public function clear(Request $request): Response
    {
        (new RentalCart())->clear();

        return $this->redirect(url('rentals/cart'));
    }

    /**
     * @return array<string, float|int>
     */
    private function summary(RentalCart $cart): array
    {
        $subtotal = 0.0;

        foreach ($cart->items() as $item) {
            $subtotal += (float) ($item['rental_rate'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }

        return [
            'subtotal' => $subtotal,
            'deposit' => 0.0,
            'total' => $subtotal,
        ];
    }
}