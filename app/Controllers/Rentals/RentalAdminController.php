<?php

declare(strict_types=1);

namespace App\Controllers\Rentals;

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
    private const SECTIONS = ['analytics', 'sales-report', 'payment-report', 'items', 'categories', 'orders', 'payments', 'customers'];
    private const WRITABLE_SECTIONS = ['items', 'categories', 'payments'];
    private const AVAILABILITY = ['available', 'unavailable', 'out_of_stock', 'reserved', 'inquire'];
    private ?array $adminUser = null;

    public function index(Request $request): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $insights = new RentalAdminInsights($this->db());
        $dashboard = $insights->dashboard();
        $notice = $_SESSION['rentals_admin_notice'] ?? null;
        unset($_SESSION['rentals_admin_notice']);
        return $this->render('rentals.admin', [
            'section' => 'dashboard', 'metrics' => $dashboard['metrics'],
            'recentOrders' => $dashboard['recentOrders'], 'upcomingRentals' => $dashboard['upcomingRentals'],
            'notice' => $notice,
            'adminUser' => $this->adminUser,
        ])->noCache();
    }

    public function section(Request $request, string $section): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        if (!in_array($section, self::SECTIONS, true)) { return Response::notFound(); }
        $id = max(0, $request->int('edit'));
        $showEditor = $id > 0 || ($request->string('new') === '1' && in_array($section, ['items', 'categories', 'payments'], true));
        $data = ['section' => $section, 'rows' => [], 'record' => null, 'categories' => [], 'images' => [],
            'details' => [], 'showEditor' => $showEditor, 'adminUser' => $this->adminUser];
        $db = $this->db();

        if ($section === 'analytics') {
            $data['insights'] = (new RentalAdminInsights($db))->analytics($request);
        } elseif ($section === 'sales-report') {
            $data['report'] = (new RentalAdminInsights($db))->salesReport($request);
        } elseif ($section === 'payment-report') {
            $data['report'] = (new RentalAdminInsights($db))->paymentReport($request);
        } elseif ($section === 'items') {
            $term = substr($request->string('q'), 0, 100);
            $categoryId = max(0, $request->int('category'));
            $type = in_array($request->string('type'), ['equipment', 'service'], true) ? $request->string('type') : 'all';
            $typeValue = $type === 'equipment' ? 0 : ($type === 'service' ? 1 : -1);
            $data['term'] = $term;
            $data['categoryFilter'] = $categoryId;
            $data['typeFilter'] = $type;
            $data['categories'] = $db->select('SELECT id, name, is_active FROM rental_categories ORDER BY name, id');
            $data['rows'] = $db->select(
                'SELECT i.*, c.name AS category_name FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
                 WHERE (? = 0 OR i.category_id = ?) AND (? = \'\' OR i.name LIKE ? OR i.sku LIKE ?)
                   AND (? = -1 OR i.is_service = ?)
                 ORDER BY i.updated_at DESC, i.id DESC LIMIT 200',
                [$categoryId, $categoryId, $term, '%' . $term . '%', '%' . $term . '%', $typeValue, $typeValue]
            );
            $data['record'] = $id ? $db->selectOne('SELECT * FROM rental_items WHERE id = ?', [$id]) : null;
            $data['blackouts'] = $id ? $db->select('SELECT * FROM rental_item_blackouts WHERE rental_item_id = ? ORDER BY start_date DESC, id DESC', [$id]) : [];
        } elseif ($section === 'categories') {
            $data['rows'] = $db->select('SELECT * FROM rental_categories ORDER BY name, id');
            $data['record'] = $id ? $db->selectOne('SELECT * FROM rental_categories WHERE id = ?', [$id]) : null;
        } elseif ($section === 'orders') {
            $term = substr($request->string('q'), 0, 100);
            $status = $request->string('status');
            $status = in_array($status, ['0', '1', '2'], true) ? $status : 'all';
            $data['term'] = $term;
            $data['statusFilter'] = $status;
            $data['rows'] = $db->select(
                'SELECT h.*, p.name AS payment_method FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
                 WHERE (? = \'\' OR h.order_number LIKE ? OR h.customer_name LIKE ? OR h.customer_email LIKE ?)
                   AND (? = \'all\' OR h.payment_status = ?)
                 ORDER BY h.created_at DESC, h.id DESC LIMIT 200',
                [$term, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%', $status, $status]
            );
            $data['record'] = $id ? $db->selectOne('SELECT h.*, p.name AS payment_method, p.type AS payment_type FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id WHERE h.id = ?', [$id]) : null;
            if ($data['record'] !== null) {
                $data['details'] = $db->select('SELECT * FROM order_details WHERE order_header_id = ? ORDER BY id', [$id]);
            }
        } elseif ($section === 'payments') {
            $data['rows'] = $db->select('SELECT * FROM payment_methods ORDER BY name, id');
            $data['record'] = $id ? $db->selectOne('SELECT * FROM payment_methods WHERE id = ?', [$id]) : null;
        } else {
            if ($id > 0) { return Response::notFound(); }
            $data['rows'] = $db->select('SELECT id, name, email, role, last_login FROM users ORDER BY created_at DESC, id DESC LIMIT 200');
        }
        if ($id > 0 && $data['record'] === null) { return Response::notFound(); }
        if (in_array($section, ['items', 'categories'], true)) {
            foreach (glob(BASE_PATH . '/media/rentals-*') ?: [] as $file) {
                $path = 'media/' . basename($file);
                if (RentalCatalog::imagePath($path) !== null) { $data['images'][] = $path; }
            }
        }
        $data['notice'] = $_SESSION['rentals_admin_notice'] ?? null;
        unset($_SESSION['rentals_admin_notice']);
        return $this->render('rentals.admin', $data)->noCache();
    }

    public function save(Request $request, string $section): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        if (!in_array($section, self::WRITABLE_SECTIONS, true)) { return Response::notFound(); }
        $id = max(0, $request->int('id'));
        $failed = false;
        try {
            match ($section) {
                'categories' => $this->saveCategory($request, $id),
                'items' => $this->saveItem($request, $id),
                'payments' => $this->savePayment($request, $id),
            };
            $_SESSION['rentals_admin_notice'] = 'Changes saved.';
        } catch (\InvalidArgumentException $e) {
            $failed = true;
            $_SESSION['rentals_admin_notice'] = $e->getMessage();
        } catch (Throwable $e) {
            $failed = true;
            error_log('Rentals admin save failed: ' . $e->getMessage());
            $_SESSION['rentals_admin_notice'] = 'Could not save. Check required fields and unique slug or SKU.';
        }
        $query = $id > 0 ? '?edit=' . $id : ($failed && $section !== 'orders' ? '?new=1' : '');
        return $this->redirect(url('rentals/admin/' . $section . $query));
    }

    public function toggle(Request $request, string $section): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $table = match ($section) {
            'items' => 'rental_items', 'categories' => 'rental_categories', 'payments' => 'payment_methods', default => null,
        };
        if ($table === null) { return Response::notFound(); }
        $id = max(0, $request->int('id'));
        if ($id > 0) {
            if ($section === 'payments') {
                $method = $this->db()->selectOne('SELECT type, account_name, account_number, is_active FROM payment_methods WHERE id = ?', [$id]);
                if ($method !== null && (int) $method['is_active'] === 0 && $method['type'] === 'manual'
                    && (trim((string) $method['account_name']) === '' || trim((string) $method['account_number']) === '')) {
                    $_SESSION['rentals_admin_notice'] = 'Add an account name and number before activating a manual method.';
                    return $this->redirect(url('rentals/admin/payments?edit=' . $id));
                }
            }
            $this->db()->update("UPDATE {$table} SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?", [$id]);
            $_SESSION['rentals_admin_notice'] = 'Active status updated.';
        }
        return $this->redirect(url('rentals/admin/' . $section));
    }

    public function saveBlackout(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $itemId = (int) $id;
        $return = url('rentals/admin/items?edit=' . $itemId);
        $start = $request->string('start_date');
        $end = $request->string('end_date');
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($itemId < 1 || !$first || !$last || $first->format('Y-m-d') !== $start
            || $last->format('Y-m-d') !== $end || $first > $last
            || $this->db()->selectOne('SELECT id FROM rental_items WHERE id = ?', [$itemId]) === null) {
            $_SESSION['rentals_admin_notice'] = 'Choose a valid equipment item and date range.';
            return $this->redirect($return);
        }
        $this->db()->insert('INSERT INTO rental_item_blackouts (rental_item_id, start_date, end_date, note) VALUES (?, ?, ?, ?)',
            [$itemId, $start, $end, substr($request->string('note'), 0, 255) ?: null]);
        $_SESSION['rentals_admin_notice'] = 'Dates blocked. Existing reservations remain unchanged.';
        return $this->redirect($return);
    }

    public function toggleBlackout(Request $request, string $id, string $blackoutId): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $itemId = (int) $id;
        $this->db()->update('UPDATE rental_item_blackouts SET is_active = IF(is_active = 1, 0, 1) WHERE id = ? AND rental_item_id = ?',
            [(int) $blackoutId, $itemId]);
        $_SESSION['rentals_admin_notice'] = 'Manual date block updated. Customer reservations still apply.';
        return $this->redirect(url('rentals/admin/items?edit=' . $itemId));
    }

    public function viewProof(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $order = $this->db()->selectOne('SELECT payment_proof_path FROM order_header WHERE id = ?', [(int) $id]);
        return $order === null ? Response::notFound() : RentalPaymentProof::response($order['payment_proof_path']);
    }

    public function reviewProof(Request $request, string $id): Response
    {
        if ($denial = $this->deny()) { return $denial; }
        $orderId = (int) $id;
        $decision = $request->string('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) { return Response::forbidden('Invalid review action.'); }
        try {
            $reviewed = $this->db()->transaction(function (\App\Core\Database $db) use ($orderId, $decision): array {
                $order = $db->selectOne('SELECT h.payment_proof_path, h.payment_status, h.order_number, h.customer_email, h.status_token, p.type AS payment_type
                    FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
                    WHERE h.id = ? FOR UPDATE', [$orderId]);
                if ($order === null || RentalPaymentProof::path($order['payment_proof_path']) === null) {
                    throw new \InvalidArgumentException('No payment proof is available for review.');
                }
                if ((int) $order['payment_status'] !== RentalPaymentStatus::PENDING) {
                    throw new \InvalidArgumentException('Only pending payments can be reviewed.');
                }
                if ($order['payment_type'] === 'gateway') {
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
            error_log('Rentals proof review failed: ' . $e->getMessage());
            $_SESSION['rentals_admin_notice'] = 'Payment proof review could not be saved.';
        }
        return $this->redirect(url('rentals/admin/orders?edit=' . $orderId));
    }

    private function deny(): ?Response
    {
        $user = (new RentalAccount($this->db(), new RentalCart()))->current();
        if ($user === null) { return $this->redirect(url('rentals/account')); }
        if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'superadmin'], true)) {
            return Response::forbidden('Rentals admin access is restricted.');
        }
        $this->adminUser = $user;
        return null;
    }

    private function name(Request $request, int $max = 190): string
    {
        $name = $request->string('name');
        if ($name === '' || strlen($name) > $max) { throw new \InvalidArgumentException('Enter a valid name.'); }
        return $name;
    }

    private function slug(Request $request, int $max = 190): string
    {
        $slug = strtolower($request->string('slug'));
        if ($slug === '' || strlen($slug) > $max || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new \InvalidArgumentException('Use a lowercase slug with letters, numbers and hyphens.');
        }
        return $slug;
    }

    private function image(Request $request): ?string
    {
        $path = $request->string('image_path');
        if ($path === '') { return null; }
        $valid = RentalCatalog::imagePath($path);
        if ($valid === null) { throw new \InvalidArgumentException('Choose an existing local image from the available Rentals images.'); }
        return $valid;
    }

    private function saveCategory(Request $request, int $id): void
    {
        $name = $this->name($request, 120);
        $slug = $this->slug($request, 150);
        $description = substr($request->string('description'), 0, 5000);
        $image = $this->image($request);
        $active = $this->active($request, 'rental_categories', $id);
        if ($id > 0) {
            $this->requireRecord('rental_categories', $id);
            $this->db()->update('UPDATE rental_categories SET name = ?, slug = ?, description = ?, image_path = ?, is_active = ? WHERE id = ?', [$name, $slug, $description, $image, $active, $id]);
        } else {
            $this->db()->insert('INSERT INTO rental_categories (name, slug, description, image_path, is_active) VALUES (?, ?, ?, ?, ?)', [$name, $slug, $description, $image, $active]);
        }
    }

    private function saveItem(Request $request, int $id): void
    {
        $category = max(0, $request->int('category_id'));
        if ($this->db()->selectOne('SELECT id FROM rental_categories WHERE id = ?', [$category]) === null) {
            throw new \InvalidArgumentException('Choose an existing category.');
        }
        $name = $this->name($request);
        $slug = $this->slug($request);
        $sku = substr($request->string('sku'), 0, 80);
        $description = substr($request->string('description'), 0, 5000);
        $ideal = substr($request->string('ideal_use'), 0, 500);
        $existing = $id > 0 ? $this->db()->selectOne('SELECT image_path FROM rental_items WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) { throw new \InvalidArgumentException('That product no longer exists.'); }
        $uploaded = RentalManagedImage::store($request->file('product_image'), 'product');
        $image = $uploaded ?? ($existing['image_path'] ?? null);
        $service = $request->string('is_service') === '1' ? 1 : 0;
        $status = $request->string('availability_status');
        if (!in_array($status, self::AVAILABILITY, true)) { throw new \InvalidArgumentException('Choose a valid availability status.'); }
        $unit = substr($request->string('rental_unit'), 0, 30);
        $rate = $this->money($request->string('rental_rate'));
        $deposit = $this->money($request->string('security_deposit'));
        $quantity = $request->int('available_quantity');
        if ($quantity < 0 || $quantity > 999999) { throw new \InvalidArgumentException('Enter a valid available quantity.'); }
        $active = $this->active($request, 'rental_items', $id);
        $values = [$category, $name, $slug, $sku !== '' ? $sku : null, $description, $ideal, $image, $service, $status, $unit !== '' ? $unit : null, $rate, $deposit, $quantity, $active];
        try {
            if ($id > 0) {
                $this->db()->update('UPDATE rental_items SET category_id = ?, name = ?, slug = ?, sku = ?, description = ?, ideal_use = ?, image_path = ?, is_service = ?, availability_status = ?, rental_unit = ?, rental_rate = ?, security_deposit = ?, available_quantity = ?, is_active = ? WHERE id = ?', [...$values, $id]);
            } else {
                $this->db()->insert('INSERT INTO rental_items (category_id, name, slug, sku, description, ideal_use, image_path, is_service, availability_status, rental_unit, rental_rate, security_deposit, available_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
            }
        } catch (Throwable $e) {
            RentalManagedImage::remove($uploaded, 'product');
            throw $e;
        }
        if ($uploaded !== null) { RentalManagedImage::remove($existing['image_path'] ?? null, 'product'); }
    }

    private function savePayment(Request $request, int $id): void
    {
        $name = $this->name($request, 120);
        $type = strtolower($request->string('type'));
        $existingType = $id > 0 ? (string) $this->db()->selectValue('SELECT type FROM payment_methods WHERE id = ?', [$id]) : '';
        if (!in_array($type, ['manual', 'gateway'], true) && $type !== $existingType) {
            throw new \InvalidArgumentException('Choose Manual or Payment Gateway.');
        }
        $existing = $id > 0 ? $this->db()->selectOne('SELECT qr_image_path FROM payment_methods WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) { throw new \InvalidArgumentException('That payment method no longer exists.'); }
        $duplicate = $this->db()->selectOne('SELECT id FROM payment_methods WHERE LOWER(name) = LOWER(?) AND type = ? AND id <> ? LIMIT 1', [$name, $type, $id]);
        if ($duplicate !== null) { throw new \InvalidArgumentException('A payment method with this name and type already exists.'); }
        $values = [$name, $type, substr($request->string('provider'), 0, 120) ?: null,
            substr($request->string('account_name'), 0, 190) ?: null, substr($request->string('account_number'), 0, 100) ?: null,
            $this->active($request, 'payment_methods', $id)];
        if ($type === 'manual' && $values[5] === 1 && ($values[3] === null || $values[4] === null)) {
            throw new \InvalidArgumentException('Active manual methods need an account name and number.');
        }
        $uploaded = RentalManagedImage::store($request->file('qr_image'), 'qr');
        $qr = $uploaded ?? ($existing['qr_image_path'] ?? null);
        try {
            if ($id > 0) {
                $this->db()->update('UPDATE payment_methods SET name = ?, type = ?, provider = ?, account_name = ?, account_number = ?, is_active = ?, qr_image_path = ? WHERE id = ?', [...$values, $qr, $id]);
            } else {
                $this->db()->insert('INSERT INTO payment_methods (name, type, provider, account_name, account_number, is_active, qr_image_path) VALUES (?, ?, ?, ?, ?, ?, ?)', [...$values, $qr]);
            }
        } catch (Throwable $e) {
            RentalManagedImage::remove($uploaded, 'qr');
            throw $e;
        }
        if ($uploaded !== null) { RentalManagedImage::remove($existing['qr_image_path'] ?? null, 'qr'); }
    }

    private function money(string $value): float
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) { throw new \InvalidArgumentException('Enter a valid nonnegative amount with up to two decimals.'); }
        return (float) $value;
    }

    private function active(Request $request, string $table, int $id): int
    {
        // Keep existing status for older clients that omit this newly exposed field.
        if (!$request->has('is_active')) {
            return $id > 0 ? (int) $this->db()->selectValue("SELECT is_active FROM {$table} WHERE id = ?", [$id]) : 1;
        }
        $value = $request->string('is_active');
        if (!in_array($value, ['0', '1'], true)) { throw new \InvalidArgumentException('Choose an active status.'); }
        return (int) $value;
    }

    private function requireRecord(string $table, int $id): void
    {
        // Table comes only from fixed controller calls, never from request input.
        if ($id < 1 || $this->db()->selectOne("SELECT id FROM {$table} WHERE id = ?", [$id]) === null) {
            throw new \InvalidArgumentException('That record no longer exists.');
        }
    }
}
