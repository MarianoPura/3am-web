<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Homepage.
 *
 * Currently renders the Phase 1 shell — the hero and the three channels —
 * against config rather than the database, because the schema and models are
 * the next piece of work. The section structure matches the plan's homepage
 * blueprint so the sections fill in rather than get rebuilt.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('pages.home', [
            'channels'     => config('app.channels'),
            'capabilities' => config('app.capabilities'),
            'proof'        => config('app.proof'),
            'sectors'      => config('app.sectors'),
            'company'      => config('app.company'),

            // JustBump and Remember.Me. These render inside the Systems
            // section, not under Ventures — they are technology products, and
            // a technology arm shipping its own software is proof that belongs
            // in the Technology story rather than filed away separately.
            'techProducts' => config('app.tech_products'),

            // Studio Setup / Stage Setup / Event Management / production support.
            'ventures'        => config('app.ventures'),
            'sectionVentures' => config('app.ventures_section'),

            'sectionMedia' => config('app.section_media'),
            'sectionTech'  => config('app.section_technology'),

            // Media work gallery: six cards. Real projects fill from the front,
            // and any remaining slots render designed placeholders — so a
            // half-populated gallery still looks deliberate rather than broken.
            'mediaWork'    => array_pad(
                array_slice((array) config('projects.projects', []), 0, 6),
                6,
                []
            ),

            // TODO(phase-3): featured projects from the database —
            //   SELECT p.*, m.path AS thumbnail, m.alt_text AS thumbnail_alt,
            //          c.name AS client, cat.name AS category
            //     FROM projects p
            //     LEFT JOIN media m   ON m.id  = p.thumbnail_media_id
            //     LEFT JOIN clients c ON c.id  = p.client_id
            //     LEFT JOIN categories cat ON cat.id = p.primary_category_id
            //    WHERE p.is_featured = 1 AND p.status = 'published'
            //          AND p.deleted_at IS NULL
            //    ORDER BY p.display_order LIMIT 4
            //
            // Joined in one query rather than looked up per project — this is
            // the most likely place on the site for an N+1 to appear.
            //
            // Until then the template renders its designed empty state.
            'featured' => [],
        ])->cacheFor(300);
    }

    /**
     * Health check.
     *
     * Confirms PHP is executing and, when configured, that the database
     * answers. Used by the deploy script to verify a release before the
     * symlink swap, and by CloudWatch afterwards.
     */
    public function health(Request $request): Response
    {
        $checks = [
            'php'     => PHP_VERSION,
            'time'    => date('c'),
            'env'     => config('app.env'),
            'storage' => is_writable(BASE_PATH . '/storage') ? 'ok' : 'not writable',
        ];

        try {
            $this->db()->selectValue('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'unavailable';
        }

        $healthy = $checks['storage'] === 'ok';

        return Response::json(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? 200 : 503
        )->noCache();
    }
}
