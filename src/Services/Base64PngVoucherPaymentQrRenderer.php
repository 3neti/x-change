<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;

class Base64PngVoucherPaymentQrRenderer implements VoucherPaymentQrRendererContract
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

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        /*
         * Placeholder renderer:
         * For now this produces a base64 data URI wrapper around the canonical payload.
         * Next refinement can replace this internals with endroid/qr-code or QRPH/EMVCo.
         */
        $base64 = base64_encode($encodedPayload);

        return new RenderedVoucherPaymentQrData(
            voucher_code: $qr->voucher_code,
            format: 'png_base64',
            content_type: 'image/png',
            payload: $payload,
            rendered: 'data:image/png;base64,'.$base64,
        );
    }
}
