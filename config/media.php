<?php

declare(strict_types=1);

/**
 * Media storage configuration.
 *
 * Images and video are served from cdn.3ammediatech.com (CloudFront → private
 * S3), never from the apex. That is a deliberate decision, not a convenience:
 * putting CloudFront in front of the apex would place the legacy applications
 * behind a cache they were never tested against — header handling, cookies and
 * POST behaviour all become variables. The CDN subdomain gets the benefit
 * exactly where the bytes are, with zero exposure for /armonyx and /trebl.
 */

return [
    'disk' => env('MEDIA_DISK', 's3'),   // s3 | local (local for development)

    'cdn_url' => rtrim((string) env('MEDIA_CDN_URL', ''), '/'),

    's3' => [
        'region' => env('AWS_REGION', 'ap-southeast-1'),
        'bucket' => env('AWS_BUCKET', ''),
        'prefix' => trim((string) env('AWS_BUCKET_PREFIX', 'uploads'), '/'),

        // Empty in production — the EC2 instance role supplies credentials.
        // Static keys sitting in a file are precisely what that avoids.
        'key'    => env('AWS_ACCESS_KEY_ID', '') ?: null,
        'secret' => env('AWS_SECRET_ACCESS_KEY', '') ?: null,
    ],

    'local' => [
        'path' => BASE_PATH . '/uploads',
        'url'  => '/uploads',
    ],

    /*
     * Derivatives are generated at upload time, not on request.
     *
     * On-the-fly resizing would put image processing on the critical path of a
     * small shared instance — one visitor hitting an uncached gallery could
     * stall requests for the legacy apps on the same box.
     */
    'image' => [
        'driver' => env('MEDIA_DRIVER', 'imagick'),   // verify in Phase 0 audit §5
        'widths' => [400, 800, 1200, 1600, 2400],
        'formats' => ['webp', 'avif'],
        'quality' => ['webp' => 82, 'avif' => 60, 'jpeg' => 85],

        // Re-encoding through Imagick strips EXIF and any embedded payload,
        // which is the real reason it is preferred over GD.
        'strip_metadata' => true,
        'max_bytes' => (int) env('MEDIA_MAX_IMAGE_MB', 10) * 1024 * 1024,

        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif',
        ],
    ],

    /*
     * Video.
     *
     * Case-study films are NOT self-hosted. Vimeo handles adaptive bitrate,
     * global delivery and transcoding with no egress bill and no infrastructure
     * to run — and unlike YouTube, no competitor recommendations at the end of
     * a client's case study.
     *
     * Self-hosting here is only for short, silent, muted hero loops.
     */
    'video' => [
        'self_host_max_seconds' => 6,
        'self_host_max_bytes'   => (int) env('MEDIA_MAX_VIDEO_MB', 50) * 1024 * 1024,
        'allowed_mimes'         => ['video/mp4', 'video/webm'],

        // Embeds are facade-loaded: a poster image and a play button, with the
        // iframe injected only on click. A third-party player costs 500KB-1MB
        // before the visitor has decided to watch anything.
        'facade_load' => true,
        'providers'   => ['vimeo', 'youtube'],
    ],

    /*
     * SVG is rejected on upload except for client logos, which go through an
     * allowlist parser first. An SVG is a document that can carry script, so
     * treating it as an image is a mistake with a long history.
     */
    'allow_svg_for' => ['client_logo'],
];
