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

        $totalEvents = (int) $db->selectValue(
            'SELECT COUNT(*) FROM tracking_events'
        );
        $totalVisits = (int) $db->selectValue(
            'SELECT COUNT(*) FROM landing_page_visits'
        );
        $todayEvents = (int) $db->selectValue(
            'SELECT COUNT(*) FROM tracking_events WHERE DATE(occurred_at) = CURDATE()'
        );
        $todayVisits = (int) $db->selectValue(
            'SELECT COUNT(*) FROM landing_page_visits WHERE DATE(first_seen_at) = CURDATE()'
        );

        $eventsByType = $db->select(
            'SELECT te.event_name, COUNT(*) AS total
             FROM tracking_events te
             GROUP BY te.event_name
             ORDER BY total DESC'
        );

        $eventsOverTime = $db->select(
            'SELECT DATE(te.occurred_at) AS day, COUNT(*) AS total
             FROM tracking_events te
             WHERE te.occurred_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(te.occurred_at)
             ORDER BY day ASC'
        );

        $visitsOverTime = $db->select(
            'SELECT DATE(first_seen_at) AS day, COUNT(*) AS total
             FROM landing_page_visits
             WHERE first_seen_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(first_seen_at)
             ORDER BY day ASC'
        );

        // Aggregate and properly classify traffic sources from visits
        $rawVisits = $db->select(
            'SELECT fbc, utm_source, referrer FROM landing_page_visits'
        );
        $sourceCounts = [];
        foreach ($rawVisits as $rv) {
            $src = self::classifyTrafficSource($rv['fbc'] ?? null, $rv['utm_source'] ?? null, $rv['referrer'] ?? null);
            $sourceCounts[$src] = ($sourceCounts[$src] ?? 0) + 1;
        }
        arsort($sourceCounts);
        $utmSources = [];
        foreach ($sourceCounts as $source => $total) {
            $utmSources[] = ['source' => $source, 'total' => $total];
        }

        // Recent 10 visitor sessions matching the visitors page
        $recentVisitors = $db->select(
            "SELECT lpv.id,
                    lpv.fbc,
                    lpv.fbp,
                    lpv.email          AS lpv_email,
                    lpv.contact        AS lpv_contact,
                    lpv.first_seen_at,
                    lpv.last_seen_at,
                    lpv.visit_count,
                    MIN(inq.name)      AS inq_name,
                    MIN(inq.email)     AS inq_email,
                    MIN(inq.phone)     AS inq_phone,
                    COUNT(te.id)       AS event_count
             FROM landing_page_visits lpv
             LEFT JOIN inquiries     inq ON inq.visit_id = lpv.id
             LEFT JOIN tracking_events te ON te.visit_id = lpv.id
             GROUP BY lpv.id
             ORDER BY COALESCE(lpv.last_seen_at, lpv.first_seen_at) DESC, lpv.id DESC
             LIMIT 10"
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
            'recentVisitors' => $recentVisitors,
            'visitors'       => $recentVisitors,
        ])->noCache();
    }

    /**
     * Classify traffic source based on Facebook click ID, UTM tags, and referrer.
     */
    public static function classifyTrafficSource(?string $fbc, ?string $utmSource, ?string $referrer = null): string
    {
        $fbc = trim((string) $fbc);
        $utm = trim((string) $utmSource);
        $ref = trim((string) $referrer);
        $utmLower = strtolower($utm);
        $refLower = strtolower($ref);

        if (
            $fbc !== ''
            || str_contains($utmLower, 'site_source_name')
            || in_array($utmLower, ['fb', 'facebook', 'meta', 'ig', 'instagram', 'an'], true)
            || str_contains($refLower, 'facebook.com')
            || str_contains($refLower, 'fb.me')
            || str_contains($refLower, 'instagram.com')
        ) {
            return 'Facebook / Meta';
        }

        if ($utm !== '') {
            if (in_array($utmLower, ['google', 'google-ads', 'cpc', 'adwords'], true)) {
                return 'Google Ads';
            }
            return ucfirst($utm);
        }

        if ($ref !== '') {
            if (str_contains($refLower, 'google.')) {
                return 'Google Search';
            }
            if (str_contains($refLower, 'bing.')) {
                return 'Bing Search';
            }
            if (str_contains($refLower, 'yahoo.')) {
                return 'Yahoo Search';
            }
            $host = parse_url($ref, PHP_URL_HOST);
            if ($host && !str_contains($host, '3ammediatech')) {
                return preg_replace('/^www\./', '', $host);
            }
        }

        return 'Direct Visit';
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

        $conditions = ['1=1'];
        $bindings   = [];

        if ($filterEvent !== '') {
            $conditions[] = 'te.event_name = ?';
            $bindings[]   = $filterEvent;
        }

        if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $conditions[] = 'DATE(te.occurred_at) = ?';
            $bindings[]   = $filterDate;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $totalCount = (int) $db->selectValue(
            "SELECT COUNT(*) FROM tracking_events te
             JOIN landing_page_visits lpv ON lpv.id = te.visit_id
             $where",
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
            'SELECT DISTINCT te.event_name FROM tracking_events te
             ORDER BY te.event_name'
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

        // Show all recorded visitors; the view distinguishes FB vs direct via fbc field
        $whereFb = '';

        $totalCount = (int) $db->selectValue("SELECT COUNT(*) FROM landing_page_visits lpv");

        // Join inquiries once to get the visitor's identity (name, email, phone).
        // If a visitor submitted the form, those fields will be populated.
        // Also calculate the total number of visit sessions for each visitor.
        $visitors = $db->select(
            "SELECT lpv.id,
                    lpv.fbc,
                    lpv.fbp,
                    lpv.email          AS lpv_email,
                    lpv.contact        AS lpv_contact,
                    lpv.first_seen_at,
                    lpv.last_seen_at,
                    lpv.visit_count,
                    MIN(inq.name)      AS inq_name,
                    MIN(inq.email)     AS inq_email,
                    MIN(inq.phone)     AS inq_phone,
                    COUNT(te.id)       AS event_count
             FROM landing_page_visits lpv
             LEFT JOIN inquiries     inq ON inq.visit_id = lpv.id
             LEFT JOIN tracking_events te ON te.visit_id = lpv.id
             {$whereFb}
             GROUP BY lpv.id
             ORDER BY COALESCE(lpv.last_seen_at, lpv.first_seen_at) DESC, lpv.id DESC
             LIMIT ? OFFSET ?",
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
     * Returns a paginated JSON list of events for a given visit ID.
     * Records are limited to 5 per page at the database query level.
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

        // Pagination: limit by 5 strictly from query execution at the back-end
        $perPage = 5;
        $page    = max(1, $request->int('page', 1));

        $totalCount = (int) $db->selectValue(
            'SELECT COUNT(*) FROM tracking_events WHERE visit_id = ?',
            [$id]
        );

        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Fetch limited events for this visit, ordered chronologically
        $events = $db->select(
            'SELECT id, event_name, event_data, occurred_at
             FROM tracking_events
             WHERE visit_id = ?
             ORDER BY occurred_at ASC, id ASC
             LIMIT ? OFFSET ?',
            [$id, $perPage, $offset]
        );

        // Decode JSON event_data so the front-end doesn't have to
        foreach ($events as &$event) {
            $decoded = json_decode((string) ($event['event_data'] ?? '{}'), true);
            $event['event_data'] = is_array($decoded) ? $decoded : [];
        }
        unset($event);

        return Response::json([
            'ok'          => true,
            'events'      => $events,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $totalCount,
            'total_pages' => $totalPages,
            'has_next'    => $page < $totalPages,
            'has_prev'    => $page > 1,
        ]);
    }
}
