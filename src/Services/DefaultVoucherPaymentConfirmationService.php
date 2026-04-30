<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class DefaultVoucherPaymentConfirmationService implements VoucherPaymentConfirmationContract
{
    public function confirm(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        return new VoucherPaymentResultData(
            voucher_code: (string) $voucher->code,
            status: Arr::get($payload, 'status', 'succeeded'),
            amount: (float) Arr::get($payload, 'amount'),
            currency: (string) Arr::get($payload, 'currency', 'PHP'),
            provider: Arr::get($payload, 'provider', 'manual'),
            provider_reference: Arr::get($payload, 'provider_reference'),
            provider_transaction_id: Arr::get($payload, 'provider_transaction_id'),
            payer: (array) Arr::get($payload, 'payer', []),
            meta: (array) Arr::get($payload, 'meta', []),
            messages: ['Voucher payment confirmed.'],
        );
    }
}
