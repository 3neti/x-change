<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PaymentProviders;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class ManualVoucherPaymentProvider implements VoucherPaymentProviderContract
{
    public function confirm(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        $status = (string) Arr::get($payload, 'status', 'succeeded');

        return new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: $status === 'succeeded' ? 'succeeded' : $status,
            amount: (float) Arr::get($payload, 'amount', 0),
            currency: strtoupper((string) Arr::get($payload, 'currency', 'PHP')),
            provider: (string) Arr::get($payload, 'provider', 'manual'),
            provider_reference: Arr::get($payload, 'provider_reference'),
            provider_transaction_id: Arr::get($payload, 'provider_transaction_id'),
            payer: (array) Arr::get($payload, 'payer', []),
            meta: (array) Arr::get($payload, 'meta', []),
            messages: $status === 'succeeded'
                ? ['Manual payment confirmation succeeded.']
                : ['Manual payment confirmation did not succeed.'],
        );
    }
}
