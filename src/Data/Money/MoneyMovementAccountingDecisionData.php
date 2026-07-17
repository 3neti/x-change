<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Money;

use Spatie\LaravelData\Data;

class MoneyMovementAccountingDecisionData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $candidate_models
     * @param  array<int, string>  $required_decisions
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.money-semantics.accounting-decision.v1',
        public readonly string $status = 'decision_required',
        public readonly bool $read_only = true,
        public readonly string $current_model = 'debit_at_issuance',
        public readonly string $recommended_next_model = 'reservation_release_pending_decision',
        public readonly array $candidate_models = [],
        public readonly array $required_decisions = [],
        public readonly array $redactions = [
            'payloads' => 'accounting-decision-only',
            'wallet_payloads_exposed' => false,
            'voucher_payloads_exposed' => false,
            'mutates_wallets' => false,
            'reserves_funds' => false,
            'releases_funds' => false,
            'refunds_funds' => false,
        ],
    ) {}
}
