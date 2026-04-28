<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
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

        $builder = new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $encodedPayload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: (int) config('x-change.payment_qr.png.size', 300),
            margin: (int) config('x-change.payment_qr.png.margin', 10),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $result = $builder->build();

        if (config('x-change.payment_qr.validate', false)) {
            $writer = new PngWriter;
            $writer->validateResult($result, $encodedPayload);
        }

        return new RenderedVoucherPaymentQrData(
            voucher_code: $qr->voucher_code,
            format: 'png_base64',
            content_type: $result->getMimeType(),
            payload: $payload,
            rendered: $result->getDataUri(),
        );
    }
}
