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
    public function render(int $amountMinor, string $currency, ?string $reference = null): string
    {
        $payload = json_encode([
            'type' => 'x-change.qrph-funding-simulation',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
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

    public function renderBase64(int $amountMinor, string $currency, ?string $reference = null): string
    {
        $dataUri = $this->render($amountMinor, $currency, $reference);
        $separator = strpos($dataUri, ',');

        return $separator === false ? $dataUri : substr($dataUri, $separator + 1);
    }
}
