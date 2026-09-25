<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/** Query-only dashboard, analytics and reports on the eight Rentals tables. */
final class RentalAdminInsights
{
    public function __construct(private readonly Database $db) {}

    public function dashboard(): array
    {
        $scope = $this->reportScope('h');
        $status = $this->db->selectOne('SELECT COUNT(*) AS orders,
            SUM(h.payment_status = 0) AS pending,
            COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.subtotal ELSE 0 END), 0) AS sales
            FROM order_header h WHERE 1 = 1 ' . $scope);
        return [
            'metrics' => [
                'Total rental sales' => (float) $status['sales'],
                'Total orders' => (int) $status['orders'],
                'Pending payments' => (int) $status['pending'],
                'Active equipment' => (int) $this->db->selectValue('SELECT COUNT(*) FROM rental_items WHERE is_service = 0 AND is_active = 1'),
            ],
            'recentOrders' => $this->db->select('SELECT h.id, h.order_number, h.customer_name, h.payment_status,
                h.total_amount, h.created_at, p.name AS payment_method FROM order_header h
                LEFT JOIN payment_methods p ON p.id = h.payment_method_id WHERE 1 = 1 ' . $scope . ' ORDER BY h.created_at DESC, h.id DESC LIMIT 5'),
            'upcomingRentals' => $this->db->select('SELECT h.id, h.order_number, h.customer_name, h.payment_status,
                d.item_name, d.quantity, d.rental_start_date, d.rental_end_date
                FROM order_details d JOIN order_header h ON h.id = d.order_header_id
                WHERE h.payment_status IN (0, 1) AND d.rental_end_date >= CURRENT_DATE ' . $scope . '
                ORDER BY d.rental_start_date, d.id LIMIT 6'),
        ];
    }

    public function analytics(Request $request): array
    {
        $period = $request->string('period', '30d');
        $today = new \DateTimeImmutable('today');
        [$from, $to] = match ($period) {
            '7d' => [$today->modify('-6 days'), $today],
            'month' => [$today->modify('first day of this month'), $today],
            'year' => [$today->modify('first day of January this year'), $today],
            'custom' => [$this->dateObject($request->string('from')) ?? $today->modify('-29 days'),
                $this->dateObject($request->string('to')) ?? $today],
            default => [$today->modify('-29 days'), $today],
        };
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        $start = $from->format('Y-m-d 00:00:00');
        $end = $to->modify('+1 day')->format('Y-m-d 00:00:00');
        $dates = [$start, $end];
        $scope = $this->reportScope('h');
        $kpis = $this->db->selectOne('SELECT COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.subtotal ELSE 0 END), 0) AS sales,
            COALESCE(AVG(CASE WHEN h.payment_status = 1 THEN h.subtotal END), 0) AS average_sale
            FROM order_header h WHERE h.created_at >= ? AND h.created_at < ? ' . $scope, $dates);
        return [
            'filters' => ['period' => $period, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'kpis' => $kpis,
            'daily' => $this->db->select('SELECT DATE(h.created_at) AS period, COUNT(*) AS orders,
                COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.subtotal ELSE 0 END), 0) AS sales
                FROM order_header h WHERE h.created_at >= ? AND h.created_at < ? ' . $scope . '
                GROUP BY DATE(h.created_at) ORDER BY period', $dates),
            'paymentStatuses' => $this->db->select('SELECT h.payment_status AS status, COUNT(*) AS total
                FROM order_header h WHERE h.created_at >= ? AND h.created_at < ? ' . $scope . '
                GROUP BY h.payment_status ORDER BY h.payment_status', $dates),
            'topItems' => $this->db->select('SELECT d.item_name AS name, SUM(d.quantity) AS units
                FROM order_details d JOIN order_header h ON h.id = d.order_header_id
                JOIN rental_items i ON i.id = d.rental_item_id
                WHERE h.created_at >= ? AND h.created_at < ? AND h.payment_status = 1 AND i.is_service = 0 ' . $scope . '
                GROUP BY d.rental_item_id, d.item_name ORDER BY units DESC LIMIT 8', $dates),
            'categories' => $this->db->select('SELECT c.name, SUM(d.quantity) AS units
                FROM order_details d JOIN order_header h ON h.id = d.order_header_id
                JOIN rental_items i ON i.id = d.rental_item_id JOIN rental_categories c ON c.id = i.category_id
                WHERE h.created_at >= ? AND h.created_at < ? AND h.payment_status = 1 ' . $scope . '
                GROUP BY c.id, c.name ORDER BY units DESC LIMIT 8', $dates),
            'types' => $this->db->select("SELECT CASE WHEN i.is_service = 1 THEN 'Service' ELSE 'Equipment' END AS name,
                SUM(d.quantity) AS units FROM order_details d JOIN rental_items i ON i.id = d.rental_item_id
                JOIN order_header h ON h.id = d.order_header_id
                WHERE h.created_at >= ? AND h.created_at < ? AND h.payment_status = 1 $scope
                GROUP BY i.is_service ORDER BY units DESC", $dates),
            'methods' => $this->db->select("SELECT COALESCE(p.name, 'Unassigned') AS name, COUNT(*) AS orders
                FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
                WHERE h.created_at >= ? AND h.created_at < ? $scope
                GROUP BY h.payment_method_id, p.name ORDER BY orders DESC, name", $dates),
        ];
    }

    public function salesReport(Request $request): array
    {
        [$where, $bindings, $filters] = $this->commonFilters($request);
        $where .= $this->reportScope('h');
        $where .= ' AND h.payment_status = ?';
        $bindings[] = RentalPaymentStatus::APPROVED;
        $category = max(0, $request->int('category'));
        $item = max(0, $request->int('item'));
        if ($category || $item) {
            $where .= ' AND EXISTS (SELECT 1 FROM order_details f JOIN rental_items fi ON fi.id = f.rental_item_id
                WHERE f.order_header_id = h.id AND (? = 0 OR fi.category_id = ?) AND (? = 0 OR fi.id = ?))';
            array_push($bindings, $category, $category, $item, $item);
        }
        $filters += ['category' => $category, 'item' => $item];
        $rows = $this->db->select("SELECT h.id, h.order_number, h.customer_name, h.created_at,
            h.payment_status, h.subtotal, h.security_deposit, h.total_amount,
            (SELECT GROUP_CONCAT(CONCAT(d.item_name, ' ×', d.quantity) ORDER BY d.id SEPARATOR ', ')
             FROM order_details d WHERE d.order_header_id = h.id) AS items
            FROM order_header h WHERE $where ORDER BY h.created_at DESC, h.id DESC LIMIT 200", $bindings);
        foreach ($rows as &$row) {
            $row['details'] = $this->db->select(
                'SELECT item_name, quantity, rental_start_date, rental_end_date, unit_rate, line_total
                 FROM order_details WHERE order_header_id = ? ORDER BY id ASC',
                [(int) $row['id']]
            );
        }
        unset($row);
        $totals = $this->db->selectOne("SELECT COUNT(*) AS orders,
            COALESCE(SUM(h.subtotal), 0) AS approved_sales
            FROM order_header h WHERE $where", $bindings);
        return ['rows' => $rows, 'totals' => $totals, 'filters' => $filters,
            'categories' => $this->db->select('SELECT id, name FROM rental_categories ORDER BY name'),
            'items' => $this->db->select('SELECT id, name FROM rental_items ORDER BY name')];
    }

    public function paymentReport(Request $request): array
    {
        [$where, $bindings, $filters] = $this->commonFilters($request);
        $where .= $this->reportScope('h');
        $status = $request->string('payment_status');
        if (in_array($status, ['0', '1', '2'], true)) {
            $where .= ' AND h.payment_status = ?'; $bindings[] = (int) $status;
        } else { $status = ''; }
        $method = max(0, $request->int('method'));
        if ($method) { $where .= ' AND h.payment_method_id = ?'; $bindings[] = $method; }
        $filters += ['payment_status' => $status, 'method' => $method];
        $rows = $this->db->select("SELECT h.id, h.order_number, h.customer_name, h.created_at,
            h.payment_status, h.payment_reference, h.payment_reviewed_at, h.paid_at, h.total_amount,
            p.name AS payment_method FROM order_header h LEFT JOIN payment_methods p ON p.id = h.payment_method_id
            WHERE $where ORDER BY h.created_at DESC, h.id DESC LIMIT 200", $bindings);
        $totals = $this->db->selectOne("SELECT COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.subtotal ELSE 0 END), 0) AS rental_amount,
            COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.security_deposit ELSE 0 END), 0) AS security_deposits,
            COALESCE(SUM(CASE WHEN h.payment_status = 1 THEN h.total_amount ELSE 0 END), 0) AS total_collected
            FROM order_header h WHERE $where", $bindings);
        return ['rows' => $rows, 'totals' => $totals, 'filters' => $filters,
            'methods' => $this->db->select('SELECT id, name FROM payment_methods ORDER BY name')];
    }

    private function commonFilters(Request $request): array
    {
        $where = '1 = 1'; $bindings = [];
        $from = $this->dateObject($request->string('from'));
        $to = $this->dateObject($request->string('to'));
        if ($from !== null) { $where .= ' AND h.created_at >= ?'; $bindings[] = $from->format('Y-m-d 00:00:00'); }
        if ($to !== null) { $where .= ' AND h.created_at < ?'; $bindings[] = $to->modify('+1 day')->format('Y-m-d 00:00:00'); }
        return [$where, $bindings, ['from' => $from?->format('Y-m-d') ?? '', 'to' => $to?->format('Y-m-d') ?? '']];
    }

    /** Keep reports scoped to orders with persisted detail snapshots. */
    private function reportScope(string $alias): string
    {
        return " AND EXISTS (SELECT 1 FROM order_details qa WHERE qa.order_header_id = $alias.id)";
    }

    private function dateObject(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
