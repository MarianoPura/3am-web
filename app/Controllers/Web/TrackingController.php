<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\TrackingService;

/**
 * Handles client-side tracking event beacons.
 */
final class TrackingController extends Controller
{
    public function track(Request $request): Response
    {
        /** @var TrackingService $tracking */
        $tracking = $this->container->get(TrackingService::class);

        // 1. Prefer the visit_id sent explicitly by the browser JS
        $visitId = (int) ($request->input('visit_id') ?? 0);

        // 2. If missing, fall back to the active session (same browser, same FB visit)
        if ($visitId <= 0) {
            $visitId = $tracking->currentVisitId() ?? 0;
        }

        // 3. Still nothing? Create a new visit so events are always attributed to a session
        if ($visitId <= 0) {
            $visitId = $tracking->recordVisit($request) ?? 0;
        }

        $eventName   = trim((string) $request->input('event_name', ''));
        $eventId     = trim((string) $request->input('event_id', ''));
        $eventSource = trim((string) $request->input('event_source', 'browser'));
        $eventData   = $request->array('event_data');
        if ($eventData === []) {
            $raw = $request->input('event_data');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $eventData = $decoded;
                }
            }
        }

        if ($visitId <= 0 || $eventName === '') {
            return Response::json(['ok' => false, 'error' => 'Missing visit_id or event_name'], 400);
        }

        $id = $tracking->recordEvent(
            visitId: $visitId,
            eventName: $eventName,
            eventId: $eventId !== '' ? $eventId : null,
            eventSource: $eventSource !== '' ? $eventSource : 'browser',
            eventData: $eventData,
        );

        return Response::json([
            'ok'       => true,
            'event_id' => $eventId,
            'id'       => $id,
            'visit_id' => $visitId,
        ]);
    }
}
