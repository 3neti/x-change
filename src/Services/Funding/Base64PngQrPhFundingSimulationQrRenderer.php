<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class Base64PngQrPhFundingSimulationQrRenderer
{
    public function render(int $amountMinor, string $currency): string
    {
        $payload = json_encode([
            'type' => 'x-change.qrph-funding-simulation',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'rollback_only' => true,
            'monetary_value' => false,
        ], JSON_THROW_ON_ERROR);
        $builder = new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 220,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getDataUri();
    }
}
