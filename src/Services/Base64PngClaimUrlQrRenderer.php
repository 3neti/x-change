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

class Base64PngClaimUrlQrRenderer implements ClaimUrlQrRendererContract
{
    public function render(string $claimUrl): string
    {
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
            size: 240,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }
}
