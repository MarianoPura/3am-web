<?php
declare(strict_types=1);

// Development server preview uses the configured environment, not a fixed host.
$localPreview = PHP_SAPI === 'cli-server'
    && in_array((string) config('app.env'), ['local', 'testing'], true);
return [
    'preview_samples' => $localPreview || (
        in_array((string) config('app.env'), ['local', 'testing'], true)
        && filter_var(env('RENTALS_PREVIEW_SAMPLES', false), FILTER_VALIDATE_BOOL)
    ),
];
