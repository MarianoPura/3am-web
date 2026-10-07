<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Single-use reset tokens; plaintext token exists only in the outbound email. */
final class RentalPasswordReset
{
    public function __construct(private readonly Database $db, private readonly RentalMailTransport $mail) {}

    public function ready(): bool { return (new RentalSchema($this->db))->hasTable('rental_password_resets'); }

    public function request(string $email): void
    {
        if (!$this->ready()) { throw new RuntimeException('Password recovery is unavailable right now. Please contact support.'); }
        $user = $this->db->selectOne('SELECT id, email FROM users WHERE email=?', [strtolower(trim($email))]);
        if (!$user) { return; }
        $token = bin2hex(random_bytes(32));
        $this->db->transaction(function(Database $db) use ($user,$token): void {
            $db->selectOne('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
            $db->statement('INSERT INTO rental_password_resets (user_id,token_hash,expires_at,created_at)
                VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=VALUES(created_at),used_at=NULL',
                [$user['id'],hash('sha256',$token),date('Y-m-d H:i:s',time()+1800),date('Y-m-d H:i:s')]);
        });
        // No CC: reset links are private, even when transactional order mail uses CC.
        try {
            if (!$this->mail->isConfigured()) { throw new RuntimeException(); }
            $link = absolute_url('rentals/account/reset/' . $token);
            $text = "Reset your 3AM Rentals password:\n" . $link . "\n\nThis link expires in 30 minutes and can be used once. If you did not request it, ignore this email.";
            $html = '<p>Reset your 3AM Rentals password.</p><p><a href="' . htmlspecialchars($link,ENT_QUOTES,'UTF-8') . '">Choose a new password</a></p><p>This link expires in 30 minutes and can be used once. If you did not request it, ignore this email.</p>';
            $this->mail->send($user['email'], 'Reset your 3AM Rentals password', $text, $html, []);
        } catch (\Throwable $e) { error_log('Rentals password recovery delivery unavailable'); }
    }

    public function valid(string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D',$token)!==1 || !$this->ready()) { return false; }
        return $this->db->selectOne('SELECT user_id FROM rental_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256',$token),date('Y-m-d H:i:s')]) !== null;
    }

    public function reset(string $token, string $password, string $confirm): void
    {
        if (strlen($password)<8 || strlen($password)>72 || str_contains($password,"\0")) { throw new RuntimeException('Use a password with 8 to 72 characters.'); }
        if ($password !== $confirm) { throw new RuntimeException('The passwords do not match.'); }
        if (!$this->valid($token)) { throw new RuntimeException('This reset link is invalid or expired. Request a new one.'); }
        $this->db->transaction(function(Database $db) use ($token,$password): void {
            // Lock the account first, matching the request flow to avoid opposite lock ordering.
            $row = $db->selectOne('SELECT user_id FROM rental_password_resets WHERE token_hash=?', [hash('sha256',$token)]);
            if (!$row) { throw new RuntimeException('This reset link is invalid or expired.'); }
            $db->selectOne('SELECT id FROM users WHERE id=? FOR UPDATE', [$row['user_id']]);
            $reset = $db->selectOne('SELECT * FROM rental_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>? FOR UPDATE', [hash('sha256',$token),date('Y-m-d H:i:s')]);
            if (!$reset) { throw new RuntimeException('This reset link is invalid or expired.'); }
            $db->update('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$reset['user_id']]);
            $db->update('UPDATE rental_password_resets SET used_at=?,reset_at=? WHERE user_id=?',[date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$reset['user_id']]);
        });
    }
}
