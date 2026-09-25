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
            'SELECT id, name, email, role, last_login, created_at FROM users WHERE id = ?',
            [$userId]
        );
        if ($user === null) {
            unset($_SESSION['user_id']);
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
        if (strlen($password) < 8) {
            throw new RuntimeException('Use a password with at least 8 characters.');
        }
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

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    private function startSession(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }
}
