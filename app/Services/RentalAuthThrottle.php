<?php
declare(strict_types=1);
namespace App\Services;

/** Cross-session, atomic limits. Only hashed identities and timestamps are stored. */
final class RentalAuthThrottle
{
    public function __construct(private readonly string $directory = BASE_PATH . '/storage/cache/rentals-auth') {}

    public function attempt(string $action, string $ip, string $email): bool
    {
        $reset = $action === 'reset';
        $window = $reset ? 3600 : 900;
        foreach (['ip' => [$ip, $reset ? 10 : 40], 'account' => [strtolower(trim($email)), $reset ? 3 : 10]] as $kind => [$identity,$limit]) {
            if (!$this->consume(hash('sha256', $action . '|' . $kind . '|' . $identity), $limit, $window)) { return false; }
        }
        return true;
    }

    private function consume(string $key, int $limit, int $window): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) { throw new \RuntimeException('Sign-in protection is unavailable. Please try again later.'); }
        $file = @fopen($this->directory . '/' . $key . '.json', 'c+');
        if (!$file) { throw new \RuntimeException('Sign-in protection is unavailable. Please try again later.'); }
        try {
            if (!flock($file, LOCK_EX)) { throw new \RuntimeException('Sign-in protection is unavailable. Please try again later.'); }
            $now = time();
            $stored = json_decode(stream_get_contents($file) ?: '[]', true);
            $hits = array_values(array_filter(is_array($stored) ? $stored : [], static fn($t) => is_int($t) && $t > $now - $window));
            $allowed = count($hits) < $limit;
            if ($allowed) { $hits[] = $now; }
            rewind($file); ftruncate($file, 0);
            if (fwrite($file, json_encode($hits, JSON_THROW_ON_ERROR)) === false) { throw new \RuntimeException('Sign-in protection is unavailable. Please try again later.'); }
            fflush($file); flock($file, LOCK_UN);
            return $allowed;
        } finally { fclose($file); }
    }
}
