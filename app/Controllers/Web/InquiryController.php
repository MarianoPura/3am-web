<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\InquiryStore;

/**
 * Project inquiry forms.
 *
 * One inquiry page, with legacy URLs preserved for compatibility.
 *
 * CSRF is verified by the middleware pipeline before this class is reached, so
 * there is no token check here — a POST route cannot be unprotected by
 * omission. See index.php.
 */
final class InquiryController extends Controller
{
    public function show(Request $request, string $type = 'project'): Response
    {
        $form = $this->form($type);

        if ($form === null) {
            return $this->render('pages.error', ['status' => 404, 'message' => ''], 404);
        }

        if ($type === 'quote') {
            return $this->redirect((string) $form['path']);
        }

        if ($type !== 'project') {
            $preset = ['media' => 'media', 'technology' => 'technology', 'ventures' => 'other'][$type];
            if (isset($_SESSION['_old']['type']) && is_string($_SESSION['_old']['type'])) {
                $legacyType = $_SESSION['_old']['type'];
                if (in_array($legacyType, $form['types'], true)) {
                    $_SESSION['_old']['type'] = match ($type) {
                        'media' => 'Media / Production',
                        'technology' => 'Technology / Event Systems',
                        default => 'Other / General Inquiry',
                    };
                    $_SESSION['_old']['details'] = $legacyType . "\n" . (is_string($_SESSION['_old']['details'] ?? null) ? $_SESSION['_old']['details'] : '');
                }
            }
            return $this->redirect('/start?type=' . $preset);
        }

        // Pull back anything the visitor typed before a validation failure, then
        // clear it so a later refresh starts clean.
        $old    = $_SESSION['_old']    ?? [];
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        $old = array_filter($old, 'is_scalar');

        $presets = ['media' => 'Media / Production', 'technology' => 'Technology / Event Systems', 'other' => 'Other / General Inquiry'];
        if ($old === []) { $old['type'] = $presets[$request->string('type')] ?? ''; }
        return $this->render('pages.inquiry', [
            'form'    => $form,
            'old'     => $old,
            'errors'  => $errors,
            'company' => config('app.company'),
        ])->noCache();
    }

    public function submit(Request $request, string $type = 'project'): Response
    {
        $form = $this->form($type);

        if ($form === null) {
            return $this->render('pages.error', ['status' => 404, 'message' => ''], 404);
        }

        $path = $form['path'] ?? ($type === 'project' ? '/start' : '/start/' . $form['slug']);

        // ── Anti-spam, before anything expensive ──────────────────────────
        if (trim((string) $request->input(config('forms.honeypot_field'), '')) !== '') {
            // A bot filled the hidden field. Return a silent success response.
            return $request->isAjax()
                ? Response::json(['ok' => true])
                : $this->redirect('/start/received');
        }

        $elapsed = time() - (int) $request->input('_t', 0);
        if ($elapsed < (int) config('forms.min_seconds', 3)) {
            return $this->invalid($request, $path, ['form' => 'That was too quick — please try again.']);
        }

        if (!$this->withinRateLimit($request->ip())) {
            $message = 'Too many enquiries from this connection. Please try again later, or email us directly.';

            return $request->isAjax()
                ? Response::json(['ok' => false, 'errors' => ['form' => $message]], 429)
                : $this->render('pages.error', ['status' => 429, 'message' => $message], 429);
        }

        // ── Validate ──────────────────────────────────────────────────────
        $limits = config('forms.limits');
        $errors = [];

        $projectType = trim((string) $request->input('type', ''));
        $name        = trim((string) $request->input('name', ''));
        $email       = trim((string) $request->input('email', ''));
        $phone       = trim((string) $request->input('phone', ''));
        $company     = trim((string) $request->input('company', ''));
        $details     = trim((string) $request->input('details', ''));

        // Compare against the allowlist, not just "is it non-empty"
        if (!in_array($projectType, $form['types'], true)) {
            $errors['type'] = 'Please choose a project type.';
        }

        if ($name === '') {
            $errors['name'] = 'Please tell us your name.';
        } elseif (mb_strlen($name) > $limits['name']) {
            $errors['name'] = 'That name is too long.';
        }

        if ($email === '') {
            $errors['email'] = 'We need an email address to reply to.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > $limits['email']) {
            $errors['email'] = 'That does not look like a valid email address.';
        }

        if ($phone === '') {
            $errors['phone'] = 'Please give us a contact number.';
        } elseif (mb_strlen($phone) > $limits['phone']) {
            $errors['phone'] = 'That number is too long.';
        }

        if (mb_strlen($company) > $limits['company']) {
            $errors['company'] = 'That company name is too long.';
        }

        $detailsRequired = ($form['details_required'] ?? true) !== false;

        if ($details === '') {
            if ($detailsRequired) {
                $errors['details'] = 'Please tell us about the project.';
            }
        } elseif ($detailsRequired && mb_strlen($details) < 20) {
            $errors['details'] = 'A little more detail would help — requirements, location and dates.';
        } elseif (mb_strlen($details) > $limits['details']) {
            $errors['details'] = 'That is longer than we can accept here. Please email us directly.';
        }

        if ($errors !== []) {
            return $this->invalid($request, $path, $errors);
        }

        // ── Capture ───────────────────────────────────────────────────────
        $store = $this->container->get(InquiryStore::class);

        try {
            $reference = $store->capture([
                'form'             => $form['slug'],
                'type'             => $projectType,
                'name'             => $name,
                'email'            => $email,
                'phone'            => $phone,
                'company'          => $company,
                'details'          => $details,
                'ip'               => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'attribution'      => $this->attribution($request, $path),
                'visit_id'         => $request->input('visit_id') ?: null,
                'lead_event_id'    => $request->input('lead_event_id') ?: null,
                'contact_event_id' => $request->input('contact_event_id') ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('Inquiry capture failed: ' . $e->getMessage());

            return $this->invalid($request, $path, [
                'form' => 'Something went wrong on our side. Please email us directly at '
                        . config('app.contact_email') . '.',
            ], 500);
        }

        $this->recordAttempt($request->ip());

        if ($request->isAjax()) {
            return Response::json([
                'ok'        => true,
                'success'   => true,
                'reference' => $reference,
            ]);
        }

        $_SESSION['_inquiry_reference'] = $reference;

        return $this->redirect('/start/received');
    }

    public function received(Request $request): Response
    {
        $reference = $_SESSION['_inquiry_reference'] ?? null;
        unset($_SESSION['_inquiry_reference']);

        return $this->render('pages.inquiry-received', [
            'reference' => $reference,
            'company'   => config('app.company'),
        ])->noCache();
    }

    // ─────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function form(string $type): ?array
    {
        $forms = (array) config('forms', []);

        return in_array($type, ['project', 'media', 'technology', 'ventures', 'quote'], true) ? ($forms[$type] ?? null) : null;
    }

    /**
     * Reject a submission: JSON for AJAX, otherwise redirect back with form input.
     *
     * @param array<string, string> $errors
     */
    private function invalid(Request $request, string $path, array $errors, int $status = 422): Response
    {
        if ($request->isAjax()) {
            return Response::json(['ok' => false, 'errors' => $errors], $status);
        }

        return $this->back($path, $errors, $request->all());
    }

    /**
     * Extract campaign attribution parameters (UTMs and fbclid).
     *
     * @return array<string, string>
     */
    private function attribution(Request $request, string $path): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'fbp', 'fbc'];
        $out  = [];

        foreach ($keys as $key) {
            $value = mb_substr(trim($request->string($key)), 0, 255);
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        if (!isset($out['fbp']) && !empty($_COOKIE['_fbp'])) {
            $out['fbp'] = mb_substr(trim((string) $_COOKIE['_fbp']), 0, 255);
        }
        if (!isset($out['fbc']) && !empty($_COOKIE['_fbc'])) {
            $out['fbc'] = mb_substr(trim((string) $_COOKIE['_fbc']), 0, 255);
        }

        if ($out !== [] || $path === config('landing.path')) {
            $out['landing'] = $path;
        }

        return $out;
    }

    /**
     * File-based rate limit.
     *
     * Crude but adequate for a marketing form, and it needs no database. Moves
     * to the login_attempts table alongside the rest of the throttling in
     * Phase 5.
     */
    private function withinRateLimit(string $ip): bool
    {
        $file = $this->rateLimitFile($ip);

        if (!is_file($file)) {
            return true;
        }

        $window   = (int) config('forms.rate_limit_window', 3600);
        $attempts = array_filter(
            (array) json_decode((string) file_get_contents($file), true),
            static fn ($t): bool => is_int($t) && $t > time() - $window
        );

        return count($attempts) < (int) config('forms.rate_limit', 5);
    }

    private function recordAttempt(string $ip): void
    {
        $file   = $this->rateLimitFile($ip);
        $window = (int) config('forms.rate_limit_window', 3600);

        $attempts = is_file($file)
            ? (array) json_decode((string) file_get_contents($file), true)
            : [];

        $attempts   = array_values(array_filter($attempts, static fn ($t): bool => is_int($t) && $t > time() - $window));
        $attempts[] = time();

        @file_put_contents($file, json_encode($attempts), LOCK_EX);
    }

    private function rateLimitFile(string $ip): string
    {
        $dir = BASE_PATH . '/storage/cache/throttle';

        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        // Hash the IP: it is personal data under the Data Privacy Act, and a
        // rate limiter has no need to store it in the clear.
        return $dir . '/' . hash('sha256', $ip) . '.json';
    }
}
