<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;
use App\Services\RentalCart;
use App\Services\RentalCheckout;
use App\Services\RentalNotification;
use App\Services\RentalPaymentStatus;
use App\Services\RentalManagedImage;
use App\Services\RentalAccount;
use RuntimeException;

final class RentalCheckoutController extends Controller
{
    public function show(Request $request): Response
    {
        $checkout = new RentalCheckout($this->db(), new RentalCart());
        $summary = $checkout->summary();
        if ($summary['items'] === [] || count($summary['items']) !== count((new RentalCart())->contents())) {
            $_SESSION['rentals_notice'] = 'Review your cart before checkout.';
            return $this->redirect(url('rentals/cart'));
        }
        foreach ($summary['items'] as $line) {
            $start = (string) ($line['rental_start_date'] ?? '');
            $end = (string) ($line['rental_end_date'] ?? '');
            $startDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            $endDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
            if ($startDay === false || $endDay === false
                || $startDay->format('Y-m-d') !== $start || $endDay->format('Y-m-d') !== $end
                || $startDay < new \DateTimeImmutable('today') || $endDay < $startDay) {
                $_SESSION['rentals_notice'] = 'Set valid dates for every item in your cart first.';
                return $this->redirect(url('rentals/cart'));
            }
            $catalog = new RentalCatalog($this->db());
            $record = $catalog->find((string) $line['item_id']);
            if ($record === null || !$catalog->isAvailableForLines($record, $summary['items'], $start, $end)) {
                $_SESSION['rentals_notice'] = 'An item is unavailable for the selected dates or quantity.';
                return $this->redirect(url('rentals/cart'));
            }
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($summary['items'] !== [] && $userId < 1 && !$summary['preview']) {
            $_SESSION['rentals_after_auth'] = 'checkout';
            return $this->redirect(url('rentals/account'));
        }
        $user = $userId > 0 ? $this->db()->selectOne('SELECT name, email FROM users WHERE id = ?', [$userId]) : null;
        if ($user === null) { return $this->redirect(url('rentals/account')); }

        return $this->render('rentals.checkout', [
            'summary' => $summary,
            'paymentMethods' => $checkout->activePaymentMethods(),
            'customer' => ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? ''],
            'error' => null,
        ])->noCache();
    }

    public function submit(Request $request): Response
    {
        $checkout = new RentalCheckout($this->db(), new RentalCart());
        $summary = $checkout->summary();
        if ((int) ($_SESSION['user_id'] ?? 0) < 1) {
            return $this->redirect(url('rentals/account'));
        }
        $paymentMethod = $request->has('payment_method_id')
            ? $request->int('payment_method_id')
            : null;

        $customer = [
            'name' => $request->string('customer_name'),
            'email' => $request->string('customer_email'),
            'phone' => $request->string('customer_phone'),
            'payment_method_id' => $paymentMethod > 0 ? $paymentMethod : null,
            'payment_reference' => substr($request->string('payment_reference'), 0, 190),
            'notes' => $request->string('notes'),
        ];
        $account = $this->db()->selectOne('SELECT email FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
        if ($account === null) { return $this->redirect(url('rentals/account')); }
        $customer['email'] = (string) $account['email'];

        $error = null;
        if ($customer['name'] === '' || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false) {
            $error = 'Enter your name and a valid email address.';
        } else {
            try {
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                $result = $checkout->createOrder($userId, $customer, $request->file('proof'));
                $this->container->get(RentalNotification::class)->rentalSubmitted($result['order_id']);

                // A GET receipt can be refreshed after review without reposting checkout.
                return $this->redirect(url('rentals/confirmation/' . $result['status_token']))->noCache();
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('rentals.checkout', [
            'summary' => $summary,
            'paymentMethods' => $checkout->activePaymentMethods(),
            'error' => $error,
            'customer' => $customer,
        ], 422)->noCache();
    }

    public function status(Request $request, string $token): Response
    {
        $order = $this->statusOrder($token, 'order-status');
        return $order instanceof Response ? $order
            : $this->render('rentals.status', ['order' => $order, 'statusToken' => $token])->noCache();
    }

    public function confirmation(Request $request, string $token): Response
    {
        $order = $this->statusOrder($token, 'confirmation');
        return $order instanceof Response ? $order
            : $this->render('rentals.confirmation', ['order' => $order, 'status_token' => $token])->noCache();
    }

    private function statusOrder(string $token, string $page): array|Response
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) { return Response::notFound()->noCache(); }
        $user = (new RentalAccount($this->db(), new RentalCart()))->current();
        if ($user === null) {
            $_SESSION['rentals_after_auth'] = $page . '/' . $token;
            return $this->redirect(url('rentals/account'))->noCache();
        }
        // A token locates the order; it never grants access by itself.
        // Return only status data, not customer details or stored proof paths.
        $order = $this->db()->selectOne('SELECT order_number, user_id, payment_status,
            CASE WHEN NULLIF(TRIM(payment_proof_path), \'\') IS NULL THEN 0 ELSE 1 END AS has_proof
            FROM order_header WHERE status_token = ?', [$token]);
        if ($order === null) { return Response::notFound()->noCache(); }
        if ((int) $order['user_id'] !== (int) $user['id']
            && !in_array(strtolower((string) $user['role']), ['admin', 'superadmin'], true)) {
            return Response::forbidden('This rental order is not available to your account.')->noCache();
        }
        unset($order['user_id']);
        return $order;
    }

    public function paymentQr(Request $request, string $id): Response
    {
        $method = $this->db()->selectOne('SELECT type, is_active, qr_image_path FROM payment_methods WHERE id = ?', [(int) $id]);
        if ($method === null) { return Response::notFound(); }
        if ($method['type'] !== 'manual' || (int) $method['is_active'] !== 1) {
            $user = (new RentalAccount($this->db(), new RentalCart()))->current();
            if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'superadmin'], true)) { return Response::notFound(); }
        }
        $path = RentalManagedImage::publicPath($method['qr_image_path'], 'qr');
        if ($path === null) { return Response::notFound(); }
        $bytes = @file_get_contents((string) \App\Services\RentalStorage::path($path));
        if ($bytes === false) { return Response::notFound(); }
        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream',
        };
        return Response::make($bytes)->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'inline; filename="payment-qr.' . pathinfo($path, PATHINFO_EXTENSION) . '"')->noCache();
    }
}
