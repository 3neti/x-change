<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use Spatie\LaravelData\Data;

class VoucherPaymentResultData extends Data
{
    public function __construct(
        public string $voucher_code,
        public string $status,
        public float $amount,
        public string $currency = 'PHP',
        public ?string $provider = null,
        public ?string $provider_reference = null,
        public ?string $provider_transaction_id = null,
        public array $payer = [],
        public array $wallet = [],
        public array $meta = [],
        public array $messages = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
