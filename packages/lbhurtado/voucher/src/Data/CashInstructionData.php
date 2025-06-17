<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;
use Brick\Money\Money;

class CashInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public float $amount,
        public string $currency,
        public CashValidationRulesData $validation,
    ) { $this->applyRulesAndDefaults(); }

    protected function rulesAndDefaults(): array
    {
        return [
            'amount' => [
                ['required', 'numeric', 'min:50'],
                config('instructions.cash.amount')
            ],
            'currency' => [
                ['required', 'string', 'size:3'],
                config('instructions.cash.currency')
            ]
        ];
    }

    public function getAmount(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
