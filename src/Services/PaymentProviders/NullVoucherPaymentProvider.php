<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PaymentProviders;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class NullVoucherPaymentProvider implements VoucherPaymentProviderContract
{
    public function confirm(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        return new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'failed',
            amount: (float) ($payload['amount'] ?? 0),
            currency: strtoupper((string) ($payload['currency'] ?? 'PHP')),
            provider: (string) ($payload['provider'] ?? 'null'),
            messages: ['No voucher payment provider is configured.'],
        );
    }
}
