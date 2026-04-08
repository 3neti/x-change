<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class SubmitPayCodeClaimResultData extends Data
{
    /**
     * @param  array<string, mixed>  $disbursement
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public string $claim_type,
        public bool $claimed,
        public string $status,
        public ?float $requested_amount,
        public ?float $disbursed_amount,
        public ?string $currency,
        public ?float $remaining_balance,
        public bool $fully_claimed,
        public array $disbursement,
        public array $messages = [],
    ) {}
}
