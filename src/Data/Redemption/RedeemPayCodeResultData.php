<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class RedeemPayCodeResultData extends Data
{
    /**
     * @param  array<string, mixed>  $redeemer
     * @param  array<string, mixed>  $bank_account
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $disbursement
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public bool $redeemed,
        public string $status,
        public array $redeemer,
        public array $bank_account,
        public array $inputs,
        public array $disbursement,
        public array $messages = [],
    ) {}
}
