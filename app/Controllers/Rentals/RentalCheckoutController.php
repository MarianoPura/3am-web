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
            $concurrent = 0;
            foreach ($summary['items'] as $other) {
                if ((int) $other['db_id'] === (int) $line['db_id']
                    && (string) $other['rental_start_date'] <= $end
                    && (string) $other['rental_end_date'] >= $start) {
                    $concurrent += (int) $other['quantity'];
                }
            }
            if ($record === null || !$catalog->isAvailable($record, $concurrent, $start, $end)) {
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
                RentalNotification::send($result['customer_email'], $result['order_number'], $result['status_token'], RentalPaymentStatus::PENDING);

                return $this->render('rentals.confirmation', $result)->noCache();
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
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) { return Response::notFound(); }
        $order = $this->db()->selectOne('SELECT h.order_number, h.customer_name, h.payment_status,
            h.payment_proof_path, p.name AS payment_method FROM order_header h
            LEFT JOIN payment_methods p ON p.id = h.payment_method_id WHERE h.status_token = ?', [$token]);
        return $order === null ? Response::notFound()
            : $this->render('rentals.status', ['order' => $order, 'statusToken' => $token])->noCache();
    }
}
