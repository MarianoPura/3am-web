<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Captures and processes inquiry submissions.
 *
 * Persists submissions to:
 *   1. `inquiries` table in MySQL/MariaDB (primary, relational store with foreign key to visits)
 *   2. `tracking_events` (server-side Lead and Contact attribution events)
 *   3. `storage/inquiries/inquiries.jsonl` (resilient append-only file backup)
 *
 * Sends confirmation email to client with CC to internal team over SMTP.
 */
final class InquiryStore
{
    /**
     * @param string $storagePath File storage backup directory
     * @param string $siteName Site/brand display name
     * @param Database|null $db Database access layer
     * @param SmtpMailer|null $mailer Mailer instance
     * @param TrackingService|null $tracking Tracking service
     * @param array{to?: string, cc?: list<string>, reply_to?: string} $mailConfig Inquiry mail settings
     * @param array<string, mixed> $company Company profile configuration
     */
    public function __construct(
        private readonly string $storagePath,
        private readonly string $siteName,
        private readonly ?Database $db = null,
        private readonly ?SmtpMailer $mailer = null,
        private readonly ?TrackingService $tracking = null,
        private readonly array $mailConfig = [],
        private readonly array $company = [],
    ) {
    }

    /**
     * Persist a submission, trigger tracking, and attempt email delivery.
     *
     * @param array<string, mixed> $data
     * @return string The reference code shown to the visitor
     */
    public function capture(array $data): string
    {
        $reference = $this->reference();
        $visitId = $data['visit_id'] ?? $this->tracking?->currentVisitId() ?? null;
        if ($visitId !== null) {
            $visitId = (int) $visitId;
        }

        $record = [
            'reference'   => $reference,
            'visit_id'    => $visitId,
            'received'    => date('c'),
            'form'        => (string) ($data['form'] ?? 'quote'),
            'type'        => (string) ($data['type'] ?? ''),
            'name'        => (string) ($data['name'] ?? ''),
            'email'       => (string) ($data['email'] ?? ''),
            'phone'       => (string) ($data['phone'] ?? ''),
            'company'     => (string) ($data['company'] ?? ''),
            'details'     => (string) ($data['details'] ?? ''),
            'ip'          => (string) ($data['ip'] ?? ''),
            'user_agent'  => (string) ($data['user_agent'] ?? ''),
            'attribution' => (array) ($data['attribution'] ?? []),
        ];

        // ── 1. Store to Database & Disk Backup ───────────────────────
        $failures = [];
        $fileOk   = false;
        $inquiryId = null;

        try {
            $this->writeJsonl($record);
            $fileOk = true;
        } catch (Throwable $e) {
            $failures[] = 'file: ' . $e->getMessage();
        }

        try {
            $inquiryId = $this->insertDatabase($record);
        } catch (Throwable $e) {
            $failures[] = 'database: ' . $e->getMessage();
        }

        if (!$fileOk && $inquiryId === null) {
            throw new RuntimeException('Could not record the inquiry (' . implode('; ', $failures) . ').');
        }

        foreach ($failures as $failure) {
            error_log(sprintf('[inquiry %s] storage degraded — %s', $reference, $failure));
        }

        // ── 2. Record Server-Side Tracking Events ────────────────────
        if ($visitId !== null && $this->tracking !== null) {
            $eventPayload = [
                'reference' => $reference,
                'form'      => $record['form'],
                'type'      => $record['type'],
            ];

            // Server-side Lead event
            $this->tracking->recordEvent(
                visitId: $visitId,
                eventName: 'Lead',
                eventId: $data['lead_event_id'] ?? null,
                eventSource: 'server',
                eventData: $eventPayload,
            );

            // Server-side Contact event
            $this->tracking->recordEvent(
                visitId: $visitId,
                eventName: 'Contact',
                eventId: $data['contact_event_id'] ?? null,
                eventSource: 'server',
                eventData: $eventPayload,
            );
        }

        // ── 3. Send Notification & Confirmation Emails ───────────────
        $this->sendEmails($record);

        return $reference;
    }

    /**
     * Unique inquiry reference code: 3AM-2026-A7F3
     */
    private function reference(): string
    {
        return sprintf('3AM-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(2))));
    }

    /**
     * Insert into MySQL inquiries table.
     */
    private function insertDatabase(array $record): ?int
    {
        if ($this->db === null) {
            return null;
        }

        $nullable = static fn (mixed $v): ?string => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);
        $attrJson = !empty($record['attribution'])
            ? json_encode($record['attribution'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        return $this->db->insert(
            'INSERT INTO inquiries (
                visit_id, reference, form, type, name, email, phone, company, details,
                ip_address, user_agent, attribution
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $record['visit_id'],
                $record['reference'],
                $nullable($record['form']),
                (string) $record['type'],
                (string) $record['name'],
                (string) $record['email'],
                (string) $record['phone'],
                $nullable($record['company']),
                $nullable($record['details']),
                $nullable(mb_substr((string) $record['ip'], 0, 45)),
                $nullable((string) $record['user_agent']),
                $attrJson,
            ]
        );
    }

    /**
     * Append to jsonl file backup.
     */
    private function writeJsonl(array $record): void
    {
        if (!is_dir($this->storagePath) && !@mkdir($this->storagePath, 0770, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException('Inquiry storage directory is not writable.');
        }

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $written = @file_put_contents($this->storagePath . '/inquiries.jsonl', $line, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Could not write inquiries.jsonl.');
        }
    }

    /**
     * Send email notifications via SMTP.
     */
    private function sendEmails(array $record): void
    {
        if ($this->mailer === null || !$this->mailer->isConfigured()) {
            return;
        }

        $clientEmail = trim((string) $record['email']);
        $ccList      = (array) ($this->mailConfig['cc'] ?? []);
        $replyTo     = (string) ($this->mailConfig['reply_to'] ?? '');
        $notifyTo    = (string) ($this->mailConfig['to'] ?? '');

        // 1. Send confirmation to the client (with CC to internal team)
        if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $this->mailer->send(
                    to: [$clientEmail],
                    subject: sprintf('We received your quote inquiry — %s', $record['reference']),
                    body: $this->clientConfirmationBody($record),
                    cc: $ccList,
                    replyTo: $replyTo !== '' ? $replyTo : null,
                );
                return;
            } catch (Throwable $e) {
                error_log(sprintf('[inquiry %s] confirmation email to client failed: %s', $record['reference'], $e->getMessage()));
            }
        }

        // 2. If client email failed or was skipped, ensure internal notifyTo receives the lead
        if (filter_var($notifyTo, FILTER_VALIDATE_EMAIL) && !in_array($notifyTo, $ccList, true)) {
            try {
                $this->mailer->send(
                    to: [$notifyTo],
                    subject: sprintf('[%s] %s inquiry — %s', $record['reference'], ucfirst((string) $record['form']), $record['name']),
                    body: $this->internalNotificationBody($record),
                    cc: $ccList,
                    replyTo: filter_var($clientEmail, FILTER_VALIDATE_EMAIL) ? $clientEmail : null,
                );
            } catch (Throwable $e) {
                error_log(sprintf('[inquiry %s] internal notification email failed: %s', $record['reference'], $e->getMessage()));
            }
        }
    }

    /**
     * Format confirmation body sent to the client.
     */
    private function clientConfirmationBody(array $record): string
    {
        $line = static fn (string $label, mixed $value): ?string
            => trim((string) $value) === '' ? null : str_pad($label, 12) . ': ' . trim((string) $value);

        $address = $this->company['address'] ?? [];
        $footer  = array_filter([
            (string) ($this->company['legal_name'] ?? $this->siteName),
            implode(', ', array_filter([$address['street'] ?? '', $address['locality'] ?? '', $address['region'] ?? ''])),
        ]);

        $lines = [
            'Hi ' . trim((string) $record['name']) . ',',
            '',
            'Thank you for reaching out to ' . $this->siteName . '. We have received your',
            'inquiry and our production team will review your requirements.',
            'We usually get back to you within one business day.',
            '',
            'Your Reference: ' . $record['reference'],
            '',
            'Summary of your inquiry:',
            '----------------------------------------',
            $line('Service', $record['type']),
            $line('Full Name', $record['name']),
            $line('Email', $record['email']),
            $line('Phone', $record['phone']),
            $line('Company', $record['company']),
        ];

        if (trim((string) $record['details']) !== '') {
            $lines[] = '';
            $lines[] = 'Event Details:';
            $lines[] = trim((string) $record['details']);
        }

        return implode("\n", array_merge(
            array_filter($lines, static fn ($l): bool => $l !== null),
            [
                '',
                '----------------------------------------',
                'If you need to update anything or send additional files, simply reply to this email.',
                '',
                '--',
                ...$footer,
            ]
        ));
    }

    /**
     * Format internal alert body sent to the team.
     */
    private function internalNotificationBody(array $record): string
    {
        $lines = [
            'New inquiry received from website.',
            '',
            'Reference : ' . $record['reference'],
            'Received  : ' . $record['received'],
            'Form      : ' . $record['form'],
            'Type      : ' . $record['type'],
            '',
            'Client Info',
            '-----------',
            'Name      : ' . $record['name'],
            'Email     : ' . $record['email'],
            'Phone     : ' . $record['phone'],
            'Company   : ' . $record['company'],
            '',
            'Details',
            '-------',
            $record['details'] ?: '(None provided)',
            '',
            '--',
            'Recorded in database & storage/inquiries/inquiries.jsonl',
        ];

        return implode("\n", $lines);
    }
}
