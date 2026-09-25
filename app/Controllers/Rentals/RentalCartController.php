<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;
use App\Services\RentalCart;
use App\Services\RentalCheckout;

final class RentalCartController extends Controller
{
    public function availability(Request $request): Response
    {
        $month = $request->string('month');
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        $item = (new RentalCatalog($this->db()))->find($request->string('id'));
        $quantity = $request->int('quantity', 1);
        $today = new \DateTimeImmutable('today');
        if ($first === false || $first->format('Y-m') !== $month
            || $first < $today->modify('first day of this month')
            || $first > $today->modify('+12 months')->modify('first day of this month')
            || $item === null || ($item['is_sample'] ?? false) || (int) ($item['is_service'] ?? 0) === 1
            || $quantity < 1 || $quantity > 999) {
            return Response::json(['ok' => false, 'message' => 'Availability could not be loaded for that item or month.'], 422)->noCache();
        }
        $last = $first->modify('last day of this month');
        $remaining = (new RentalCatalog($this->db()))->remainingByDate($item, $first->format('Y-m-d'), $last->format('Y-m-d'));
        $stockStatus = strtolower((string) ($item['availability_status'] ?? ''));
        $canRent = !in_array($stockStatus, ['unavailable', 'out_of_stock', 'inactive', 'reserved'], true);
        $days = [];
        foreach ($remaining as $date => $units) {
            $days[] = ['date' => $date, 'remaining' => $units,
                'available' => $date >= $today->format('Y-m-d')
                    && $canRent && $units >= $quantity];
        }
        return Response::json(['ok' => true, 'month' => $month, 'days' => $days])->noCache();
    }

    public function index(Request $request): Response
    {
        $cart = new RentalCart();

        $items = $cart->items();
        $summary = (new RentalCheckout($this->db(), $cart))->summary();
        $ready = $items !== [] && count($summary['items']) === count($cart->contents());
        $catalog = new RentalCatalog($this->db());
        foreach ($items as $index => $item) {
            $start = (string) ($item['rental_start_date'] ?? '');
            $end = (string) ($item['rental_end_date'] ?? '');
            $record = $catalog->find((string) $item['item_id']);
            $datesValid = $this->validDateRange($start, $end);
            $items[$index]['dates_valid'] = $datesValid;
            $items[$index]['can_checkout'] = $datesValid && $record !== null
                && $this->canAddToCart($record, $cart, (int) $item['quantity'], $start, $end, (string) $item['line_id']);
            if (!$items[$index]['can_checkout']) {
                $ready = false;
            }
        }
        return $this->render('rentals.cart', [
            'cart' => $cart,
            'items' => $items,
            'summary' => $summary,
            'ready' => $ready,
            'count' => array_sum(array_column($items, 'quantity')),
            'empty' => $items === [],
        ])->noCache();
    }

    public function add(Request $request): Response
    {
        $itemId = $request->string('id', '');
        if ($itemId === '') {
            return $this->addFailure($request, 'Choose an equipment item.');
        }

        $catalog = new RentalCatalog($this->db());
        $item = $catalog->find($itemId);

        if ($item === null) {
            return $this->addFailure($request, 'This item is currently unavailable.');
        }
        if (($item['is_sample'] ?? false) === true) {
            return $this->addFailure($request, 'Preview images are not available to rent yet.');
        }

        $start = $request->string('rental_start_date');
        $end = $request->string('rental_end_date');
        $quantity = $request->int('quantity', 1);
        if ($start === '' || $end === '') {
            return $this->addFailure($request, 'Select your rental dates before adding this item.');
        }
        if (!$this->validDateRange($start, $end) || $quantity < 1 || $quantity > 999) {
            return $this->addFailure($request, 'Choose current or future dates and a valid quantity.');
        }
        $cart = new RentalCart();
        if (!$this->canAddToCart($item, $cart, $quantity, $start, $end)) {
            $lowest = $this->minimumRemainingWithCart($item, $cart, $start, $end);
            $message = $lowest > 0
                ? 'Only ' . $lowest . ' unit' . ($lowest === 1 ? ' is' : 's are') . ' available for the selected dates.'
                : 'This equipment is unavailable for the selected dates.';
            return $this->addFailure($request, $message);
        }
        $cart->add((string) $item['id'], $quantity, $start, $end);
        if ($request->isAjax()) {
            return Response::json(['ok' => true, 'count' => $cart->count(), 'message' => 'Available for these dates. Added to Cart.'])->noCache();
        }
        return $this->redirect(url('rentals/cart'));
    }

    private function addFailure(Request $request, string $message): Response
    {
        if ($request->isAjax()) {
            return Response::json(['ok' => false, 'message' => $message], 422)->noCache();
        }
        $_SESSION['rentals_notice'] = $message;
        return $this->redirect(url('rentals/items'));
    }

    public function update(Request $request): Response
    {
        $lineId = $request->string('line', '');
        $itemId = $request->string('id', '');
        $quantity = max(1, $request->int('quantity', 1));
        $start = $request->string('rental_start_date');
        $end = $request->string('rental_end_date');

        if ($lineId === '' || $itemId === '') {
            return $this->redirect(url('rentals/cart'));
        }

        $item = (new RentalCatalog($this->db()))->find($itemId);
        $cart = new RentalCart();
        if ($item === null || !$this->validDateRange($start, $end)
            || !$this->canAddToCart($item, $cart, $quantity, $start, $end, $lineId)) {
            $_SESSION['rentals_notice'] = 'Choose valid dates and an available quantity before updating your cart.';
            return $this->redirect(url('rentals/cart'));
        }

        $cart->update(
            $lineId,
            $quantity,
            $start !== '' ? $start : null,
            $end !== '' ? $end : null,
        );

        return $this->redirect(url('rentals/cart'));
    }

    public function remove(Request $request): Response
    {
        $lineId = $request->string('line', '');
        $cart = new RentalCart();

        if ($lineId !== '') {
            $cart->remove($lineId);
        }

        return $this->redirect(url('rentals/cart'));
    }

    public function clear(Request $request): Response
    {
        (new RentalCart())->clear();

        return $this->redirect(url('rentals/cart'));
    }

    private function canAddToCart(array $item, RentalCart $cart, int $quantity, string $start, string $end, ?string $excludeLine = null): bool
    {
        if ((int) ($item['is_service'] ?? 0) === 1) {
            return false;
        }

        if (($item['is_sample'] ?? false) === true) {
            return false;
        }

        $status = strtolower((string) ($item['availability_status'] ?? ''));
        return !in_array($status, ['unavailable', 'out_of_stock', 'inactive', 'reserved'], true)
            && $quantity > 0
            && $this->minimumRemainingWithCart($item, $cart, $start, $end, $excludeLine) >= $quantity;
    }

    private function minimumRemainingWithCart(array $item, RentalCart $cart, string $start, string $end, ?string $excludeLine = null): int
    {
        $remaining = (new RentalCatalog($this->db()))->remainingByDate($item, $start, $end);
        if ($remaining === []) { return 0; }
        $entries = $cart->contents();
        foreach ($remaining as $date => &$units) {
            foreach ($entries as $entry) {
                if ((string) ($entry['item_id'] ?? '') === (string) $item['id']
                    && (string) ($entry['line_id'] ?? '') !== $excludeLine
                    && (string) ($entry['rental_start_date'] ?? '') <= $date
                    && (string) ($entry['rental_end_date'] ?? '') >= $date) {
                    $units -= (int) ($entry['quantity'] ?? 0);
                }
            }
        }
        unset($units);
        return min($remaining);
    }

    private function validDateRange(string $start, string $end): bool
    {
        if ($start === '' && $end === '') {
            return false;
        }

        if ($start === '' || $end === '') {
            return false;
        }

        $startDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);

        return $startDate !== false
            && $endDate !== false
            && $startDate->format('Y-m-d') === $start
            && $endDate->format('Y-m-d') === $end
            && $startDate >= new \DateTimeImmutable('today')
            && $endDate >= $startDate;
    }

}
