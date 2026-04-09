<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class WithdrawPayCodeResultData extends Data
{
    /**
     * @param  array<string, mixed>  $redeemer
     * @param  array<string, mixed>  $bank_account
     * @param  array<string, mixed>  $disbursement
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public bool $withdrawn,
        public string $status,
        public float $requested_amount,
        public float $disbursed_amount,
        public ?string $currency,
        public ?float $remaining_balance,
        public ?int $slice_number,
        public ?int $remaining_slices,
        public ?string $slice_mode,
        public array $redeemer,
        public array $bank_account,
        public array $disbursement,
        public array $messages = [],
    ) {}
}
