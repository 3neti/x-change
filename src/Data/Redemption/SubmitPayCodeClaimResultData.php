<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class SubmitPayCodeClaimResultData extends Data
{
    /**
     * @param  array<int, string>  $messages
     * @param  array<string, mixed>|null  $settlement
     */
    public function __construct(
        public string $voucher_code,
        public string $claim_type,
        public bool $claimed,
        public string $status,
        public ?float $requested_amount = null,
        public ?float $disbursed_amount = null,
        public ?string $currency = null,
        public ?float $remaining_balance = null,
        public bool $fully_claimed = false,
        public mixed $disbursement = null,
        public array $messages = [],
        public ?array $settlement = null,
    ) {}

    public function toArray(): array
    {
        $data = parent::toArray();

        if (($data['settlement'] ?? null) === null) {
            unset($data['settlement']);
        }

        return $data;
    }
}
