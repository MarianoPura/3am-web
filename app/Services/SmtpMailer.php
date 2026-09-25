<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Minimal SMTP client.
 *
 * Written in-house rather than pulling in PHPMailer, because the site is
 * designed to run without `composer install` (see bootstrap.php). It covers
 * exactly what the site needs: one plain-text UTF-8 message, To + Cc,
 * STARTTLS (port 587) or implicit TLS (port 465), AUTH LOGIN/PLAIN.
 *
 * Certificates ARE verified. Turning verification off to make a
 * misconfigured server work would send the SMTP password to anyone able to
 * intercept the connection.
 *
 * Errors throw RuntimeException with the server's reply but never the
 * credentials, so the message is safe to log.
 */
final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;

    /**
     * @param array{
     *   enabled?: bool,
     *   host: string,
     *   port: int,
     *   encryption: string,
     *   username: string,
     *   password: string,
     *   timeout: int,
     *   from: array{address: string, name: string}
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function isConfigured(): bool
    {
        return ($this->config['enabled'] ?? true)
            && !empty($this->config['host'])
            && !empty($this->config['from']['address']);
    }

    /**
     * Send one plain-text message.
     *
     * @param list<string> $to
     * @param list<string> $cc
     */
    public function send(array $to, string $subject, string $body, array $cc = [], ?string $replyTo = null): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SMTP is not configured or is disabled in config.');
        }

        $to = $this->validAddresses($to);
        $cc = array_values(array_diff($this->validAddresses($cc), $to));

        if ($to === []) {
            throw new RuntimeException('No valid recipient.');
        }

        try {
            $this->connect();
            $this->hello();

            if ($this->config['username'] !== '') {
                $this->authenticate();
            }

            $this->command('MAIL FROM:<' . $this->config['from']['address'] . '>', [250]);
            foreach ([...$to, ...$cc] as $recipient) {
                $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            $this->command('DATA', [354]);
            $this->write($this->message($to, $cc, $subject, $body, $replyTo) . "\r\n.\r\n");
            $this->expect([250]);

            $this->command('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    // ─────────────────────────────────────────────────────────
    // Protocol
    // ─────────────────────────────────────────────────────────

    private function connect(): void
    {
        $encryption = $this->config['encryption'] ?? 'tls';
        $host       = $this->config['host'];
        $port       = (int) ($this->config['port'] ?? 587);
        $timeout    = max(1, (int) ($this->config['timeout'] ?? 15));

        $context = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'peer_name'         => $host,
        ]]);

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new RuntimeException(sprintf('SMTP connect to %s:%d failed: %s', $host, $port, $errstr ?: 'unknown error'));
        }

        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;

        $this->expect([220]);
    }

    private function hello(): void
    {
        $name = $this->heloName();
        $ehlo = $this->command('EHLO ' . $name, [250]);

        if (($this->config['encryption'] ?? 'tls') === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) {
                throw new RuntimeException('SMTP server does not offer STARTTLS; refusing to send credentials unencrypted.');
            }

            $this->command('STARTTLS', [220]);

            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (@stream_socket_enable_crypto($this->socket, true, $crypto) !== true) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed (certificate or TLS version).');
            }

            // Capabilities must be re-read after the TLS upgrade.
            $this->command('EHLO ' . $name, [250]);
        }
    }

    private function authenticate(): void
    {
        $user = $this->config['username'];
        $pass = $this->config['password'];

        $reply = $this->command('AUTH LOGIN', [334, 504]);

        if (str_starts_with($reply, '504')) {
            $this->command('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], 'AUTH PLAIN [credentials]');
            return;
        }

        $this->command(base64_encode($user), [334], '[username]');
        $this->command(base64_encode($pass), [235], '[password]');
    }

    /**
     * Send a command and require one of the expected reply codes.
     *
     * @param list<int> $expected
     * @param string|null $logAs What to show in an error instead of the command (hides credentials).
     */
    private function command(string $line, array $expected, ?string $logAs = null): string
    {
        $this->write($line . "\r\n");

        try {
            return $this->expect($expected);
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('SMTP "%s" rejected: %s', $logAs ?? $line, $e->getMessage()));
        }
    }

    /** @param list<int> $expected */
    private function expect(array $expected): string
    {
        $reply = '';

        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($reply === '') {
            $meta = stream_get_meta_data($this->socket);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'timed out waiting for the server' : 'connection closed by the server');
        }

        if (!in_array((int) substr($reply, 0, 3), $expected, true)) {
            throw new RuntimeException(trim($reply));
        }

        return $reply;
    }

    private function write(string $data): void
    {
        if ($this->socket === null || fwrite($this->socket, $data) === false) {
            throw new RuntimeException('SMTP write failed.');
        }
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    // ─────────────────────────────────────────────────────────
    // Message
    // ─────────────────────────────────────────────────────────

    /**
     * @param list<string> $to
     * @param list<string> $cc
     */
    private function message(array $to, array $cc, string $subject, string $body, ?string $replyTo): string
    {
        $from   = $this->config['from'];
        $domain = substr((string) strrchr($from['address'], '@'), 1) ?: 'localhost';

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->mailbox($from['address'], $from['name']),
            'To: ' . implode(', ', $to),
        ];

        if ($cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = sprintf('Message-ID: <%s@%s>', bin2hex(random_bytes(12)), $domain);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';

        $normalised = str_replace(["\r\n", "\r"], "\n", $body);

        return implode("\r\n", $headers) . "\r\n\r\n"
            . rtrim(chunk_split(base64_encode(str_replace("\n", "\r\n", $normalised)), 76, "\r\n"));
    }

    private function mailbox(string $address, string $name): string
    {
        return $name === '' ? $address : $this->encodeHeader($name) . ' <' . $address . '>';
    }

    private function encodeHeader(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));

        return preg_match('/[^\x20-\x7E]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    /**
     * @param  list<string> $addresses
     * @return list<string>
     */
    private function validAddresses(array $addresses): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($a): string => trim((string) $a), $addresses),
            static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    private function heloName(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost');

        return preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1 ? $host : 'localhost';
    }
}
