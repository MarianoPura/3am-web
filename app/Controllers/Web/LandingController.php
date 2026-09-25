<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Ad Landing Page Controller (/get-a-quote)
 *
 * Dedicated controller for Facebook/Meta Ad traffic.
 * Handles rendering the landing page and passing submitted inquiries
 * into the main InquiryController processing pipeline.
 */
final class LandingController extends Controller
{
    /**
     * Display the landing page.
     */
    public function show(Request $request): Response
    {
        // Retrieve any old input and validation errors from a non-JS submission
        $old    = array_filter($_SESSION['_old'] ?? [], 'is_scalar');
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);

        $landingConfig = (array) config('landing');
        $formConfig    = (array) config('forms.quote');

        // Record or resume visitor in landing_page_visits
        $tracking = $this->container->get(\App\Services\TrackingService::class);
        $visitId  = $tracking->recordVisit($request, [
            'landing_page' => $request->path(),
        ]);

        return $this->render('pages.landing', [
            'landing'      => $landingConfig,
            'form'         => $formConfig,
            'old'          => $old,
            'errors'       => $errors,
            'company'      => config('app.company'),
            'capabilities' => config('app.capabilities'),
            'proof'        => config('app.proof'),
            'video'        => $this->resolveVideo((string) ($landingConfig['video_url'] ?? '')),
            'visit_id'     => $visitId,
        ])->noCache();
    }

    /**
     * Handle form submission (delegates directly to InquiryController).
     */
    public function submit(Request $request): Response
    {
        return (new InquiryController($this->container))->submit($request, 'quote');
    }

    /**
     * Resolve video URL to either a local MP4 file or an embeddable YouTube/Vimeo player.
     *
     * @return array{kind: string, src: ?string}
     */
    private function resolveVideo(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return ['kind' => 'none', 'src' => null];
        }

        // Local video file (MP4/WebM)
        if (preg_match('/\.(mp4|webm)(\?.*)?$/i', $url) === 1) {
            return ['kind' => 'file', 'src' => site_media($url)];
        }

        // YouTube embed
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0'];
        }

        // Vimeo embed
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1'];
        }

        return ['kind' => 'none', 'src' => null];
    }
}
