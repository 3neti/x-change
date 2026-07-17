<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Money;

use Spatie\LaravelData\Data;

class MoneyMovementTargetModelData extends Data
{
    /**
     * @param  array<int, string>  $approval_requirements
     * @param  array<int, string>  $implementation_boundaries
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.money-semantics.target-model.v1',
        public readonly string $status = 'pending_human_approval',
        public readonly bool $read_only = true,
        public readonly string $current_model = 'debit_at_issuance',
        public readonly string $recommended_model = 'reserve_at_issuance_debit_at_redemption',
        public readonly ?string $selected_model = null,
        public readonly bool $requires_human_approval = true,
        public readonly array $approval_requirements = [],
        public readonly array $implementation_boundaries = [],
        public readonly array $redactions = [
            'payloads' => 'target-model-decision-only',
            'wallet_payloads_exposed' => false,
            'voucher_payloads_exposed' => false,
            'mutates_wallets' => false,
            'reserves_funds' => false,
            'releases_funds' => false,
            'refunds_funds' => false,
        ],
    ) {}
}
