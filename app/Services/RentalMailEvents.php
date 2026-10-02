<?php
declare(strict_types=1);

namespace App\Services;

/** Fixed system content. There are no database overrides or website settings. */
final class RentalMailEvents
{
    public const RENTAL_SUBMITTED = 'rental_submitted';
    public const RENTAL_APPROVED = 'rental_approved';
    public const RENTAL_REJECTED = 'rental_rejected';
    public const PAYMENT_PROOF_RECEIVED = 'payment_proof_received';

    public static function labels(): array
    {
        return [self::RENTAL_SUBMITTED => 'Rental request received', self::RENTAL_APPROVED => 'Rental approved',
            self::RENTAL_REJECTED => 'Rental rejected', self::PAYMENT_PROOF_RECEIVED => 'Payment proof received'];
    }

    public static function status(string $event): int
    {
        return match ($event) {
            self::RENTAL_APPROVED => RentalPaymentStatus::APPROVED,
            self::RENTAL_REJECTED => RentalPaymentStatus::REJECTED,
            self::RENTAL_SUBMITTED, self::PAYMENT_PROOF_RECEIVED => RentalPaymentStatus::PENDING,
            default => throw new \InvalidArgumentException('Unknown email event.'),
        };
    }

    public static function placeholders(string $audience): array
    {
        $common = ['customer_name', 'customer_email', 'order_number', 'order_status', 'rental_start_date',
            'rental_end_date', 'subtotal', 'security_deposit', 'total_amount', 'payment_method', 'payment_status',
            'company_name', 'support_email', 'order_items', 'rejection_reason', 'order_status_url', 'proof_receipt_text'];
        return $audience === 'admin' ? [...$common, 'customer_phone', 'proof_submitted', 'admin_order_url'] : $common;
    }

    public static function defaults(string $event, string $audience): array
    {
        $label = self::labels()[$event] ?? throw new \InvalidArgumentException('Unknown email event.');
        if (!in_array($audience, ['customer', 'admin'], true)) { throw new \InvalidArgumentException('Unknown email audience.'); }
        $intro = match ($event) {
            self::RENTAL_SUBMITTED => 'We received your rental request. {{proof_receipt_text}} Your request and payment/proof are Pending review by our team. We will email you after review.',
            self::RENTAL_APPROVED => 'Your rental request and payment review have been approved under the current shared status. Please contact the team to arrange pickup or delivery.',
            self::RENTAL_REJECTED => 'Your rental request and payment review were rejected. {{rejection_reason}} Please contact support for assistance. If a replacement proof is needed, sign in to your rental account to upload it for review.',
            self::PAYMENT_PROOF_RECEIVED => 'We received your payment proof. It is awaiting verification. There is no need to resubmit unless our team requests a replacement.',
        };
        $subject = match ($event) {
            self::RENTAL_SUBMITTED => 'Rental Request Received — {{order_number}}',
            self::RENTAL_APPROVED => 'Rental Request Approved — {{order_number}}',
            self::RENTAL_REJECTED => 'Rental Request Update — {{order_number}}',
            self::PAYMENT_PROOF_RECEIVED => 'We Received Your Payment Proof — {{order_number}}',
        };
        $summary = "Order: {{order_number}}\nStatus: {{order_status}}\n\nEquipment / services:\n{{order_items}}\n\nRental dates: {{rental_start_date}} – {{rental_end_date}}\nSubtotal: {{subtotal}}\nSecurity deposit: {{security_deposit}}\nTotal: {{total_amount}}\nPayment method: {{payment_method}}\nPayment status: {{payment_status}}";
        $body = "Hi {{customer_name}},\n\n{$intro}\n\n{$summary}\n\nView your status: {{order_status_url}}\n\nQuestions? Contact {{support_email}}.\nThank you,\n{{company_name}}";
        if ($audience === 'admin') {
            $subject = match ($event) {
                self::RENTAL_SUBMITTED => 'New Rental Request',
                self::RENTAL_APPROVED => 'Rental Request Approved',
                self::RENTAL_REJECTED => 'Rental Request Rejected',
                self::PAYMENT_PROOF_RECEIVED => 'Payment Proof Received',
            } . ' — {{order_number}}';
            $body = "{$label}.\n\nCustomer: {{customer_name}}\nEmail: {{customer_email}}\nPhone: {{customer_phone}}\n\n{$summary}\nProof submitted: {{proof_submitted}}\n\nReview securely in Admin: {{admin_order_url}}\n\n{{company_name}}";
        }
        return ['subject' => $subject, 'body' => $body];
    }
}
