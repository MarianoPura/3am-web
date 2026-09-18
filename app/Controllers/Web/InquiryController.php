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

        if ($type !== 'project') {
            $preset = ['media' => 'media', 'technology' => 'technology', 'ventures' => 'other'][$type];
            if (isset($_SESSION['_old']['type']) && is_string($_SESSION['_old']['type'])) {
                $legacyType = $_SESSION['_old']['type'];
                if (in_array($legacyType, $form['types'], true)) {
                    $_SESSION['_old']['type'] = match ($type) {
                        'media' => 'Media / Production',
                        'technology' => 'Technology / Event Systems',
                        default => str_contains($legacyType, 'Rental') ? 'Rentals' : 'Other / General Inquiry',
                    };
                    $_SESSION['_old']['details'] = $legacyType . "\n" . (is_string($_SESSION['_old']['details'] ?? null) ? $_SESSION['_old']['details'] : '');
                }
            }
            $rental = $request->string('rental');
            return $this->redirect('/start?type=' . ($rental !== '' ? 'rentals' : $preset) . ($rental !== '' ? '&rental=' . rawurlencode($rental) : ''));
        }

        // Pull back anything the visitor typed before a validation failure, then
        // clear it so a later refresh starts clean.
        $old    = $_SESSION['_old']    ?? [];
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        $old = array_filter($old, 'is_scalar');

        $presets = ['media' => 'Media / Production', 'technology' => 'Technology / Event Systems', 'rentals' => 'Rentals', 'other' => 'Other / General Inquiry'];
        if ($old === []) { $old['type'] = $presets[$request->string('type')] ?? ''; }
        $rentalSlug = (string) ($old['rental'] ?? $request->string('rental'));
        $rental = (new \App\Models\RentalCatalog($this->db()))->find($rentalSlug);
        if ($rental !== null) {
            $old['rental'] = $rental['id'];
            if ($errors === []) { $old['type'] = 'Rentals'; }
        } elseif ($rentalSlug !== '') {
            $errors['rental'] = 'This rental is no longer listed. Please choose another rental or continue with a general inquiry.';
            unset($old['rental']);
        }

        return $this->render('pages.inquiry', [
            'form'    => $form,
            'old'     => $old,
            'errors'  => $errors,
            'company' => config('app.company'),
            'rental' => $rental,
        ])->noCache();
    }

    public function submit(Request $request, string $type = 'project'): Response
    {
        $form = $this->form($type);

        if ($form === null) {
            return $this->render('pages.error', ['status' => 404, 'message' => ''], 404);
        }

        $path = $type === 'project' ? '/start' : '/start/' . $form['slug'];

        // ── Anti-spam, before anything expensive ──────────────────────────
        if (trim((string) $request->input(config('forms.honeypot_field'), '')) !== '') {
            // A bot filled the hidden field. Show the success page rather than
            // an error: telling a spammer their submission was rejected just
            // tells them what to change.
            return $this->redirect('/start/received');
        }

        $elapsed = time() - (int) $request->input('_t', 0);
        if ($elapsed < (int) config('forms.min_seconds', 3)) {
            return $this->back($path, ['form' => 'That was too quick — please try again.'], $request->all());
        }

        if (!$this->withinRateLimit($request->ip())) {
            return $this->render('pages.error', [
                'status'  => 429,
                'message' => 'Too many enquiries from this connection. Please try again later, or email us directly.',
            ], 429);
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
        $rentalSlug  = $request->string('rental');
        $rental = $projectType === 'Rentals' ? (new \App\Models\RentalCatalog($this->db()))->find($rentalSlug) : null;
        if ($projectType === 'Rentals' && $rentalSlug !== '' && $rental === null) {
            $errors['rental'] = 'This rental is no longer listed. Please choose another rental or continue with a general inquiry.';
        }

        // Compare against the allowlist, not just "is it non-empty" — the
        // select is a client-side control and a POST can carry anything.
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

        if (mb_strlen($company) > $limits['company']) { $errors['company'] = 'That company name is too long.'; }

        if ($details === '') {
            $errors['details'] = 'Please tell us about the project.';
        } elseif (mb_strlen($details) < 20) {
            $errors['details'] = 'A little more detail would help — requirements, location and dates.';
        } elseif (mb_strlen($details) > $limits['details']) {
            $errors['details'] = 'That is longer than we can accept here. Please email us directly.';
        }

        if ($errors !== []) {
            return $this->back($path, $errors, $request->all());
        }

        // ── Capture ───────────────────────────────────────────────────────
        $store = $this->container->get(InquiryStore::class);

        try {
            $reference = $store->capture([
                'form'       => $form['slug'],
                'type'       => $projectType,
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'company'    => $company,
                'details'    => ($rental !== null ? 'Selected rental: ' . $rental['name'] . ' [' . $rental['id'] . "]\n\n" : '') . $details,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // The record could not be written, so we must NOT show a success
            // page — the visitor would believe they had made contact when
            // nothing exists to follow up.
            error_log('Inquiry capture failed: ' . $e->getMessage());

            return $this->back($path, [
                'form' => 'Something went wrong on our side. Please email us directly at '
                        . config('app.contact_email') . '.',
            ], $request->all());
        }

        $this->recordAttempt($request->ip());

        $_SESSION['_inquiry_reference'] = $reference;

        // Redirect after POST so a refresh cannot resubmit, and so the
        // confirmation has its own URL for conversion tracking.
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

        return in_array($type, ['project', 'media', 'technology', 'ventures'], true) ? ($forms[$type] ?? null) : null;
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
