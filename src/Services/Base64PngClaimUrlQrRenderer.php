<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use InvalidArgumentException;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\ScaledClaimUrlQrRendererContract;

class Base64PngClaimUrlQrRenderer implements ClaimUrlQrRendererContract, ScaledClaimUrlQrRendererContract
{
    public function render(string $claimUrl): string
    {
        return $this->renderAtScale($claimUrl, 1);
    }

    public function renderAtScale(string $claimUrl, int $scale): string
    {
        if (! in_array($scale, [1, 2], true)) {
            throw new InvalidArgumentException('Unsupported QR pixel density.');
        }
        $canonicalUrl = trim($claimUrl);

        if (
            filter_var($canonicalUrl, FILTER_VALIDATE_URL) === false ||
            ! in_array(parse_url($canonicalUrl, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new InvalidArgumentException('A canonical HTTP claim URL is required.');
        }

        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $canonicalUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240 * $scale,
            margin: 8 * $scale,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            logoPath: dirname(__DIR__, 2).'/resources/assets/images/pay-code/pay-code-mark.png',
            logoResizeToWidth: 44 * $scale,
            logoPunchoutBackground: true,
        ))->build();

        return $result->getDataUri();
    }
}
