<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token issue and verification.
 *
 * Verification runs as a middleware stage rather than a call inside each
 * handler. That difference is the whole point: a developer adding a new POST
 * route gets protection whether or not they remembered it existed. Per-handler
 * checks fail by omission, and omission is the normal case under deadline.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD       = '_token';
    private const HEADER      = 'X-CSRF-Token';

    /**
     * The current token, generated on first use.
     *
     * One token per session rather than one per form. Per-form tokens are
     * marginally stronger but break the back button and any page open in two
     * tabs, and the practical result is people disabling the check.
     */
    public function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $this->regenerate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Issue a fresh token.
     *
     * Called on login and logout, so a token captured before authentication
     * cannot be replayed against the authenticated session.
     */
    public function regenerate(): string
    {
        return $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Verify the token on an incoming request.
     *
     * Accepts the hidden form field or the X-CSRF-Token header, the latter for
     * fetch() calls from the admin. hash_equals is used rather than === because
     * a timing-variable comparison on a secret is a real, if narrow, leak.
     */
    public function verify(Request $request): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';

        if ($expected === '') {
            // No token was ever issued for this session, so nothing can match.
            // Fail closed.
            return false;
        }

        $provided = (string) ($request->input(self::FIELD) ?? $request->header(self::HEADER) ?? '');

        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function fieldName(): string
    {
        return self::FIELD;
    }
}
