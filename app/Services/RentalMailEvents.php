<?php
declare(strict_types=1);

namespace App\Services;

/** The event catalog is independent of SMTP and of future rental workflows. */
final class RentalMailEvents
{
    public const RENTAL_SUBMITTED = 'rental_submitted';
    public const RENTAL_APPROVED = 'rental_approved';
    public const RENTAL_REJECTED = 'rental_rejected';
    public const PAYMENT_PROOF_RECEIVED = 'payment_proof_received';
    public const PAYMENT_APPROVED = 'payment_approved';
    public const PAYMENT_REJECTED = 'payment_rejected';

    public static function labels(): array
    {
        return [self::RENTAL_SUBMITTED => 'Rental request received', self::RENTAL_APPROVED => 'Rental approved',
            self::RENTAL_REJECTED => 'Rental rejected', self::PAYMENT_PROOF_RECEIVED => 'Payment proof received',
            self::PAYMENT_APPROVED => 'Payment confirmed', self::PAYMENT_REJECTED => 'Payment proof needs attention'];
    }

    public static function status(string $event): int
    {
        return match ($event) {
            self::RENTAL_APPROVED, self::PAYMENT_APPROVED => RentalPaymentStatus::APPROVED,
            self::RENTAL_REJECTED, self::PAYMENT_REJECTED => RentalPaymentStatus::REJECTED,
            self::RENTAL_SUBMITTED, self::PAYMENT_PROOF_RECEIVED => RentalPaymentStatus::PENDING,
            default => throw new \InvalidArgumentException('Unknown email event.'),
        };
    }

    public static function placeholders(string $audience): array
    {
        $common = ['customer_name', 'customer_email', 'order_number', 'order_status', 'rental_start_date',
            'rental_end_date', 'subtotal', 'security_deposit', 'total_amount', 'payment_method', 'payment_status',
            'company_name', 'support_email', 'order_items', 'rejection_reason', 'order_status_url'];
        return $audience === 'admin' ? [...$common, 'customer_phone', 'proof_submitted', 'admin_order_url'] : $common;
    }

    public static function defaults(string $event, string $audience): array
    {
        $label = self::labels()[$event] ?? throw new \InvalidArgumentException('Unknown email event.');
        if (!in_array($audience, ['customer', 'admin'], true)) { throw new \InvalidArgumentException('Unknown email audience.'); }
        $intro = match ($event) {
            self::RENTAL_SUBMITTED => 'We received your rental request and payment proof. The request and payment are pending review. We will email you after the team reviews them.',
            self::RENTAL_APPROVED => 'Your rental request has been approved. Payment review status: {{payment_status}}. Please contact the team to arrange pickup or delivery.',
            self::RENTAL_REJECTED => 'Your rental request was reviewed and was not approved. {{rejection_reason}} Please contact support for assistance or submit a new request.',
            self::PAYMENT_PROOF_RECEIVED => 'We received your payment proof. It is awaiting verification. There is no need to resubmit unless our team requests a replacement.',
            self::PAYMENT_APPROVED => 'Your payment has been confirmed. Your rental request is approved. Please contact the team to arrange pickup or delivery.',
            self::PAYMENT_REJECTED => 'Your payment proof needs attention and your request was not approved. {{rejection_reason}} Sign in to My rentals and upload a replacement proof for review.',
        };
        $subject = match ($event) {
            self::RENTAL_SUBMITTED => 'Rental Request {{order_number}} Received — Pending Review',
            self::RENTAL_APPROVED => 'Your Rental Request {{order_number}} Has Been Approved',
            self::RENTAL_REJECTED => 'Update on Your Rental Request {{order_number}}',
            self::PAYMENT_PROOF_RECEIVED => 'We Received Your Payment Proof — {{order_number}}',
            self::PAYMENT_APPROVED => 'Payment Confirmed — {{order_number}}',
            self::PAYMENT_REJECTED => 'Payment Proof Needs Attention — {{order_number}}',
        };
        $summary = "Order: {{order_number}}\nStatus: {{order_status}}\n\nEquipment / services:\n{{order_items}}\n\nRental dates: {{rental_start_date}} – {{rental_end_date}}\nSubtotal: {{subtotal}}\nSecurity deposit: {{security_deposit}}\nTotal: {{total_amount}}\nPayment method: {{payment_method}}\nPayment status: {{payment_status}}";
        $body = "Hi {{customer_name}},\n\n{$intro}\n\n{$summary}\n\nView your status: {{order_status_url}}\n\nQuestions? Contact {{support_email}}.\nThank you,\n{{company_name}}";
        if ($audience === 'admin') {
            $subject = ($event === self::RENTAL_SUBMITTED ? 'New Rental Request Received' : $label) . ' — {{order_number}}';
            $body = "{$label}.\n\nCustomer: {{customer_name}}\nEmail: {{customer_email}}\nPhone: {{customer_phone}}\n\n{$summary}\nProof submitted: {{proof_submitted}}\n\nReview securely in Admin: {{admin_order_url}}\n\n{{company_name}}";
        }
        return ['event_key' => $event, 'audience' => $audience, 'display_name' => $label . ' — ' . ucfirst($audience),
            'subject' => $subject, 'body' => $body, 'is_active' => 1, 'updated_at' => null];
    }
}
