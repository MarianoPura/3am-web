<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class RentalAccount
{
    public function __construct(
        private readonly Database $db,
        private readonly RentalCart $cart,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        $user = $this->db->selectOne(
            'SELECT id, name, email, password, role, last_login, created_at FROM users WHERE id = ?',
            [$userId]
        );
        if ($user === null) {
            unset($_SESSION['user_id']);
        }

        if ($user !== null) {
            $stamp = hash('sha256', (string)$user['password']);
            $known = $_SESSION['rentals_password_stamp'] ?? null;
            $invalid = is_string($known) && !hash_equals($known,$stamp);
            if ($known === null && (new RentalSchema($this->db))->hasTable('rental_password_resets')) {
                $resetAt = $this->db->selectValue('SELECT reset_at FROM rental_password_resets WHERE user_id=?',[$userId]);
                $invalid = $resetAt !== null && strtotime($resetAt) >= (float)($_SESSION['rentals_authenticated_at'] ?? 0);
            }
            if ($invalid) { unset($_SESSION['user_id'],$_SESSION['rentals_password_stamp'],$_SESSION['rentals_authenticated_at']); return null; }
            $_SESSION['rentals_password_stamp'] = $stamp;
            unset($user['password']);
        }
        return $user;
    }

    /** @return array<string, mixed> */
    public function register(string $name, string $email, string $password): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Enter your name.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if (strlen($password) < 8) { throw new RuntimeException('Use a password with at least 8 characters.'); }
        if (strlen($password) > 72) { throw new RuntimeException('That password is too long. Use a shorter password.'); }
        if (str_contains($password, "\0")) { throw new RuntimeException('That password contains an unsupported character. Choose a different password.'); }
        if ($this->db->selectOne('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new RuntimeException('An account already exists for that email.');
        }

        $userId = $this->db->insert(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'customer']
        );

        $this->startSession($userId);
        $this->cart->migrateToDatabase();

        return $this->current() ?? throw new RuntimeException('Account could not be loaded.');
    }

    /** @return array<string, mixed> */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user = $this->db->selectOne(
            'SELECT id, name, email, password, role, last_login, created_at FROM users WHERE email = ?',
            [$email]
        );

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            throw new RuntimeException('Email or password is incorrect.');
        }

        $this->db->update('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?', [(int) $user['id']]);
        $this->startSession((int) $user['id']);
        $this->cart->migrateToDatabase();

        return $this->current() ?? throw new RuntimeException('Account could not be loaded.');
    }

    /**
     * Update the authenticated user's name, email, and optionally their password.
     *
     * - Name and email are always updated.
     * - Password is only updated when $newPassword is non-empty, in which case
     *   $currentPassword must match the stored hash first.
     *
     * @throws RuntimeException with a user-facing message on any failure.
     */
    public function updateProfile(int $userId, string $name, string $email, string $currentPassword, string $newPassword): void
    {
        // 1. Validate name
        $name = trim($name);
        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Enter a valid name (max 150 characters).');
        }

        // 2. Validate email
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            throw new RuntimeException('Enter a valid email address.');
        }

        // 3. Load the user's current record so we can verify their password later
        $user = $this->db->selectOne(
            'SELECT id, email, password FROM users WHERE id = ?',
            [$userId]
        );
        if ($user === null) {
            throw new RuntimeException('Account not found.');
        }

        // 4. If the email is changing, make sure no other account already uses it
        if ($email !== strtolower((string) $user['email'])) {
            $taken = $this->db->selectOne(
                'SELECT id FROM users WHERE email = ? AND id != ?',
                [$email, $userId]
            );
            if ($taken !== null) {
                throw new RuntimeException('That email address is already in use by another account.');
            }
        }

        // 5. If they want to change the password, validate it properly
        $hashedPassword = null;
        if ($newPassword !== '') {
            // Always verify the current password before allowing a change
            if (!password_verify($currentPassword, (string) $user['password'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if (strlen($newPassword) > 72) {
                throw new RuntimeException('New password is too long. Use a shorter one.');
            }
            if (str_contains($newPassword, "\0")) {
                throw new RuntimeException('New password contains an unsupported character.');
            }
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        // 6. Run the update — include password column only when it's changing
        if ($hashedPassword !== null) {
            $this->db->update(
                'UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?',
                [$name, $email, $hashedPassword, $userId]
            );
            $_SESSION['rentals_password_stamp'] = hash('sha256',$hashedPassword);
        } else {
            $this->db->update(
                'UPDATE users SET name = ?, email = ? WHERE id = ?',
                [$name, $email, $userId]
            );
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            if (ini_get('session.use_cookies')) {
                setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'],
                    'domain' => $params['domain'], 'secure' => $params['secure'],
                    'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax']);
            }
            session_destroy();
        }
    }

    private function startSession(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['rentals_authenticated_at'] = microtime(true);
        unset($_SESSION['rentals_password_stamp']);
    }
}
