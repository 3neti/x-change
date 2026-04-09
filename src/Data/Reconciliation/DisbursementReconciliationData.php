<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Reconciliation;

use Spatie\LaravelData\Data;

class DisbursementReconciliationData extends Data
{
    /**
     * @param  array<string, mixed>|null  $raw_request
     * @param  array<string, mixed>|null  $raw_response
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public ?int $id,
        public ?int $voucher_id,
        public string $voucher_code,
        public ?string $claim_type,
        public ?string $provider,
        public ?string $provider_reference,
        public ?string $provider_transaction_id,
        public ?string $transaction_uuid,
        public string $status,
        public ?string $internal_status,
        public ?float $amount,
        public ?string $currency,
        public ?string $bank_code,
        public ?string $account_number_masked,
        public ?string $settlement_rail,
        public int $attempt_count = 1,
        public ?string $attempted_at = null,
        public ?string $completed_at = null,
        public ?string $last_checked_at = null,
        public ?string $next_retry_at = null,
        public bool $needs_review = false,
        public ?string $review_reason = null,
        public ?string $error_message = null,
        public ?array $raw_request = null,
        public ?array $raw_response = null,
        public ?array $meta = null,
    ) {}
}
