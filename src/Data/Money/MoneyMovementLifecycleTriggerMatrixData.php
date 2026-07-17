<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Money;

use Spatie\LaravelData\Data;

class MoneyMovementLifecycleTriggerMatrixData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $triggers
     * @param  array<int, string>  $open_questions
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.money-semantics.lifecycle-trigger-matrix.v1',
        public readonly string $status = 'planning_only',
        public readonly bool $read_only = true,
        public readonly array $triggers = [],
        public readonly array $open_questions = [],
        public readonly array $redactions = [
            'payloads' => 'trigger-matrix-only',
            'wallet_payloads_exposed' => false,
            'voucher_payloads_exposed' => false,
            'mutates_wallets' => false,
            'reserves_funds' => false,
            'captures_funds' => false,
            'releases_funds' => false,
            'reverses_funds' => false,
        ],
    ) {}
}
