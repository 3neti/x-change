<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Contracts\VoucherPaymentQrGeneratorContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;

class DefaultVoucherPaymentQrGenerator implements VoucherPaymentQrGeneratorContract
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    public function generate(Voucher $voucher): VoucherPaymentQrData
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->can_collect) {
            throw VoucherCannotCollect::forVoucher($voucher, $capabilities);
        }

        return new VoucherPaymentQrData(
            voucher_code: (string) $voucher->code,
            flow_type: $capabilities->type->value,
            route_key: $capabilities->pay_code_route,
            url: url('/'.$capabilities->pay_code_route.'/'.$voucher->code),
            qr_type: $capabilities->qr_type,
            capabilities: [
                'can_disburse' => $capabilities->can_disburse,
                'can_collect' => $capabilities->can_collect,
                'can_settle' => $capabilities->can_settle,
                'requires_envelope' => $capabilities->requires_envelope,
            ],
        );
    }
}
