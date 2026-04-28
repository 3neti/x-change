<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;

class DefaultVoucherPaymentQrRenderer implements VoucherPaymentQrRendererContract
{
    public function render(VoucherPaymentQrData $qr): RenderedVoucherPaymentQrData
    {
        $payload = [
            'type' => 'x-change.payment_qr',
            'voucher_code' => $qr->voucher_code,
            'flow_type' => $qr->flow_type,
            'route_key' => $qr->route_key,
            'url' => $qr->url,
            'qr_type' => $qr->qr_type,
            'capabilities' => $qr->capabilities,
        ];

        return new RenderedVoucherPaymentQrData(
            voucher_code: $qr->voucher_code,
            format: 'json',
            content_type: 'application/json',
            payload: $payload,
            rendered: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
