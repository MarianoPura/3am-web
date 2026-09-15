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
 * Two forms sharing one template and one handler — "media" and "technology"
 * differ only in their project-type list (config/forms.php). The split exists
 * because a client booking a corporate video and a client specifying AV for a
 * 500-person hybrid conference are different buyers; asking which one they are
 * up front qualifies the enquiry for free.
 *
 * CSRF is verified by the middleware pipeline before this class is reached, so
 * there is no token check here — a POST route cannot be unprotected by
 * omission. See index.php.
 */
final class InquiryController extends Controller
{
    public function show(Request $request, string $type): Response
    {
        $form = $this->form($type);

        if ($form === null) {
            return $this->render('pages.error', ['status' => 404, 'message' => ''], 404);
        }

        // Pull back anything the visitor typed before a validation failure, then
        // clear it so a later refresh starts clean.
        $old    = $_SESSION['_old']    ?? [];
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);

        return $this->render('pages.inquiry', [
            'form'    => $form,
            'old'     => $old,
            'errors'  => $errors,
            'company' => config('app.company'),
        ])->noCache();
    }

    public function submit(Request $request, string $type): Response
    {
        $form = $this->form($type);

        if ($form === null) {
            return $this->render('pages.error', ['status' => 404, 'message' => ''], 404);
        }

        $path = '/start/' . $form['slug'];

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
        $store = new InquiryStore(
            storagePath: BASE_PATH . '/storage/inquiries',
            notifyTo:    (string) config('app.contact_email'),
            siteName:    (string) config('app.name'),
        );

        try {
            $reference = $store->capture([
                'form'       => $form['slug'],
                'type'       => $projectType,
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'company'    => $company,
                'details'    => $details,
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

        return isset($forms[$type]) && is_array($forms[$type]) ? $forms[$type] : null;
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
