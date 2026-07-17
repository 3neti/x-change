<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Money;

use Spatie\LaravelData\Data;

class VoucherLiabilitySummaryData extends Data
{
    public function __construct(
        public readonly string $schema = 'x-change.money-semantics.voucher-liability-summary.v1',
        public readonly string $status = 'available',
        public readonly bool $read_only = true,
        public readonly string $currency = 'PHP',
        public readonly int $wallet_balance_minor = 0,
        public readonly int $active_issued_minor = 0,
        public readonly int $redeemed_minor = 0,
        public readonly int $expired_minor = 0,
        public readonly int $cancelled_minor = 0,
        public readonly int $outstanding_liability_minor = 0,
        public readonly int $usable_balance_estimate_minor = 0,
        public readonly int $active_count = 0,
        public readonly int $redeemed_count = 0,
        public readonly int $expired_count = 0,
        public readonly int $cancelled_count = 0,
        public readonly array $redactions = [
            'payloads' => 'liability-summary-only',
            'wallet_payloads_exposed' => false,
            'voucher_payloads_exposed' => false,
            'mutates_wallets' => false,
            'releases_funds' => false,
        ],
    ) {}
}
