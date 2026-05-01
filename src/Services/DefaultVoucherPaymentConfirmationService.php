<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class DefaultVoucherPaymentConfirmationService implements VoucherPaymentConfirmationContract
{
    public function __construct(
        protected VoucherPaymentProviderManager $providers,
    ) {}

    public function confirm(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        $provider = (string) Arr::get(
            $payload,
            'provider',
            config('x-change.payment.default_provider', 'manual'),
        );

        return $this->providers
            ->driver($provider)
            ->confirm($voucher, $payload);
    }
}
