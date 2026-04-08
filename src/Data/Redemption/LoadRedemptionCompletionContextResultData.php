<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class LoadRedemptionCompletionContextResultData extends Data
{
    /**
     * @param  array<string, mixed>  $collected_data
     * @param  array<string, mixed>  $flat_data
     * @param  array<string, mixed>  $wallet
     * @param  array<string, mixed>  $inputs
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public bool $can_confirm,
        public ?string $reference_id,
        public ?string $flow_id,
        public array $collected_data,
        public array $flat_data,
        public array $wallet,
        public array $inputs,
        public array $messages = [],
    ) {}
}
