<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Brick\Money\Money;
use DOMDocument;
use Endroid\QrCode\Builder\Builder;
use GdImage;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardRendererContract;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Contracts\RiderStampRecipientResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampRecipientData;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;
use ReflectionClass;
use RuntimeException;

final readonly class GdRiderStampClaimShareCardRenderer implements ClaimShareCardRendererContract
{
    private const string CacheVersion = 'v4';

    private const int Width = 1200;

    private const int Height = 630;

    public function __construct(
        private RiderStampCopyResolverContract $copy,
        private RiderStampRecipientResolverContract $recipient,
        private ClaimUrlQrRendererContract $claimQr,
        private RiderUrlArtworkPreviewResolver $urlArtwork,
    ) {}

    public function render(Voucher $voucher, string $claimUrl): ClaimShareCardData
    {
        $cacheKey = $this->cacheKey($voucher, $claimUrl);
        $cacheTtl = max(
            60,
            (int) config('x-change.claim.share.cache_ttl_seconds', 300),
        );
        $contents = Cache::remember(
            $cacheKey,
            $cacheTtl,
            fn (): string => $this->renderPng($voucher, $claimUrl),
        );

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('The Rider Stamp share card could not be rendered.');
        }

        return new ClaimShareCardData(
            contents: $contents,
            etag: '"'.hash('sha256', $contents).'"',
        );
    }

    private function renderPng(Voucher $voucher, string $claimUrl): string
    {
        $canvas = imagecreatetruecolor(self::Width, self::Height);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('The Rider Stamp canvas could not be created.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $this->paintBrandBackground($canvas);

        $rider = $voucher->instructions->rider;
        $artwork = $this->resolveArtwork($rider);

        if ($artwork instanceof GdImage) {
            $this->paintArtwork($canvas, $artwork, $rider);
            imagedestroy($artwork);
            $this->paintScrim($canvas, $rider);
        }

        $copy = $this->copy->resolve($voucher);
        $darkText = $artwork === null
            && ($rider->stamp?->theme?->value ?? 'automatic') === 'light';
        $textColor = $darkText
            ? imagecolorallocate($canvas, 15, 23, 42)
            : imagecolorallocate($canvas, 255, 255, 255);
        $mutedColor = $darkText
            ? imagecolorallocate($canvas, 71, 85, 105)
            : imagecolorallocate($canvas, 226, 232, 240);

        $this->paintBrand($canvas, $rider, $textColor, $mutedColor);
        $this->paintCopy(
            $canvas,
            $voucher,
            $copy->title,
            $copy->description,
            $textColor,
            $mutedColor,
            $copy->visible,
        );
        $this->paintRecipient(
            $canvas,
            $voucher,
            $this->recipient->resolve($voucher),
            $textColor,
            $mutedColor,
        );
        $this->paintClaimMarker($canvas, $voucher, $claimUrl, $textColor);

        ob_start();
        imagepng($canvas, null, 8);
        $contents = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('The Rider Stamp PNG could not be encoded.');
        }

        return $contents;
    }

    private function paintBrandBackground(GdImage $canvas): void
    {
        for ($y = 0; $y < self::Height; $y++) {
            $ratio = $y / self::Height;
            $red = (int) round(15 + (18 * $ratio));
            $green = (int) round(23 + (27 * $ratio));
            $blue = (int) round(42 + (27 * $ratio));
            $color = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, self::Width, $y, $color);
        }

        $amber = imagecolorallocatealpha($canvas, 251, 146, 60, 58);
        $emerald = imagecolorallocatealpha($canvas, 16, 185, 129, 72);
        imagefilledellipse($canvas, 1080, 20, 540, 540, $amber);
        imagefilledellipse($canvas, 80, 660, 520, 430, $emerald);
    }

    private function resolveArtwork(RiderInstructionData $rider): ?GdImage
    {
        $stamp = $rider->stamp;
        $artworkSource = $stamp?->artwork_source?->value
            ?? match ($stamp?->source?->value ?? $rider->og_source) {
                'url' => 'url',
                'splash' => 'splash',
                default => 'x_change',
            };

        if (
            ($stamp?->artwork_treatment?->value ?? 'automatic') === 'text'
            || in_array($artworkSource, ['x_change', 'none'], true)
        ) {
            return null;
        }

        $dataUrl = match ($artworkSource) {
            'url' => filled($rider->url)
                ? $this->urlArtwork->resolve((string) $rider->url)['image_url']
                : null,
            'splash' => $this->embeddedSplashImage($rider->splash),
            default => null,
        };

        return $this->imageFromDataUrl($dataUrl);
    }

    private function embeddedSplashImage(?string $splash): ?string
    {
        if (blank($splash)) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML(
                (string) $splash,
                LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR,
            )) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            $source = trim($image->getAttribute('src'));

            if (preg_match(
                '/^data:image\\/(?:png|jpeg|webp);base64,[A-Za-z0-9+\\/=]+$/',
                $source,
            ) === 1) {
                return $source;
            }
        }

        return null;
    }

    private function imageFromDataUrl(mixed $dataUrl): ?GdImage
    {
        if (! is_string($dataUrl)) {
            return null;
        }

        $parts = explode(',', $dataUrl, 2);

        if (
            count($parts) !== 2
            || preg_match('/^data:image\\/(?:png|jpeg|webp);base64$/', $parts[0]) !== 1
        ) {
            return null;
        }

        $contents = base64_decode($parts[1], true);
        $maximumBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_image_bytes',
                2 * 1024 * 1024,
            ),
        );

        if (
            ! is_string($contents)
            || $contents === ''
            || strlen($contents) > $maximumBytes
        ) {
            return null;
        }

        $dimensions = @getimagesizefromstring($contents);
        $maximumPixels = max(
            self::Width * self::Height,
            (int) config(
                'x-change.claim.share.maximum_artwork_pixels',
                16_000_000,
            ),
        );

        if (
            ! is_array($dimensions)
            || ! isset($dimensions[0], $dimensions[1])
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || ($dimensions[0] * $dimensions[1]) > $maximumPixels
        ) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        return $image instanceof GdImage ? $image : null;
    }

    private function paintArtwork(
        GdImage $canvas,
        GdImage $artwork,
        RiderInstructionData $rider,
    ): void {
        $sourceWidth = imagesx($artwork);
        $sourceHeight = imagesy($artwork);
        $fit = $rider->stamp?->fit?->value ?? 'cover';
        $scale = $fit === 'contain'
            ? min(self::Width / $sourceWidth, self::Height / $sourceHeight)
            : max(self::Width / $sourceWidth, self::Height / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        [$targetX, $targetY] = $this->artworkPosition(
            $rider->stamp?->position?->value ?? 'center',
            $targetWidth,
            $targetHeight,
        );

        if ($fit === 'contain') {
            $backdrop = imagecreatetruecolor(self::Width, self::Height);

            if ($backdrop instanceof GdImage) {
                imagecopyresampled(
                    $backdrop,
                    $artwork,
                    0,
                    0,
                    0,
                    0,
                    self::Width,
                    self::Height,
                    $sourceWidth,
                    $sourceHeight,
                );
                imagefilter($backdrop, IMG_FILTER_GAUSSIAN_BLUR);
                imagefilter($backdrop, IMG_FILTER_GAUSSIAN_BLUR);
                imagecopy($canvas, $backdrop, 0, 0, 0, 0, self::Width, self::Height);
                imagedestroy($backdrop);
            }
        }

        imagecopyresampled(
            $canvas,
            $artwork,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function artworkPosition(
        string $position,
        int $width,
        int $height,
    ): array {
        $centerX = (int) round((self::Width - $width) / 2);
        $centerY = (int) round((self::Height - $height) / 2);

        return match ($position) {
            'top' => [$centerX, 0],
            'bottom' => [$centerX, self::Height - $height],
            'left' => [0, $centerY],
            'right' => [self::Width - $width, $centerY],
            default => [$centerX, $centerY],
        };
    }

    private function paintScrim(
        GdImage $canvas,
        RiderInstructionData $rider,
    ): void {
        $scrim = $rider->stamp?->scrim ?? 28;
        $alpha = 127 - (int) round((max(0, min(100, $scrim)) / 100) * 127);
        $color = imagecolorallocatealpha($canvas, 2, 6, 23, $alpha);
        imagefilledrectangle($canvas, 0, 0, self::Width, self::Height, $color);
    }

    private function paintBrand(
        GdImage $canvas,
        RiderInstructionData $rider,
        int $textColor,
        int $mutedColor,
    ): void {
        if ($rider->stamp?->show_logo !== false) {
            $logoPath = dirname(__DIR__, 3).'/resources/assets/images/logo-orange.png';
            $logo = is_file($logoPath) ? @imagecreatefrompng($logoPath) : false;

            if ($logo instanceof GdImage) {
                $height = 58;
                $width = (int) round(imagesx($logo) * ($height / imagesy($logo)));
                imagecopyresampled(
                    $canvas,
                    $logo,
                    72,
                    58,
                    0,
                    0,
                    $width,
                    $height,
                    imagesx($logo),
                    imagesy($logo),
                );
                imagedestroy($logo);
            }

            $this->drawText($canvas, 'x-change', 20, 150, 92, $textColor);
        }

        if ($rider->stamp?->show_tagline !== false) {
            $this->drawText(
                $canvas,
                'Money should adapt to people. Not the other way around.',
                16,
                72,
                590,
                $mutedColor,
            );
        }
    }

    private function paintCopy(
        GdImage $canvas,
        Voucher $voucher,
        string $title,
        string $description,
        int $textColor,
        int $mutedColor,
        bool $showCopy,
    ): void {
        $amount = $this->formattedAmount($voucher);
        $this->drawText($canvas, $amount, 46, 72, 254, $textColor);

        if (! $showCopy) {
            return;
        }

        $titleBottom = $this->drawWrappedText(
            $canvas,
            $title,
            31,
            72,
            330,
            760,
            44,
            $textColor,
            2,
        );
        $this->drawWrappedText(
            $canvas,
            $description,
            18,
            72,
            $titleBottom + 24,
            720,
            30,
            $mutedColor,
            3,
        );
    }

    private function paintClaimMarker(
        GdImage $canvas,
        Voucher $voucher,
        string $claimUrl,
        int $textColor,
    ): void {
        $marker = $voucher->instructions->rider->stamp?->claim_marker?->value
            ?? 'qr';

        if ($marker === 'none') {
            return;
        }

        $position = $voucher->instructions->rider->stamp
            ?->claim_marker_position?->value ?? 'bottom_right';
        $showQr = in_array($marker, ['qr', 'both'], true);
        $showCode = in_array($marker, ['code', 'both'], true);
        $boxWidth = $showQr ? 190 : 250;
        $boxHeight = $showQr ? ($showCode ? 226 : 190) : 62;
        [$x, $y] = $this->markerPosition($position, $boxWidth, $boxHeight);

        if ($showQr) {
            $qr = $this->imageFromDataUrl($this->claimQr->render($claimUrl));

            if ($qr instanceof GdImage) {
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, $x, $y, $x + 190, $y + 190, $white);
                imagecopyresampled(
                    $canvas,
                    $qr,
                    $x + 10,
                    $y + 10,
                    0,
                    0,
                    170,
                    170,
                    imagesx($qr),
                    imagesy($qr),
                );
                imagedestroy($qr);
            }
        }

        if ($showCode) {
            $codeY = $showQr ? $y + 218 : $y + 42;
            $this->drawText(
                $canvas,
                (string) $voucher->code,
                20,
                $x,
                $codeY,
                $textColor,
            );
        }
    }

    private function paintRecipient(
        GdImage $canvas,
        Voucher $voucher,
        RiderStampRecipientData $recipient,
        int $textColor,
        int $mutedColor,
    ): void {
        if (! $recipient->visible) {
            return;
        }

        $markerPosition = $voucher->instructions->rider->stamp
            ?->claim_marker_position?->value ?? 'bottom_right';
        $marker = $voucher->instructions->rider->stamp
            ?->claim_marker?->value ?? 'qr';
        $x = $marker !== 'none' && $markerPosition === 'bottom_left'
            ? 330
            : 72;

        $this->drawText(
            $canvas,
            $recipient->eyebrow,
            14,
            $x,
            525,
            $mutedColor,
        );
        $this->drawText(
            $canvas,
            $recipient->label,
            20,
            $x,
            558,
            $textColor,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function markerPosition(
        string $position,
        int $width,
        int $height,
    ): array {
        $margin = 54;

        return match ($position) {
            'top_left' => [$margin, $margin],
            'top_right' => [self::Width - $width - $margin, $margin],
            'bottom_left' => [$margin, self::Height - $height - $margin],
            default => [
                self::Width - $width - $margin,
                self::Height - $height - $margin,
            ],
        };
    }

    private function formattedAmount(Voucher $voucher): string
    {
        $amount = data_get($voucher, 'cash.amount');
        $currency = (string) data_get($voucher, 'cash.currency', 'PHP');
        $value = $amount instanceof Money
            ? $amount->getAmount()->toFloat()
            : (is_numeric($amount) ? (float) $amount : 0.0);

        return strtoupper($currency) === 'PHP'
            ? 'PHP '.number_format($value, 2)
            : strtoupper($currency).' '.number_format($value, 2);
    }

    private function drawText(
        GdImage $canvas,
        string $text,
        int $size,
        int $x,
        int $y,
        int $color,
    ): void {
        imagettftext(
            $canvas,
            $size,
            0,
            $x,
            $y,
            $color,
            $this->fontPath(),
            $text,
        );
    }

    private function drawWrappedText(
        GdImage $canvas,
        string $text,
        int $size,
        int $x,
        int $y,
        int $maximumWidth,
        int $lineHeight,
        int $color,
        int $maximumLines,
    ): int {
        $words = preg_split('/\\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);

            if (
                $line !== ''
                && $this->textWidth($candidate, $size) > $maximumWidth
            ) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        $lines = array_slice($lines, 0, $maximumLines);

        foreach ($lines as $index => $renderedLine) {
            if (
                $index === $maximumLines - 1
                && count($words) > count(preg_split('/\\s+/', implode(' ', $lines)) ?: [])
            ) {
                $renderedLine = rtrim($renderedLine, '.,').'…';
            }

            $this->drawText(
                $canvas,
                $renderedLine,
                $size,
                $x,
                $y + ($index * $lineHeight),
                $color,
            );
        }

        return $y + (max(1, count($lines)) * $lineHeight);
    }

    private function textWidth(string $text, int $size): int
    {
        $bounds = imagettfbbox($size, 0, $this->fontPath(), $text);

        return is_array($bounds) ? abs($bounds[2] - $bounds[0]) : 0;
    }

    private function fontPath(): string
    {
        $builderPath = (new ReflectionClass(Builder::class))->getFileName();

        if (! is_string($builderPath)) {
            throw new RuntimeException('The Rider Stamp font could not be resolved.');
        }

        $fontPath = dirname($builderPath, 3).'/assets/open_sans.ttf';

        if (! is_file($fontPath)) {
            throw new RuntimeException('The Rider Stamp font is unavailable.');
        }

        return $fontPath;
    }

    private function cacheKey(Voucher $voucher, string $claimUrl): string
    {
        $instructions = json_encode(
            $voucher->instructions->toArray(),
            JSON_THROW_ON_ERROR,
        );

        return 'x-change:claim:share-card:'.hash(
            'sha256',
            implode('|', [
                self::CacheVersion,
                (string) $voucher->code,
                (string) $voucher->updated_at?->toJSON(),
                $claimUrl,
                $instructions,
                config('x-change.claim.share.recipient.enabled', true)
                    ? 'recipient-visible'
                    : 'recipient-hidden',
            ]),
        );
    }
}
