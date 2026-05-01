<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PaymentWebhooks;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Contracts\VoucherPaymentWebhookParserContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class ManualVoucherPaymentWebhookParser implements VoucherPaymentWebhookParserContract
{
    public function parse(array $payload): VoucherPaymentResultData
    {
        return new VoucherPaymentResultData(
            voucher_code: (string) $this->voucherCode($payload),
            status: (string) Arr::get($payload, 'status', 'succeeded'),
            amount: (float) Arr::get($payload, 'amount', 0),
            currency: strtoupper((string) Arr::get($payload, 'currency', 'PHP')),
            provider: (string) Arr::get($payload, 'provider', 'manual'),
            provider_reference: Arr::get($payload, 'provider_reference'),
            provider_transaction_id: Arr::get($payload, 'provider_transaction_id'),
            payer: (array) Arr::get($payload, 'payer', []),
            meta: (array) Arr::get($payload, 'meta', []),
            messages: ['Manual payment webhook parsed.'],
        );
    }

    public function voucherCode(array $payload): ?string
    {
        $code = Arr::get($payload, 'voucher_code')
            ?? Arr::get($payload, 'code')
            ?? Arr::get($payload, 'metadata.voucher_code')
            ?? Arr::get($payload, 'meta.voucher_code');

        return is_string($code) && trim($code) !== ''
            ? trim($code)
            : null;
    }
}
