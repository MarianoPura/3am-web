<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Captures inquiry submissions.
 *
 * WHY THIS WRITES TO DISK FIRST
 * =============================
 * There is no database yet and no verified SES sender, so email delivery from
 * this box is not something to rely on: PHP's mail() with no configured MTA
 * either fails silently or gets dropped by the recipient's spam filter.
 *
 * A contact form that appears to work and quietly loses enquiries is worse than
 * no form at all — the visitor believes they have made contact, and nobody
 * follows up. So every submission is written to disk BEFORE any attempt to
 * send mail, and the visitor only sees a success page once that write has
 * succeeded. Mail is a best-effort notification on top, never the record.
 *
 * Storage is storage/inquiries/, which sits behind a deny-all .htaccess and
 * outside anything the web server will serve.
 *
 * Replaced by the contact_inquiries table in Phase 5. The JSONL file is
 * trivially importable when that lands — one JSON object per line.
 */
final class InquiryStore
{
    public function __construct(
        private readonly string $storagePath,
        private readonly string $notifyTo,
        private readonly string $siteName,
    ) {
    }

    /**
     * Persist a submission and attempt to notify.
     *
     * @param  array<string, mixed> $data
     * @return string The reference code shown to the visitor
     */
    public function capture(array $data): string
    {
        $reference = $this->reference();

        $record = [
            'reference'  => $reference,
            'received'   => date('c'),
            'form'       => $data['form'] ?? 'unknown',
            'type'       => $data['type'] ?? '',
            'name'       => $data['name'] ?? '',
            'email'      => $data['email'] ?? '',
            'phone'      => $data['phone'] ?? '',
            'company'    => $data['company'] ?? '',
            'details'    => $data['details'] ?? '',
            'ip'         => $data['ip'] ?? '',
            'user_agent' => $data['user_agent'] ?? '',
        ];

        $this->write($record);

        // Best effort. A failed notification must never fail the submission —
        // the record is already safe on disk.
        try {
            $this->notify($record);
        } catch (\Throwable $e) {
            error_log(sprintf('[inquiry %s] notification failed: %s', $reference, $e->getMessage()));
        }

        return $reference;
    }

    /**
     * Human-readable reference: 3AM-2026-A7F3
     *
     * Shown on the confirmation page and included in the notification, so a
     * follow-up phone call has something to quote.
     */
    private function reference(): string
    {
        return sprintf('3AM-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(2))));
    }

    /** @param array<string, mixed> $record */
    private function write(array $record): void
    {
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0770, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException('Inquiry storage directory is not writable.');
        }

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        // LOCK_EX so two simultaneous submissions cannot interleave and corrupt
        // a line. Append-only: a submission is never overwritten or edited.
        $written = file_put_contents($this->storagePath . '/inquiries.jsonl', $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Could not record the inquiry.');
        }
    }

    /** @param array<string, mixed> $record */
    private function notify(array $record): void
    {
        if ($this->notifyTo === '') {
            return;
        }

        $subject = sprintf('[%s] %s enquiry — %s', $record['reference'], ucfirst((string) $record['form']), $record['name']);

        $body = implode("\n", [
            'New enquiry from the website.',
            '',
            'Reference : ' . $record['reference'],
            'Received  : ' . $record['received'],
            'Form      : ' . $record['form'],
            'Type      : ' . $record['type'],
            '',
            'Name      : ' . $record['name'],
            'Email     : ' . $record['email'],
            'Phone     : ' . $record['phone'],
            'Company   : ' . $record['company'],
            '',
            'Details',
            '-------',
            $record['details'],
            '',
            '--',
            'Recorded in storage/inquiries/inquiries.jsonl',
        ]);

        /*
         * From: must be a domain address, not the visitor's. Sending as the
         * visitor is a forged header that SPF will reject outright. Their
         * address goes in Reply-To, which is what "reply" should actually use.
         *
         * Header values are stripped of CR/LF: an unfiltered newline in a
         * header is a mail-injection hole that turns this form into an open
         * relay for spam.
         */
        $from = 'noreply@' . $this->hostFromEmail($this->notifyTo);

        $headers = [
            'From: ' . $this->headerSafe($this->siteName) . ' <' . $this->headerSafe($from) . '>',
            'Reply-To: ' . $this->headerSafe((string) $record['email']),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: 3AM-Site',
        ];

        @mail(
            $this->notifyTo,
            $this->headerSafe($subject),
            $body,
            implode("\r\n", $headers)
        );
    }

    private function headerSafe(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    private function hostFromEmail(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? 'localhost' : substr($at, 1);
    }
}
