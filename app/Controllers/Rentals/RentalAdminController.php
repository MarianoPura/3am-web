<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Services\RentalDiagnostic;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalCatalog;
use App\Services\RentalAccount;
use App\Services\RentalAdminInsights;
use App\Services\RentalCart;
use App\Services\RentalPaymentProof;
use App\Services\RentalPaymentStatus;
use App\Services\RentalNotification;
use App\Services\RentalManagedImage;
use Throwable;

/** Rentals-only administration. */
final class RentalAdminController extends Controller
{
    private const AVAILABILITY = ['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'];
    private ?array $adminUser = null;

    // ── Section entry points ───────────────────────────────────

    public function index(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $insights = new RentalAdminInsights($this->db());
        $dashboard = $insights->dashboard();
        $notice = $this->popNotice();
        return $this->render('rentals.admin.dashboard', [
            'section'         => 'dashboard',
            'metrics'         => $dashboard['metrics'],
            'recentOrders'    => $dashboard['recentOrders'],
            'upcomingRentals' => $dashboard['upcomingRentals'],
            'notice'          => $notice,
            'adminUser'       => $this->adminUser,
        ])->noCache();
    }

    public function analytics(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        return $this->render('rentals.admin.analytics', [
            'section'   => 'analytics',
            'insights'  => (new RentalAdminInsights($this->db()))->analytics($request),
            'adminUser' => $this->adminUser,
        ])->noCache();
    }

    public function salesReport(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        return $this->render('rentals.admin.sales-report', [
            'section'   => 'sales-report',
            'report'    => (new RentalAdminInsights($this->db()))->salesReport($request),
            'adminUser' => $this->adminUser,
        ])->noCache();
    }

    public function paymentReport(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        try {
            $report = (new RentalAdminInsights($this->db()))->paymentReport($request);
        } catch (Throwable $e) {
            $cause = $e->getPrevious() ?? $e;
            RentalDiagnostic::exception('reports', 'query', $cause);
            throw $e;
        }
        return $this->render('rentals.admin.payment-report', [
            'section'   => 'payment-report',
            'report'    => $report,
            'adminUser' => $this->adminUser,
        ])->noCache();
    }

    public function items(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        if ($request->string('new') === '1') {
            return $this->redirect(url('rentals/admin/items/new'));
        }
        $editId = max(0, $request->int('edit'));
        if ($editId > 0) {
            return $this->redirect(url('rentals/admin/items/' . $editId . '/edit'));
        }

        $db = $this->db();
        $term       = substr($request->string('q'), 0, 100);
        $categoryId = max(0, $request->int('category'));
        $type       = in_array($request->string('type'), ['equipment', 'service'], true) ? $request->string('type') : 'all';
        $typeValue  = $type === 'equipment' ? 0 : ($type === 'service' ? 1 : -1);
        $categories = $db->select('SELECT id, name, is_active FROM rental_categories ORDER BY name, id');
        $rows       = $db->select(
            'SELECT i.*, c.name AS category_name FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
             WHERE (? = 0 OR i.category_id = ?) AND (? = \'\' OR i.name LIKE ? OR i.sku LIKE ?)
               AND (? = -1 OR i.is_service = ?)
             ORDER BY i.updated_at DESC, i.id DESC LIMIT 200',
            [$categoryId, $categoryId, $term, '%' . $term . '%', '%' . $term . '%', $typeValue, $typeValue]
        );

        return $this->render('rentals.admin.items', [
            'section'        => 'items',
            'rows'           => $rows,
            'categories'     => $categories,
            'images'         => $this->localImages(),
            'term'           => $term,
            'categoryFilter' => $categoryId,
            'typeFilter'     => $type,
            'notice'         => $this->popNotice(),
            'noticeIsError'  => $this->popNoticeError(),
            'adminUser'      => $this->adminUser,
        ])->noCache();
    }

    public function itemsNew(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $draft = $_SESSION['rentals_admin_product_draft']['fields'] ?? [];
        unset($_SESSION['rentals_admin_product_draft']);

        $db = $this->db();
        return $this->render('rentals.admin.items-create', [
            'section'       => 'items',
            'categories'    => $db->select('SELECT id, name, is_active FROM rental_categories ORDER BY name, id'),
            'images'        => $this->localImages(),
            'draft'         => $draft,
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function itemsEdit(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $itemId = (int) $id;
        if ($itemId < 1) { return Response::notFound(); }

        $db   = $this->db();
        $item = $db->selectOne('SELECT * FROM rental_items WHERE id = ?', [$itemId]);
        if ($item === null) { return Response::notFound(); }

        if (isset($_SESSION['rentals_admin_product_draft']) && (int) ($_SESSION['rentals_admin_product_draft']['id'] ?? 0) === $itemId) {
            $draft = $_SESSION['rentals_admin_product_draft']['fields'] ?? [];
            unset($_SESSION['rentals_admin_product_draft']);
            if (!empty($draft)) {
                $item = array_merge($item, $draft);
            }
        }

        return $this->render('rentals.admin.items-edit', [
            'section'       => 'items',
            'itemId'        => $itemId,
            'item'          => $item,
            'record'        => $item,
            'categories'    => $db->select('SELECT id, name, is_active FROM rental_categories ORDER BY name, id'),
            'blackouts'     => $db->select('SELECT * FROM rental_item_blackouts WHERE rental_item_id = ? ORDER BY start_date DESC, id DESC', [$itemId]),
            'images'        => $this->localImages(),
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function categories(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        if ($request->string('new') === '1') {
            return $this->redirect(url('rentals/admin/categories/new'));
        }
        $editId = max(0, $request->int('edit'));
        if ($editId > 0) {
            return $this->redirect(url('rentals/admin/categories/' . $editId . '/edit'));
        }

        $rows = $this->db()->select('SELECT * FROM rental_categories ORDER BY name, id');
        return $this->render('rentals.admin.categories', [
            'section'       => 'categories',
            'rows'          => $rows,
            'images'        => $this->localImages(),
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function categoriesNew(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        return $this->render('rentals.admin.categories-create', [
            'section'       => 'categories',
            'images'        => $this->localImages(),
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function categoriesEdit(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $categoryId = (int) $id;
        if ($categoryId < 1) { return Response::notFound(); }

        $category = $this->db()->selectOne('SELECT * FROM rental_categories WHERE id = ?', [$categoryId]);
        if ($category === null) { return Response::notFound(); }

        return $this->render('rentals.admin.categories-edit', [
            'section'       => 'categories',
            'category'      => $category,
            'record'        => $category,
            'images'        => $this->localImages(),
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function orders(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $editId = max(0, $request->int('edit'));
        if ($editId > 0) {
            return $this->redirect(url('rentals/admin/orders/' . $editId));
        }

        $db     = $this->db();
        $term   = substr($request->string('q'), 0, 100);
        $status = $request->string('status');
        $status = in_array($status, ['0', '1', '2'], true) ? $status : 'all';
        $rows   = $db->select(
            'SELECT h.*, p.name AS payment_method FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
             WHERE (? = \'\' OR h.order_number LIKE ? OR h.customer_name LIKE ? OR h.customer_email LIKE ?)
               AND (? = \'all\' OR h.payment_status = ?)
             ORDER BY (h.payment_status = 0) DESC, h.created_at DESC, h.id DESC LIMIT 200',
            [$term, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%', $status, $status]
        );

        return $this->render('rentals.admin.orders', [
            'section'       => 'orders',
            'rows'          => $rows,
            'term'          => $term,
            'statusFilter'  => $status,
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function ordersView(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $orderId = (int) $id;
        if ($orderId < 1) { return Response::notFound(); }

        $db     = $this->db();
        $record = $db->selectOne('SELECT h.*, p.name AS payment_method, p.type AS payment_type FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id WHERE h.id = ?', [$orderId]);
        if ($record === null) { return Response::notFound(); }

        return $this->render('rentals.admin.orders-view', [
            'section'       => 'orders',
            'record'        => $record,
            'details'       => $db->select('SELECT * FROM order_details WHERE order_header_id = ? ORDER BY id', [$orderId]),
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function payments(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        if ($request->string('new') === '1') {
            return $this->redirect(url('rentals/admin/payments/new'));
        }
        $editId = max(0, $request->int('edit'));
        if ($editId > 0) {
            return $this->redirect(url('rentals/admin/payments/' . $editId . '/edit'));
        }

        $rows = $this->db()->select('SELECT * FROM payment_methods ORDER BY name, id');
        return $this->render('rentals.admin.payments', [
            'section'       => 'payments',
            'rows'          => $rows,
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function paymentsNew(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        return $this->render('rentals.admin.payments-create', [
            'section'       => 'payments',
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function paymentsEdit(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $paymentId = (int) $id;
        if ($paymentId < 1) { return Response::notFound(); }

        $payment = $this->db()->selectOne('SELECT * FROM payment_methods WHERE id = ?', [$paymentId]);
        if ($payment === null) { return Response::notFound(); }

        return $this->render('rentals.admin.payments-edit', [
            'section'       => 'payments',
            'payment'       => $payment,
            'record'        => $payment,
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    public function customers(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $rows = $this->db()->select('SELECT id, name, email, role, last_login FROM users ORDER BY created_at DESC, id DESC LIMIT 200');
        return $this->render('rentals.admin.customers', [
            'section'       => 'customers',
            'rows'          => $rows,
            'notice'        => $this->popNotice(),
            'noticeIsError' => $this->popNoticeError(),
            'adminUser'     => $this->adminUser,
        ])->noCache();
    }

    // ── Write actions ──────────────────────────────────────────

    public function itemsStore(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $id = max(0, $request->int('id'));
        if ($id > 0) {
            return $this->itemsUpdate($request, (string) $id);
        }

        try {
            $this->validateAndSaveItem($request, 0);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            unset($_SESSION['rentals_admin_product_draft']);
            return $this->redirect(url('rentals/admin/items'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('product-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $this->saveProductDraft($request, 0);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/items/new'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('product-crud', 'save', $e, ['response_status' => 302]);
            $this->saveProductDraft($request, 0);
            $_SESSION['rentals_admin_notice'] = 'The product could not be saved right now. Your entries have been kept.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/items/new'));
        }
    }

    public function itemsUpdate(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $itemId = (int) $id;
        if ($itemId < 1) { return Response::notFound(); }

        try {
            $this->validateAndSaveItem($request, $itemId);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            unset($_SESSION['rentals_admin_product_draft']);
            return $this->redirect(url('rentals/admin/items/' . $itemId . '/edit'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('product-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $this->saveProductDraft($request, $itemId);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/items/' . $itemId . '/edit'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('product-crud', 'save', $e, ['response_status' => 302]);
            $this->saveProductDraft($request, $itemId);
            $_SESSION['rentals_admin_notice'] = 'The product could not be saved right now. Your entries have been kept.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/items/' . $itemId . '/edit'));
        }
    }

    public function saveItems(Request $request): Response
    {
        $id = max(0, $request->int('id'));
        return $id > 0 ? $this->itemsUpdate($request, (string) $id) : $this->itemsStore($request);
    }

    public function categoriesStore(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $id = max(0, $request->int('id'));
        if ($id > 0) {
            return $this->categoriesUpdate($request, (string) $id);
        }

        try {
            $this->validateAndSaveCategory($request, 0);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            return $this->redirect(url('rentals/admin/categories'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('category-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/categories/new'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('category-crud', 'save', $e, ['response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Could not save. Check required fields and unique slug.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/categories/new'));
        }
    }

    public function categoriesUpdate(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $categoryId = (int) $id;
        if ($categoryId < 1) { return Response::notFound(); }

        try {
            $this->validateAndSaveCategory($request, $categoryId);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            return $this->redirect(url('rentals/admin/categories/' . $categoryId . '/edit'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('category-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/categories/' . $categoryId . '/edit'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('category-crud', 'save', $e, ['response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Could not save. Check required fields and unique slug.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/categories/' . $categoryId . '/edit'));
        }
    }

    public function saveCategories(Request $request): Response
    {
        $id = max(0, $request->int('id'));
        return $id > 0 ? $this->categoriesUpdate($request, (string) $id) : $this->categoriesStore($request);
    }

    public function paymentsStore(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $id = max(0, $request->int('id'));
        if ($id > 0) {
            return $this->paymentsUpdate($request, (string) $id);
        }

        try {
            $this->validateAndSavePayment($request, 0);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            return $this->redirect(url('rentals/admin/payments'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('payment-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/payments/new'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('payment-crud', 'save', $e, ['response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Could not save. Check required fields and unique name.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/payments/new'));
        }
    }

    public function paymentsUpdate(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }

        $paymentId = (int) $id;
        if ($paymentId < 1) { return Response::notFound(); }

        try {
            $this->validateAndSavePayment($request, $paymentId);
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
            return $this->redirect(url('rentals/admin/payments/' . $paymentId . '/edit'));
        } catch (\InvalidArgumentException $e) {
            RentalDiagnostic::exception('payment-crud', 'validation', $e, ['validation' => 'failed', 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/payments/' . $paymentId . '/edit'));
        } catch (Throwable $e) {
            RentalDiagnostic::exception('payment-crud', 'save', $e, ['response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Could not save. Check required fields and unique name.';
            $_SESSION['rentals_admin_notice_error'] = true;
            return $this->redirect(url('rentals/admin/payments/' . $paymentId . '/edit'));
        }
    }

    public function savePayments(Request $request): Response
    {
        $id = max(0, $request->int('id'));
        return $id > 0 ? $this->paymentsUpdate($request, (string) $id) : $this->paymentsStore($request);
    }

    public function section(Request $request, string $section): Response
    {
        return match ($section) {
            'analytics'      => $this->analytics($request),
            'sales-report'   => $this->salesReport($request),
            'payment-report' => $this->paymentReport($request),
            'orders'         => $this->orders($request),
            'items'          => $this->items($request),
            'categories'     => $this->categories($request),
            'payments'       => $this->payments($request),
            default          => Response::notFound(),
        };
    }

    public function save(Request $request, string $section): Response
    {
        return match ($section) {
            'items'      => $this->saveItems($request),
            'categories' => $this->saveCategories($request),
            'payments'   => $this->savePayments($request),
            default      => Response::notFound(),
        };
    }

    public function toggleItems(Request $request): Response
    {
        return $this->toggleRecord($request, 'items', 'rental_items');
    }

    public function toggleCategories(Request $request): Response
    {
        return $this->toggleRecord($request, 'categories', 'rental_categories');
    }

    public function togglePayments(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $id = max(0, $request->int('id'));
        if ($id > 0) {
            $method = $this->db()->selectOne('SELECT type, account_name, account_number, is_active FROM payment_methods WHERE id = ?', [$id]);
            if ($method !== null && (int) $method['is_active'] === 0 && $method['type'] === 'manual'
                && (trim((string) $method['account_name']) === '' || trim((string) $method['account_number']) === '')) {
                $_SESSION['rentals_admin_notice'] = 'Add an account name and number before activating a manual method.';
                return $this->redirect(url('rentals/admin/payments/' . $id . '/edit'));
            }
            $this->db()->update('UPDATE payment_methods SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?', [$id]);
            $_SESSION['rentals_admin_notice'] = 'Active status updated.';
        }
        return $this->redirect(url('rentals/admin/payments'));
    }

    // ── Availability / blackouts ───────────────────────────────

    public function availability(Request $request, string $id): Response
    {
        RentalDiagnostic::trace('admin-availability', 'start', ['item_id' => (int) $id]);
        if ($denial = $this->deny()) { return $denial; }
        $item = $this->db()->selectOne('SELECT * FROM rental_items WHERE id = ? AND is_service = 0', [(int) $id]);
        if ($item === null) { RentalDiagnostic::failure('admin-availability', 'item-missing', ['response_status' => 404]); return Response::notFound(); }
        $month   = $request->string('month');
        $first   = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        $current = new \DateTimeImmutable('first day of this month');
        if (!$first || $first->format('Y-m') !== $month || $first < $current->modify('-12 months')
            || $first > $current->modify('+12 months')) {
            RentalDiagnostic::failure('admin-availability', 'dates', ['date_range_valid' => false, 'response_status' => 422]);
            return Response::json(['ok' => false, 'message' => 'Choose a month within the availability calendar.'], 422)->noCache();
        }
        $item['db_id'] = (int) $item['id'];
        $dates = (new RentalCatalog($this->db()))->availabilityByDate($item, $first->format('Y-m-d'), $first->modify('last day of this month')->format('Y-m-d'));
        $days  = [];
        foreach ($dates as $date => $day) {
            if ((int) $item['is_active'] !== 1 || $item['availability_status'] !== 'available') { $day['remaining'] = 0; }
            $days[] = ['date' => $date] + $day;
        }
        return Response::json(['ok' => true, 'month' => $month, 'capacity' => (int) $item['available_quantity'],
            'stock_status' => (string) $item['availability_status'], 'active' => (int) $item['is_active'] === 1,
            'days' => $days])->noCache();
    }

    public function saveBlackout(Request $request, string $id): Response
    {
        RentalDiagnostic::trace('admin-availability', 'block-write', ['item_id' => (int) $id]);
        if ($denial = $this->deny()) { return $denial; }
        $itemId = (int) $id;
        $return = url('rentals/admin/items/' . $itemId . '/edit#equipment-availability');
        if (!in_array($request->string('availability_action'), ['', 'blocked', 'available'], true)) {
            RentalDiagnostic::failure('admin-availability', 'action', ['validation' => 'failed', 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Choose Block dates or Make available. Customer reservations are managed through orders.';
            return $this->redirect($return);
        }
        $start  = $request->string('start_date');
        $end    = $request->string('end_date');
        $first  = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $last   = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($itemId < 1 || !$first || !$last || $first->format('Y-m-d') !== $start
            || $last->format('Y-m-d') !== $end || $first > $last
            || $this->db()->selectOne('SELECT id FROM rental_items WHERE id = ? AND is_service = 0', [$itemId]) === null) {
            RentalDiagnostic::failure('admin-availability', 'dates', ['date_range_valid' => false, 'response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Choose a valid equipment item and date range.';
            return $this->redirect($return);
        }
        RentalDiagnostic::trace('admin-availability', 'validated', ['date_range_valid' => true, 'start_date' => $start, 'end_date' => $end, 'reservation_conflict' => 'skipped']);
        if ($request->string('availability_action') === 'available') {
            RentalDiagnostic::trace('admin-availability', 'block-remove', ['block_operation' => 'pending']);
            $this->db()->transaction(function ($db) use ($itemId, $start, $end, $first, $last): void {
                $db->selectOne('SELECT id FROM rental_items WHERE id = ? FOR UPDATE', [$itemId]);
                $blocks = $db->select('SELECT * FROM rental_item_blackouts WHERE rental_item_id = ? AND is_active = 1 AND start_date <= ? AND end_date >= ? FOR UPDATE', [$itemId, $end, $start]);
                foreach ($blocks as $block) {
                    $db->update('UPDATE rental_item_blackouts SET is_active = 0 WHERE id = ?', [$block['id']]);
                    foreach ([[$block['start_date'], $first->modify('-1 day')->format('Y-m-d')], [$last->modify('+1 day')->format('Y-m-d'), $block['end_date']]] as [$from, $through]) {
                        if ($from <= $through) {
                            $db->insert('INSERT INTO rental_item_blackouts (rental_item_id, start_date, end_date, note) VALUES (?, ?, ?, ?)', [$itemId, $from, $through, $block['note']]);
                        }
                    }
                }
            });
            RentalDiagnostic::trace('admin-availability', 'block-remove', ['block_operation' => 'success']);
            $_SESSION['rentals_admin_notice'] = 'Manual blocks removed for the selected dates. Customer reservations and equipment stock status still apply.';
            return $this->redirect($return);
        }
        RentalDiagnostic::trace('admin-availability', 'block-insert', ['block_operation' => 'pending']);
        $this->db()->insert('INSERT INTO rental_item_blackouts (rental_item_id, start_date, end_date, note) VALUES (?, ?, ?, ?)',
            [$itemId, $start, $end, substr($request->string('note'), 0, 255) ?: null]);
        RentalDiagnostic::trace('admin-availability', 'block-insert', ['block_operation' => 'success']);
        $_SESSION['rentals_admin_notice'] = 'Dates blocked. Existing reservations remain unchanged.';
        return $this->redirect($return);
    }

    public function toggleBlackout(Request $request, string $id, string $blackoutId): Response
    {
        RentalDiagnostic::trace('admin-availability', 'block-toggle', ['item_id' => (int) $id]);
        if ($denial = $this->deny()) { return $denial; }
        $itemId = (int) $id;
        $this->db()->update('UPDATE rental_item_blackouts SET is_active = IF(is_active = 1, 0, 1) WHERE id = ? AND rental_item_id = ?',
            [(int) $blackoutId, $itemId]);
        $_SESSION['rentals_admin_notice'] = 'Manual date block updated. Customer reservations still apply.';
        return $this->redirect(url('rentals/admin/items/' . $itemId . '/edit#equipment-availability'));
    }

    // ── Payment proof ──────────────────────────────────────────

    public function viewProof(Request $request, string $id): Response
    {
        RentalDiagnostic::trace('admin-proof', 'start', ['order_id' => (int) $id]);
        if ($this->deny() !== null) { RentalDiagnostic::failure('admin-proof', 'authorization', ['auth' => 'denied', 'response_status' => 403]); return Response::forbidden()->noCache(); }
        RentalDiagnostic::trace('admin-proof', 'order-lookup', ['db_operation' => 'pending']);
        $order = $this->db()->selectOne('SELECT payment_proof_path FROM order_header WHERE id = ?', [(int) $id]);
        RentalDiagnostic::trace('admin-proof', 'order-lookup', ['db_operation' => 'success', 'db_path' => empty($order['payment_proof_path']) ? 'missing' : 'present']);
        if ($order === null) { RentalDiagnostic::failure('admin-proof', 'order-missing', ['response_status' => 404]); }
        return $order === null ? Response::notFound()->noCache() : RentalPaymentProof::response($order['payment_proof_path'], 'admin-proof');
    }

    public function reviewProof(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $orderId  = (int) $id;
        $decision = $request->string('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) { return Response::forbidden('Invalid review action.'); }
        try {
            $reviewed = $this->db()->transaction(function (\App\Core\Database $db) use ($orderId, $decision): array {
                $order = $db->selectOne('SELECT h.payment_proof_path, h.payment_status, h.order_number, h.customer_email, h.status_token, p.type AS payment_type
                    FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
                    WHERE h.id = ? FOR UPDATE', [$orderId]);
                if ($order === null) { throw new \InvalidArgumentException('That order no longer exists.'); }
                if ($decision === 'approved' && RentalPaymentProof::path($order['payment_proof_path']) === null) {
                    throw new \InvalidArgumentException('No payment proof is available for review.');
                }
                if ((int) $order['payment_status'] !== RentalPaymentStatus::PENDING) {
                    throw new \InvalidArgumentException('Only pending payments can be reviewed.');
                }
                if ($decision === 'approved' && $order['payment_type'] === 'gateway') {
                    throw new \InvalidArgumentException('Gateway payments need an approved integration before review.');
                }
                $status = $decision === 'approved' ? RentalPaymentStatus::APPROVED : RentalPaymentStatus::REJECTED;
                $db->update('UPDATE order_header SET payment_status = ?, payment_reviewed_at = CURRENT_TIMESTAMP,
                    payment_reviewed_by = ?, paid_at = IF(? = 1, CURRENT_TIMESTAMP, NULL)
                    WHERE id = ?', [$status, (int) $this->adminUser['id'], $status, $orderId]);
                return $order;
            });
            RentalNotification::send($reviewed['customer_email'], $reviewed['order_number'], (string) $reviewed['status_token'],
                $decision === 'approved' ? RentalPaymentStatus::APPROVED : RentalPaymentStatus::REJECTED);
            $_SESSION['rentals_admin_notice'] = 'Payment proof ' . $decision . '.';
        } catch (\InvalidArgumentException $e) {
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
        } catch (Throwable $e) {
            RentalDiagnostic::exception('admin-write', 'save', $e, ['response_status' => 302]);
            $_SESSION['rentals_admin_notice'] = 'Payment proof review could not be saved.';
        }
        return $this->redirect(url('rentals/admin/orders/' . $orderId));
    }

    private function toggleRecord(Request $request, string $section, string $table): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $id = max(0, $request->int('id'));
        if ($id > 0) {
            $this->db()->update("UPDATE {$table} SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?", [$id]);
            $_SESSION['rentals_admin_notice'] = 'Active status updated.';
        }
        return $this->redirect(url('rentals/admin/' . $section));
    }

    private function deny(): ?Response
    {
        $user = (new RentalAccount($this->db(), new RentalCart()))->current();
        $role = strtolower((string) ($user['role'] ?? 'guest'));
        $role = in_array($role, ['guest', 'customer', 'admin', 'superadmin'], true) ? $role : 'customer';
        RentalDiagnostic::trace('auth', 'authorization', ['role' => $role, 'auth' => $user === null ? 'denied' : 'ok']);
        if ($user === null) { RentalDiagnostic::failure('auth', 'authorization', ['role' => 'guest', 'auth' => 'denied', 'response_status' => 302]); return $this->redirect(url('rentals/account')); }
        if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'superadmin'], true)) {
            RentalDiagnostic::failure('auth', 'authorization', ['role' => $role, 'auth' => 'denied', 'response_status' => 403]);
            return $this->render('rentals.forbidden', [], 403)->noCache();
        }
        $this->adminUser = $user;
        return null;
    }

    private function popNotice(): ?string
    {
        $notice = $_SESSION['rentals_admin_notice'] ?? null;
        unset($_SESSION['rentals_admin_notice']);
        return is_string($notice) ? $notice : null;
    }

    private function popNoticeError(): bool
    {
        $error = (bool) ($_SESSION['rentals_admin_notice_error'] ?? false);
        unset($_SESSION['rentals_admin_notice_error']);
        return $error;
    }

    /** @return list<string> */
    private function localImages(): array
    {
        $images = [];
        foreach (glob(BASE_PATH . '/media/rentals-*') ?: [] as $file) {
            $path = 'media/' . basename($file);
            if (RentalCatalog::imagePath($path) !== null) { $images[] = $path; }
        }
        return $images;
    }

    private function validateAndSaveItem(Request $request, int $id): void
    {
        RentalDiagnostic::trace('product-crud', 'start', ['record_id' => $id]);
        $categoryValue = $request->string('category_id');
        if (!preg_match('/^[1-9][0-9]{0,17}$/D', $categoryValue)) {
            RentalDiagnostic::trace('product-crud', 'validate-category', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Category: choose an existing category from the list.');
        }
        $categoryId = (int) $categoryValue;
        if ($this->db()->selectOne('SELECT id FROM rental_categories WHERE id = ?', [$categoryId]) === null) {
            RentalDiagnostic::trace('product-crud', 'validate-category', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Choose an existing category.');
        }

        $name = trim($request->string('name'));
        if ($name === '') {
            RentalDiagnostic::trace('product-crud', 'validate-name', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Name: enter a value.');
        }
        if (mb_strlen($name, 'UTF-8') > 190) {
            RentalDiagnostic::trace('product-crud', 'validate-name', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Name: use valid text with 190 characters or fewer.');
        }

        $existing = $id > 0 ? $this->db()->selectOne('SELECT id, slug, image_path, is_active FROM rental_items WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            RentalDiagnostic::trace('product-crud', 'validate-fields', ['validation' => 'failed']);
            throw new \InvalidArgumentException('That product no longer exists.');
        }

        $slugInput = trim($request->string('slug'));
        if (mb_strlen($slugInput, 'UTF-8') > 190) {
            RentalDiagnostic::trace('product-crud', 'validate-slug', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Slug: use valid text with 190 characters or fewer.');
        }

        if ($id > 0 && $slugInput === '' && !empty($existing['slug'])) {
            $slug = $existing['slug'];
        } else {
            $baseSlug = $slugInput !== '' ? $slugInput : $name;
            $slug = $this->normalizeSlug($baseSlug);
            $slug = $this->uniqueItemSlug($slug, $id);
        }

        $skuInput = trim($request->string('sku'));
        if ($skuInput !== '') {
            if (mb_strlen($skuInput, 'UTF-8') > 80) {
                RentalDiagnostic::trace('product-crud', 'validate-sku', ['validation' => 'failed']);
                throw new \InvalidArgumentException('SKU: use valid text with 80 characters or fewer.');
            }
            $duplicateSku = $this->db()->selectValue('SELECT id FROM rental_items WHERE sku = ? AND id <> ? LIMIT 1', [$skuInput, $id]);
            if ($duplicateSku !== null) {
                RentalDiagnostic::trace('product-crud', 'validate-sku', ['validation' => 'failed']);
                throw new \InvalidArgumentException('That SKU is already used by another product. Enter a different SKU or leave it blank.');
            }
            $sku = $skuInput;
        } else {
            $sku = null;
        }

        $description = $request->string('description');
        if (mb_strlen($description, 'UTF-8') > 5000) {
            RentalDiagnostic::trace('product-crud', 'validate-fields', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Description: use valid text with 5000 characters or fewer.');
        }

        $idealUse = $request->string('ideal_use');
        if (mb_strlen($idealUse, 'UTF-8') > 500) {
            RentalDiagnostic::trace('product-crud', 'validate-fields', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Ideal use: use valid text with 500 characters or fewer.');
        }
        $idealUse = $idealUse !== '' ? $idealUse : null;

        $type = $request->string('is_service');
        if (!in_array($type, ['0', '1'], true)) {
            RentalDiagnostic::trace('product-crud', 'validate-type', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Type: choose Equipment or Service.');
        }
        $isService = (int) $type;

        $status = $request->string('availability_status');
        if (!in_array($status, self::AVAILABILITY, true)) {
            RentalDiagnostic::trace('product-crud', 'validate-status', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Choose a valid availability status.');
        }

        $rentalUnit = $request->string('rental_unit');
        if (mb_strlen($rentalUnit, 'UTF-8') > 30) {
            RentalDiagnostic::trace('product-crud', 'validate-unit', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Rental unit: use valid text with 30 characters or fewer.');
        }
        $rentalUnit = $rentalUnit !== '' ? $rentalUnit : null;

        $rateInput = $request->string('rental_rate');
        if (!preg_match('/^(?:[0-9]{1,10}(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/D', $rateInput)) {
            RentalDiagnostic::trace('product-crud', 'validate-rate', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Rate: enter an amount from 0 to 9999999999.99 with up to two decimal places.');
        }
        $rate = $rateInput;

        $depositInput = $request->string('security_deposit');
        if ($depositInput === '') {
            $deposit = '0.00';
        } else {
            if (!preg_match('/^(?:[0-9]{1,10}(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/D', $depositInput)) {
                RentalDiagnostic::trace('product-crud', 'validate-deposit', ['validation' => 'failed']);
                throw new \InvalidArgumentException('Security deposit: enter an amount from 0 to 9999999999.99 with up to two decimal places.');
            }
            $deposit = $depositInput;
        }

        $quantityInput = $request->string('available_quantity');
        if (!preg_match('/^[0-9]{1,6}$/D', $quantityInput)) {
            RentalDiagnostic::trace('product-crud', 'validate-quantity', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Available quantity: enter a whole number from 0 to 999999.');
        }
        $quantity = (int) $quantityInput;

        if ($request->has('is_active')) {
            $activeInput = $request->string('is_active');
            if (!in_array($activeInput, ['0', '1'], true)) {
                RentalDiagnostic::trace('product-crud', 'validate-status', ['validation' => 'failed']);
                throw new \InvalidArgumentException('Choose an active status.');
            }
            $isActive = (int) $activeInput;
        } else {
            $isActive = $id > 0 ? (int) ($existing['is_active'] ?? 1) : 1;
        }

        $uploaded = RentalManagedImage::store($request->file('product_image'), 'product');
        $imagePath = $uploaded ?? ($existing['image_path'] ?? null);

        $params = [
            $categoryId,
            $name,
            $slug,
            $sku,
            $description !== '' ? $description : null,
            $idealUse,
            $imagePath,
            $isService,
            $status,
            $rentalUnit,
            $rate,
            $deposit,
            $quantity,
            $isActive,
        ];

        try {
            if ($id > 0) {
                RentalDiagnostic::trace('product-crud', 'db-update', ['db_operation' => 'pending']);
                $this->db()->update(
                    'UPDATE rental_items SET
                        category_id = ?,
                        name = ?,
                        slug = ?,
                        sku = ?,
                        description = ?,
                        ideal_use = ?,
                        image_path = ?,
                        is_service = ?,
                        availability_status = ?,
                        rental_unit = ?,
                        rental_rate = ?,
                        security_deposit = ?,
                        available_quantity = ?,
                        is_active = ?
                    WHERE id = ?',
                    [...$params, $id]
                );
            } else {
                RentalDiagnostic::trace('product-crud', 'db-insert', ['db_operation' => 'pending']);
                $this->db()->insert(
                    'INSERT INTO rental_items (
                        category_id,
                        name,
                        slug,
                        sku,
                        description,
                        ideal_use,
                        image_path,
                        is_service,
                        availability_status,
                        rental_unit,
                        rental_rate,
                        security_deposit,
                        available_quantity,
                        is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $params
                );
            }
        } catch (Throwable $e) {
            RentalDiagnostic::exception('product-crud', 'db-save', $e, ['db_operation' => 'failed', 'db_path_saved' => false]);
            RentalManagedImage::remove($uploaded, 'product');
            throw $e;
        }

        RentalDiagnostic::trace('product-upload', 'db-saved', ['db_path_saved' => $uploaded !== null, 'db_operation' => 'success']);
        if ($uploaded !== null && !empty($existing['image_path'])) {
            RentalManagedImage::remove($existing['image_path'], 'product');
        }
    }

    private function normalizeSlug(string $source): string
    {
        if (function_exists('iconv')) {
            $source = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source) ?: $source;
        }
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $source), '-'));
        return substr($slug !== '' ? $slug : 'product', 0, 190);
    }

    private function uniqueItemSlug(string $base, int $ignoreId = 0): string
    {
        $slug = $base;
        $suffix = 1;
        while ($this->db()->selectValue('SELECT id FROM rental_items WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $ignoreId]) !== null) {
            $suffix++;
            $suffixStr = '-' . $suffix;
            $slug = substr($base, 0, 190 - strlen($suffixStr)) . $suffixStr;
        }
        return $slug;
    }

    private function saveProductDraft(Request $request, int $id): void
    {
        $fields = [];
        foreach ([
            'category_id', 'name', 'slug', 'sku', 'description', 'ideal_use', 'is_service',
            'availability_status', 'rental_unit', 'rental_rate', 'security_deposit', 'available_quantity', 'is_active',
        ] as $field) {
            $fields[$field] = substr($request->string($field), 0, 5000);
        }
        $_SESSION['rentals_admin_product_draft'] = ['id' => $id, 'fields' => $fields];
    }

    private function validateAndSaveCategory(Request $request, int $id): void
    {
        RentalDiagnostic::trace('category-crud', 'start', ['record_id' => $id]);
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            RentalDiagnostic::trace('category-crud', 'validate-name', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Enter a valid name.');
        }
        $duplicateName = $this->db()->selectValue(
            'SELECT id FROM rental_categories WHERE LOWER(TRIM(name)) = LOWER(?) AND id <> ? LIMIT 1',
            [$name, $id]
        );
        RentalDiagnostic::trace('category-crud', 'duplicate-name', ['duplicate_name' => $duplicateName !== null]);
        if ($duplicateName !== null) {
            RentalDiagnostic::trace('category-crud', 'validate-category', ['validation' => 'failed']);
            throw new \InvalidArgumentException('A category with this name already exists.');
        }

        $existing = $id > 0 ? $this->db()->selectOne('SELECT id, slug, is_active FROM rental_categories WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            RentalDiagnostic::trace('category-crud', 'validate-category', ['validation' => 'failed']);
            throw new \InvalidArgumentException('That category no longer exists.');
        }

        $slugInput = strtolower(trim($request->string('slug')));
        if ($slugInput === '') {
            $slugInput = $name;
        }
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $slugInput), '-'));
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > 150) {
            RentalDiagnostic::trace('category-crud', 'validate-slug', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Use a lowercase slug with letters, numbers and hyphens.');
        }

        $baseSlug = $slug;
        $suffix = 1;
        while ($this->db()->selectValue('SELECT id FROM rental_categories WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $id]) !== null) {
            $suffix++;
            $suffixStr = '-' . $suffix;
            $slug = substr($baseSlug, 0, 150 - strlen($suffixStr)) . $suffixStr;
        }

        $description = trim($request->string('description'));
        if (mb_strlen($description, 'UTF-8') > 5000) {
            $description = mb_substr($description, 0, 5000, 'UTF-8');
        }
        $description = $description !== '' ? $description : null;

        $imagePath = trim($request->string('image_path'));
        if ($imagePath !== '') {
            $validImage = RentalCatalog::imagePath($imagePath);
            if ($validImage === null) {
                RentalDiagnostic::trace('category-crud', 'validate-image', ['validation' => 'failed']);
                throw new \InvalidArgumentException('Choose an existing local image from the available Rentals images.');
            }
            $imagePath = $validImage;
        } else {
            $imagePath = null;
        }

        if ($request->has('is_active')) {
            $activeVal = $request->string('is_active');
            if (!in_array($activeVal, ['0', '1'], true)) {
                RentalDiagnostic::trace('category-crud', 'validate-status', ['validation' => 'failed']);
                throw new \InvalidArgumentException('Choose an active status.');
            }
            $isActive = (int) $activeVal;
        } else {
            $isActive = $id > 0 ? (int) ($existing['is_active'] ?? 1) : 1;
        }

        if ($id > 0) {
            RentalDiagnostic::trace('category-crud', 'db-update', ['db_operation' => 'pending']);
            $this->db()->update(
                'UPDATE rental_categories SET name = ?, slug = ?, description = ?, image_path = ?, is_active = ? WHERE id = ?',
                [$name, $slug, $description, $imagePath, $isActive, $id]
            );
        } else {
            RentalDiagnostic::trace('category-crud', 'db-insert', ['db_operation' => 'pending']);
            $this->db()->insert(
                'INSERT INTO rental_categories (name, slug, description, image_path, is_active) VALUES (?, ?, ?, ?, ?)',
                [$name, $slug, $description, $imagePath, $isActive]
            );
        }
    }

    private function validateAndSavePayment(Request $request, int $id): void
    {
        RentalDiagnostic::trace('payment-crud', 'start', ['record_id' => $id]);
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            RentalDiagnostic::trace('payment-crud', 'validate-name', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Enter a valid name.');
        }

        $type = strtolower(trim($request->string('type')));
        $existing = $id > 0 ? $this->db()->selectOne('SELECT id, type, is_active FROM payment_methods WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            RentalDiagnostic::trace('payment-crud', 'validate-fields', ['validation' => 'failed']);
            throw new \InvalidArgumentException('That payment method no longer exists.');
        }

        $existingType = $existing['type'] ?? '';
        if (!in_array($type, ['manual', 'gateway'], true) && $type !== $existingType) {
            RentalDiagnostic::trace('payment-crud', 'validate-fields', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Choose Manual or Payment Gateway.');
        }

        $duplicate = $this->db()->selectOne(
            'SELECT id FROM payment_methods WHERE LOWER(name) = LOWER(?) AND type = ? AND id <> ? LIMIT 1',
            [$name, $type, $id]
        );
        RentalDiagnostic::trace('payment-crud', 'duplicate-name', ['duplicate_name' => $duplicate !== null]);
        if ($duplicate !== null) {
            RentalDiagnostic::trace('payment-crud', 'validate-type', ['validation' => 'failed']);
            throw new \InvalidArgumentException('A payment method with this name and type already exists.');
        }

        $provider = trim($request->string('provider'));
        $provider = $provider !== '' ? substr($provider, 0, 120) : null;

        $accountName = trim($request->string('account_name'));
        $accountName = $accountName !== '' ? substr($accountName, 0, 190) : null;

        $accountNumber = trim($request->string('account_number'));
        $accountNumber = $accountNumber !== '' ? substr($accountNumber, 0, 100) : null;

        if ($request->has('is_active')) {
            $activeVal = $request->string('is_active');
            if (!in_array($activeVal, ['0', '1'], true)) {
                RentalDiagnostic::trace('payment-crud', 'validate-status', ['validation' => 'failed']);
                throw new \InvalidArgumentException('Choose an active status.');
            }
            $isActive = (int) $activeVal;
        } else {
            $isActive = $id > 0 ? (int) ($existing['is_active'] ?? 1) : 1;
        }

        if ($type === 'manual' && $isActive === 1 && ($accountName === null || $accountNumber === null)) {
            RentalDiagnostic::trace('payment-crud', 'validate-name', ['validation' => 'failed']);
            throw new \InvalidArgumentException('Active manual methods need an account name and number.');
        }

        RentalDiagnostic::trace('payment-crud', 'qr-column', ['qr_column' => 'pending']);
        $oldImage = $id > 0 ? $this->db()->selectValue('SELECT qr_image_path FROM payment_methods WHERE id = ?', [$id]) : null;
        RentalDiagnostic::trace('payment-crud', 'qr-column', ['qr_column' => $id > 0 ? 'yes' : 'pending']);
        $newImage = RentalManagedImage::store($request->file('qr_image'), 'qr');
        try {
            if ($id > 0) {
                RentalDiagnostic::trace('payment-crud', 'db-update', ['db_operation' => 'pending']);
                $this->db()->update(
                    'UPDATE payment_methods SET name = ?, type = ?, provider = ?, account_name = ?, account_number = ?, is_active = ?, qr_image_path = ? WHERE id = ?',
                    [$name, $type, $provider, $accountName, $accountNumber, $isActive, $newImage ?? $oldImage, $id]
                );
            } else {
                RentalDiagnostic::trace('payment-crud', 'db-insert', ['db_operation' => 'pending']);
                $this->db()->insert(
                    'INSERT INTO payment_methods (name, type, provider, account_name, account_number, is_active, qr_image_path) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$name, $type, $provider, $accountName, $accountNumber, $isActive, $newImage]
                );
            }
        } catch (Throwable $e) {
            RentalDiagnostic::exception('payment-crud', 'db-save', $e, ['db_operation' => 'failed', 'db_path_saved' => false]);
            if ($newImage !== null) { RentalManagedImage::remove($newImage, 'qr'); }
            throw $e;
        }
        RentalDiagnostic::trace('qr-upload', 'db-saved', ['db_path_saved' => $newImage !== null, 'db_operation' => 'success']);
        if ($newImage !== null && $oldImage !== null) { RentalManagedImage::remove($oldImage, 'qr'); }
    }
}
