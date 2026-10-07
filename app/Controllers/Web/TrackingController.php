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

        $visitId = (int) ($request->input('visit_id') ?: ($tracking->currentVisitId() ?? 0));
        $eventName = trim((string) $request->input('event_name', ''));
        $eventId = trim((string) $request->input('event_id', ''));
        $eventSource = trim((string) $request->input('event_source', 'browser'));
        $eventData = $request->array('event_data');
        if ($eventData === []) {
            $raw = $request->input('event_data');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $eventData = $decoded;
                }
            }
        }

        if ($visitId <= 0) {
            if ($tracking->isFromFacebook($request)) {
                $visitId = $tracking->recordVisit($request) ?? 0;
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
        ]);
    }
}
