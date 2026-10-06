<?php

declare(strict_types=1);

namespace App\Controllers\Analytics;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Analytics Dashboard Controller
 *
 * Provides read-only access to Meta Pixel tracking data.
 * Restricted to admin users only. Reuses the existing
 * session-based auth (users table, role check).
 */
final class AnalyticsController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Auth helpers
    // ─────────────────────────────────────────────────────────────

    private function currentUser(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        $user = $this->db()->selectOne(
            'SELECT id, name, email, role FROM users WHERE id = ?',
            [$userId]
        );

        if ($user === null) {
            unset($_SESSION['user_id']);
        }

        return $user;
    }

    private function requireAdmin(): ?Response
    {
        $user = $this->currentUser();

        if ($user === null) {
            $_SESSION['analytics_after_login'] = '/analytics';
            return $this->redirect('/analytics/login');
        }

        if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
            return Response::html(
                $this->view()->render('analytics.forbidden', ['user' => $user]),
                403
            );
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Login / Logout
    // ─────────────────────────────────────────────────────────────

    public function loginShow(Request $request): Response
    {
        $user = $this->currentUser();
        if ($user !== null && strtolower((string) ($user['role'] ?? '')) === 'admin') {
            return $this->redirect('/analytics');
        }

        return $this->render('analytics.login', [
            'error' => $_SESSION['analytics_login_error'] ?? null,
            'email' => $_SESSION['analytics_login_email'] ?? '',
        ])->noCache();
    }

    public function loginSubmit(Request $request): Response
    {
        $error = null;
        unset($_SESSION['analytics_login_error'], $_SESSION['analytics_login_email']);

        $email    = strtolower(trim($request->string('email')));
        $password = $request->string('password');

        $user = $this->db()->selectOne(
            'SELECT id, name, email, password, role FROM users WHERE email = ?',
            [$email]
        );

        $valid = $user !== null && password_verify($password, (string) $user['password']);

        if (!$valid) {
            $_SESSION['analytics_login_error'] = 'Email or password is incorrect.';
            $_SESSION['analytics_login_email'] = $email;
            return $this->redirect('/analytics/login');
        }

        if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
            $_SESSION['analytics_login_error'] = 'Your account does not have access to Analytics.';
            $_SESSION['analytics_login_email'] = $email;
            return $this->redirect('/analytics/login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        $destination = $_SESSION['analytics_after_login'] ?? '/analytics';
        unset($_SESSION['analytics_after_login']);

        return $this->redirect($destination);
    }

    public function logout(Request $request): Response
    {
        unset($_SESSION['user_id'], $_SESSION['analytics_after_login']);
        return $this->redirect('/analytics/login');
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────

    public function dashboard(Request $request): Response
    {
        if ($denial = $this->requireAdmin()) {
            return $denial;
        }

        $db   = $this->db();
        $user = $this->currentUser();

        $totalEvents = (int) $db->selectValue('SELECT COUNT(*) FROM tracking_events');
        $totalVisits = (int) $db->selectValue('SELECT COUNT(*) FROM landing_page_visits');
        $todayEvents = (int) $db->selectValue(
            'SELECT COUNT(*) FROM tracking_events WHERE DATE(occurred_at) = CURDATE()'
        );
        $todayVisits = (int) $db->selectValue(
            'SELECT COUNT(*) FROM landing_page_visits WHERE DATE(first_seen_at) = CURDATE()'
        );

        $eventsByType = $db->select(
            'SELECT event_name, COUNT(*) AS total
             FROM tracking_events
             GROUP BY event_name
             ORDER BY total DESC'
        );

        $eventsOverTime = $db->select(
            'SELECT DATE(occurred_at) AS day, COUNT(*) AS total
             FROM tracking_events
             WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(occurred_at)
             ORDER BY day ASC'
        );

        $visitsOverTime = $db->select(
            'SELECT DATE(first_seen_at) AS day, COUNT(*) AS total
             FROM landing_page_visits
             WHERE first_seen_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(first_seen_at)
             ORDER BY day ASC'
        );

        $utmSources = $db->select(
            'SELECT COALESCE(utm_source, "(direct)") AS source, COUNT(*) AS total
             FROM landing_page_visits
             GROUP BY utm_source
             ORDER BY total DESC
             LIMIT 10'
        );

        $recentEvents = $db->select(
            'SELECT te.id, te.event_name, te.event_source, te.occurred_at,
                    lpv.ip_address, lpv.utm_source, lpv.utm_campaign
             FROM tracking_events te
             JOIN landing_page_visits lpv ON lpv.id = te.visit_id
             ORDER BY te.occurred_at DESC
             LIMIT 10'
        );

        return $this->render('analytics.dashboard', [
            'user'           => $user,
            'totalEvents'    => $totalEvents,
            'totalVisits'    => $totalVisits,
            'todayEvents'    => $todayEvents,
            'todayVisits'    => $todayVisits,
            'eventsByType'   => $eventsByType,
            'eventsOverTime' => $eventsOverTime,
            'visitsOverTime' => $visitsOverTime,
            'utmSources'     => $utmSources,
            'recentEvents'   => $recentEvents,
        ])->noCache();
    }

    // ─────────────────────────────────────────────────────────────
    // Events list
    // ─────────────────────────────────────────────────────────────

    public function events(Request $request): Response
    {
        if ($denial = $this->requireAdmin()) {
            return $denial;
        }

        $db   = $this->db();
        $user = $this->currentUser();

        $filterEvent = $request->string('event');
        $filterDate  = $request->string('date');
        $page        = max(1, $request->int('page') ?: 1);
        $perPage     = 10;
        $offset      = ($page - 1) * $perPage;

        $conditions = [];
        $bindings   = [];

        if ($filterEvent !== '') {
            $conditions[] = 'te.event_name = ?';
            $bindings[]   = $filterEvent;
        }

        if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $conditions[] = 'DATE(te.occurred_at) = ?';
            $bindings[]   = $filterDate;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $totalCount = (int) $db->selectValue(
            "SELECT COUNT(*) FROM tracking_events te $where",
            $bindings
        );

        $bindings[] = $perPage;
        $bindings[] = $offset;

        $events = $db->select(
            "SELECT te.id, te.event_name, te.event_id, te.event_source,
                    te.event_data, te.occurred_at,
                    lpv.ip_address, lpv.session_id, lpv.utm_source, lpv.utm_campaign
             FROM tracking_events te
             JOIN landing_page_visits lpv ON lpv.id = te.visit_id
             $where
             ORDER BY te.occurred_at DESC
             LIMIT ? OFFSET ?",
            $bindings
        );

        $eventNames = $db->select(
            'SELECT DISTINCT event_name FROM tracking_events ORDER BY event_name'
        );

        $totalPages = max(1, (int) ceil($totalCount / $perPage));

        return $this->render('analytics.events', [
            'user'        => $user,
            'events'      => $events,
            'eventNames'  => $eventNames,
            'filterEvent' => $filterEvent,
            'filterDate'  => $filterDate,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'totalCount'  => $totalCount,
        ])->noCache();
    }

    // ─────────────────────────────────────────────────────────────
    // Visitors / Sessions list
    // ─────────────────────────────────────────────────────────────

    public function visitors(Request $request): Response
    {
        if ($denial = $this->requireAdmin()) {
            return $denial;
        }

        $db   = $this->db();
        $user = $this->currentUser();

        $page    = max(1, $request->int('page') ?: 1);
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $totalCount = (int) $db->selectValue('SELECT COUNT(*) FROM landing_page_visits');

        // Join inquiries once to get the visitor's identity (name, email, phone).
        // If a visitor submitted the form, those fields will be populated.
        // We use MIN() so GROUP BY works correctly even if multiple inquiries exist per visit.
        $visitors = $db->select(
            'SELECT lpv.id,
                    lpv.fbc,
                    lpv.fbp,
                    lpv.email          AS lpv_email,
                    lpv.contact        AS lpv_contact,
                    lpv.first_seen_at,
                    lpv.last_seen_at,
                    MIN(inq.name)      AS inq_name,
                    MIN(inq.email)     AS inq_email,
                    MIN(inq.phone)     AS inq_phone,
                    COUNT(te.id)       AS event_count
             FROM landing_page_visits lpv
             LEFT JOIN inquiries     inq ON inq.visit_id = lpv.id
             LEFT JOIN tracking_events te ON te.visit_id = lpv.id
             GROUP BY lpv.id
             ORDER BY COALESCE(lpv.last_seen_at, lpv.first_seen_at) DESC, lpv.id DESC
             LIMIT ? OFFSET ?',
            [$perPage, $offset]
        );

        $totalPages = max(1, (int) ceil($totalCount / $perPage));

        return $this->render('analytics.visitors', [
            'user'       => $user,
            'visitors'   => $visitors,
            'page'       => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ])->noCache();
    }

    // ─────────────────────────────────────────────────────────────
    // Visitor events modal (AJAX)
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns a JSON list of events for a given visit ID.
     * Used by the "View Events" modal on the visitors page.
     */
    public function visitorEvents(Request $request, string $visitId): Response
    {
        if ($denial = $this->requireAdmin()) {
            return $denial;
        }

        $db = $this->db();

        // Route params arrive as strings; cast to int for the query
        $id = (int) $visitId;

        if ($id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid visit ID'], 400);
        }

        // Confirm visit exists
        $exists = $db->selectValue(
            'SELECT id FROM landing_page_visits WHERE id = ?',
            [$id]
        );

        if (!$exists) {
            return Response::json(['ok' => false, 'error' => 'Visit not found'], 404);
        }

        // Fetch all events for this visit, ordered by oldest first
        $events = $db->select(
            'SELECT id, event_name, event_data, occurred_at
             FROM tracking_events
             WHERE visit_id = ?
             ORDER BY occurred_at ASC',
            [$id]
        );

        // Decode JSON event_data so the front-end doesn't have to
        foreach ($events as &$event) {
            $decoded = json_decode((string) ($event['event_data'] ?? '{}'), true);
            $event['event_data'] = is_array($decoded) ? $decoded : [];
        }
        unset($event);

        return Response::json(['ok' => true, 'events' => $events]);
    }
}
