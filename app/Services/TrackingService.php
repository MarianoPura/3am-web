<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use Throwable;

/**
 * Tracking Service
 *
 * Manages visitor attribution and event tracking for Meta Pixel and ad analytics.
 * Persists visit sessions to `landing_page_visits` and granular actions
 * to `tracking_events`.
 */
final class TrackingService
{
    public function __construct(private readonly ?Database $db = null)
    {
    }

    /**
     * Record or resume a visit session.
     *
     * @param Request $request Current HTTP request
     * @param array<string, mixed> $extra Additional metadata (e.g. referrer, landing path)
     * @return int|null Visit ID from landing_page_visits
     */
    public function recordVisit(Request $request, array $extra = []): ?int
    {
        if ($this->db === null) {
            return null;
        }

        $sessionId = session_id() ?: null;
        $ip = mb_substr((string) $request->ip(), 0, 45);
        $userAgent = (string) $request->userAgent();
        $landingPage = mb_substr((string) ($extra['landing_page'] ?? $request->path()), 0, 500);
        $referrer = mb_substr((string) ($extra['referrer'] ?? $request->header('referer') ?? ''), 0, 1000);

        // UTM parameters
        $utmSource   = $this->cleanStr($request->input('utm_source')   ?? $extra['utm_source']   ?? null, 255);
        $utmMedium   = $this->cleanStr($request->input('utm_medium')   ?? $extra['utm_medium']   ?? null, 255);
        $utmCampaign = $this->cleanStr($request->input('utm_campaign') ?? $extra['utm_campaign'] ?? null, 255);
        $utmContent  = $this->cleanStr($request->input('utm_content')  ?? $extra['utm_content']  ?? null, 255);
        $utmTerm     = $this->cleanStr($request->input('utm_term')     ?? $extra['utm_term']     ?? null, 255);
        $fbclid      = $this->cleanStr($request->input('fbclid')       ?? $extra['fbclid']       ?? null, 255);

        // Facebook cookies (_fbp, _fbc)
        $fbp = $this->cleanStr($_COOKIE['_fbp'] ?? $extra['fbp'] ?? null, 255);
        $fbc = $this->cleanStr($_COOKIE['_fbc'] ?? $extra['fbc'] ?? null, 255);

        // Synthesize fbc if fbclid is present and _fbc cookie isn't yet set
        if ($fbc === null && $fbclid !== null) {
            $fbc = 'fb.1.' . time() . '.' . $fbclid;
        }

        $now = date('Y-m-d H:i:s');
        $existingVisitId = isset($_SESSION['_visit_id']) ? (int) $_SESSION['_visit_id'] : null;

        try {
            if ($existingVisitId !== null && $existingVisitId > 0) {
                // Update existing visit
                $this->db->update(
                    'UPDATE landing_page_visits
                     SET last_seen_at = ?,
                         fbp = COALESCE(?, fbp),
                         fbc = COALESCE(?, fbc)
                     WHERE id = ?',
                    [$now, $fbp, $fbc, $existingVisitId]
                );

                return $existingVisitId;
            }

            // Insert new visit
            $visitId = $this->db->insert(
                'INSERT INTO landing_page_visits (
                    session_id, landing_page, ip_address, user_agent, referrer,
                    utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                    fbp, fbc, first_seen_at, last_seen_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $sessionId,
                    $landingPage !== '' ? $landingPage : '/',
                    $ip !== '' ? $ip : null,
                    $userAgent !== '' ? $userAgent : null,
                    $referrer !== '' ? $referrer : null,
                    $utmSource,
                    $utmMedium,
                    $utmCampaign,
                    $utmContent,
                    $utmTerm,
                    $fbp,
                    $fbc,
                    $now,
                    $now,
                ]
            );

            if ($visitId > 0) {
                $_SESSION['_visit_id'] = $visitId;
                return $visitId;
            }
        } catch (Throwable $e) {
            error_log('TrackingService::recordVisit failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Record a discrete tracking event (e.g. PageView, ViewContent, Lead, Contact).
     *
     * @param int $visitId Associated visit ID
     * @param string $eventName Event name
     * @param string|null $eventId Deduplication event ID (shared with browser pixel)
     * @param string $eventSource Source: 'browser' or 'server'
     * @param array<string, mixed>|null $eventData Additional payload
     * @return int|null Event record ID
     */
    public function recordEvent(
        int $visitId,
        string $eventName,
        ?string $eventId = null,
        string $eventSource = 'server',
        ?array $eventData = null
    ): ?int {
        if ($this->db === null || $visitId <= 0) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $eventId = $eventId !== null && $eventId !== ''
            ? mb_substr($eventId, 0, 255)
            : 'evt_' . bin2hex(random_bytes(10));
        $eventDataJson = $eventData !== null ? json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        try {
            return $this->db->insert(
                'INSERT INTO tracking_events (
                    visit_id, event_name, event_id, event_source, event_data, occurred_at
                ) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $visitId,
                    mb_substr($eventName, 0, 100),
                    $eventId,
                    mb_substr($eventSource, 0, 50),
                    $eventDataJson,
                    $now,
                ]
            );
        } catch (Throwable $e) {
            error_log("TrackingService::recordEvent failed for [{$eventName}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve the active visit ID from session.
     */
    public function currentVisitId(): ?int
    {
        return isset($_SESSION['_visit_id']) ? (int) $_SESSION['_visit_id'] : null;
    }

    private function cleanStr(mixed $val, int $maxLength): ?string
    {
        if ($val === null) {
            return null;
        }
        $str = trim((string) $val);
        return $str === '' ? null : mb_substr($str, 0, $maxLength);
    }
}
