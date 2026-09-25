<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\RentalAccount;
use App\Services\RentalCart;
use App\Services\RentalPaymentProof;
use App\Services\RentalPaymentStatus;
use App\Services\RentalNotification;
use App\Models\RentalCatalog;
use RuntimeException;

final class RentalAccountController extends Controller
{
    public function show(Request $request): Response
    {
        $account = new RentalAccount($this->db(), new RentalCart());
        $user = $account->current();

        return $this->render('rentals.account', [
            'user' => $user,
            'error' => null,
            'action' => $request->string('mode') === 'register' ? 'register' : 'login',
        ])->noCache();
    }

    public function submit(Request $request): Response
    {
        $account = new RentalAccount($this->db(), new RentalCart());
        $action = $request->string('action', 'login');
        $error = null;
        $user = null;

        try {
            if ($action === 'register') {
                $user = $account->register(
                    $request->string('name'),
                    $request->string('email'),
                    $request->string('password')
                );
            } else {
                $user = $account->login(
                    $request->string('email'),
                    $request->string('password')
                );
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        if ($user !== null) {
            if (in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'superadmin'], true)) {
                unset($_SESSION['rentals_after_auth']);
                return $this->redirect(url('rentals/admin'));
            }
            $destination = ($_SESSION['rentals_after_auth'] ?? null) === 'checkout'
                ? 'rentals/checkout'
                : ((new RentalCart())->empty() ? 'rentals/account' : 'rentals/cart');
            unset($_SESSION['rentals_after_auth']);
            return $this->redirect(url($destination));
        }

        return $this->render('rentals.account', [
            'user' => null,
            'error' => $error,
            'action' => $action,
            'submittedEmail' => $request->string('email'),
        ], 422)->noCache();
    }

    public function logout(Request $request): Response
    {
        (new RentalAccount($this->db(), new RentalCart()))->logout();
        unset($_SESSION['rentals_after_auth']);
        return $this->redirect(url('rentals'));
    }

    public function orders(Request $request): Response
    {
        $userId = (int) ((new RentalAccount($this->db(), new RentalCart()))->current()['id'] ?? 0);
        if ($userId < 1) {
            return $this->redirect(url('rentals/account'));
        }

        $orders = $this->db()->select(
            'SELECT h.id, h.order_number, h.customer_name, h.customer_email, h.payment_status,
                    h.payment_reference, h.payment_proof_path, h.status_token,
                    h.subtotal, h.security_deposit, h.total_amount, h.created_at,
                    p.name AS payment_method, p.type AS payment_type
             FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
             WHERE h.user_id = ?
             ORDER BY h.created_at DESC, h.id DESC',
            [$userId]
        );
        foreach ($orders as &$order) {
            $order['details'] = $this->db()->select(
                'SELECT item_name, quantity, rental_start_date, rental_end_date, unit_rate, line_total
                 FROM order_details WHERE order_header_id = ? ORDER BY id ASC',
                [(int) $order['id']]
            );
        }
        unset($order);

        return $this->render('rentals.orders', ['orders' => $orders])->noCache();
    }

    public function uploadProof(Request $request, string $id): Response
    {
        $user = (new RentalAccount($this->db(), new RentalCart()))->current();
        if ($user === null) { return $this->redirect(url('rentals/account')); }
        $orderId = (int) $id;
        $order = $this->db()->selectOne(
            'SELECT h.id, h.payment_proof_path, h.payment_status, p.type AS payment_type
             FROM order_header h JOIN payment_methods p ON p.id = h.payment_method_id
             WHERE h.id = ? AND h.user_id = ?', [$orderId, (int) $user['id']]
        );
        if ($order === null) { return Response::notFound(); }
        if ((string) $order['payment_type'] === 'gateway'
            || (int) $order['payment_status'] === RentalPaymentStatus::APPROVED) {
            return Response::forbidden('Payment proof cannot be changed for this order.');
        }
        try {
            $name = RentalPaymentProof::store($request->file('proof'));
            try {
                $reference = substr($request->string('payment_reference'), 0, 190);
                $result = $this->db()->transaction(function (\App\Core\Database $db) use ($orderId, $user, $name, $reference): array {
                    $current = $db->selectOne(
                        'SELECT h.payment_proof_path, h.payment_status, h.order_number, h.customer_email, h.status_token, p.type AS payment_type
                         FROM order_header h JOIN payment_methods p ON p.id = h.payment_method_id
                         WHERE h.id = ? AND h.user_id = ? FOR UPDATE', [$orderId, (int) $user['id']]
                    );
                    if ($current === null || $current['payment_type'] === 'gateway'
                        || (int) $current['payment_status'] === RentalPaymentStatus::APPROVED) {
                        throw new RuntimeException('Payment proof cannot be changed for this order.');
                    }
                    if ((int) $current['payment_status'] === RentalPaymentStatus::REJECTED) {
                        $lines = $db->select('SELECT rental_item_id, quantity, rental_start_date, rental_end_date FROM order_details WHERE order_header_id = ?', [$orderId]);
                        $ids = array_unique(array_map(static fn (array $line): int => (int) $line['rental_item_id'], $lines));
                        sort($ids, SORT_NUMERIC);
                        foreach ($ids as $itemId) { $db->selectOne('SELECT id FROM rental_items WHERE id = ? FOR UPDATE', [$itemId]); }
                        $catalog = new RentalCatalog($db);
                        foreach ($lines as $line) {
                            if ($line['rental_start_date'] === null || $line['rental_end_date'] === null) {
                                throw new RuntimeException('Rental dates are missing from this order. Please contact support.');
                            }
                            $item = $catalog->find((string) $line['rental_item_id']);
                            $overlappingQuantity = 0;
                            foreach ($lines as $other) {
                                if ((int) $other['rental_item_id'] === (int) $line['rental_item_id']
                                    && $other['rental_start_date'] <= $line['rental_end_date']
                                    && $other['rental_end_date'] >= $line['rental_start_date']) {
                                    $overlappingQuantity += (int) $other['quantity'];
                                }
                            }
                            if ($item === null || !$catalog->isAvailable($item, $overlappingQuantity, $line['rental_start_date'], $line['rental_end_date'])) {
                                throw new RuntimeException('This order is no longer available for its rental dates. Please contact support.');
                            }
                        }
                    }
                    $db->update(
                        'UPDATE order_header SET payment_proof_path = ?, payment_reference = ?,
                         payment_reviewed_at = NULL, payment_reviewed_by = NULL, payment_status = ?, paid_at = NULL
                         WHERE id = ? AND user_id = ?',
                        [$name, $reference !== '' ? $reference : null, RentalPaymentStatus::PENDING, $orderId, (int) $user['id']]
                    );
                    return $current;
                });
            } catch (\Throwable $e) {
                RentalPaymentProof::remove($name);
                throw $e;
            }
            RentalPaymentProof::remove($result['payment_proof_path']);
            RentalNotification::send($result['customer_email'], $result['order_number'], (string) $result['status_token'], RentalPaymentStatus::PENDING);
            $_SESSION['rentals_notice'] = 'Payment proof submitted for review.';
        } catch (RuntimeException $e) {
            $_SESSION['rentals_notice'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('Rentals proof upload failed: ' . $e->getMessage());
            $_SESSION['rentals_notice'] = 'Payment proof could not be saved.';
        }
        return $this->redirect(url('rentals/orders'));
    }

    public function viewProof(Request $request, string $id): Response
    {
        $user = (new RentalAccount($this->db(), new RentalCart()))->current();
        if ($user === null) { return $this->redirect(url('rentals/account')); }
        $order = $this->db()->selectOne(
            'SELECT payment_proof_path FROM order_header WHERE id = ? AND user_id = ?',
            [(int) $id, (int) $user['id']]
        );
        return $order === null ? Response::notFound() : RentalPaymentProof::response($order['payment_proof_path']);
    }
}
