<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
class CashInstructionData extends Data
{
    public function __construct(
        public int $amount,
        public string $currency,
        public CashValidationRulesData $validation,
    ) {}

    public static function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'], // ISO 4217 code, e.g., 'PHP'
            'validation' => ['required', 'array'],
        ];
    }
}
