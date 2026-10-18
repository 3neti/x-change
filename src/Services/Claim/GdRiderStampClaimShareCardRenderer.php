<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use DOMDocument;
use Endroid\QrCode\Builder\Builder;
use GdImage;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardRendererContract;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Contracts\RiderStampRecipientResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampRecipientData;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampPresentation;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;
use LBHurtado\XStamp\Appearance;
use LBHurtado\XStamp\Design;
use LBHurtado\XStamp\QrPresentation;
use LBHurtado\XStamp\Renderer;
use ReflectionClass;
use RuntimeException;

final readonly class GdRiderStampClaimShareCardRenderer implements ClaimShareCardRendererContract, RiderStampClaimCardComposerContract
{
    private const string CacheVersion = 'v21';

    private const int Width = 1200;

    private const int Height = 630;

    public function __construct(
        private RiderStampCopyResolverContract $copy,
        private RiderStampRecipientResolverContract $recipient,
        private RiderSplashArtworkSnapshotterContract $splashArtwork,
        private ClaimUrlQrRendererContract $claimQr,
        private RiderUrlArtworkPreviewResolver $urlArtwork,
        private ClaimShareCardAmountFormatter $amounts,
        private PayCodeStampIndicatorResolver $indicators,
        private RiderStampDesignRegistry $stampDesigns,
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

    public function compose(
        Voucher $voucher,
        string $claimUrl,
    ): ClaimShareCardData {
        $contents = $this->renderPng($voucher, $claimUrl);

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
        $rider = $voucher->instructions->rider;
        $palette = $this->stampDesigns->palette(
            data_get($rider, 'stamp.design_id')
                ?? data_get($voucher, 'metadata.instructions.metadata.custom.rider_stamp_design.id'),
            data_get($rider, 'stamp.design_version')
                ?? data_get($voucher, 'metadata.instructions.metadata.custom.rider_stamp_design.version'),
        );
        $designId = (string) (data_get($rider, 'stamp.design_id') ?? data_get($voucher, 'metadata.instructions.metadata.custom.rider_stamp_design.id'));
        $designVersion = (int) (data_get($rider, 'stamp.design_version') ?? data_get($voucher, 'metadata.instructions.metadata.custom.rider_stamp_design.version'));
        $customArtwork = ($rider->stamp?->artwork_treatment?->value ?? 'automatic') !== 'text'
            && in_array($rider->stamp?->artwork_source?->value ?? $rider->og_source ?? '', ['url', 'splash'], true);
        if ($designId !== '' && ! str_starts_with($designId, 'x-change-') && ! $customArtwork) {
            $formattedAmount = $this->amounts->format($voucher);
            $design = app(StampCatalog::class)->presentation($designId, $designVersion, $rider->stamp?->claim_marker_position?->value ?? 'bottom_right', $formattedAmount);
            $copy = $this->copy->resolve($voucher);
            $recipient = $this->recipient->resolve($voucher);
            $identity = app(StampProviderIdentity::class)->forVoucher($voucher);
            $showProvider = $rider->stamp?->show_logo !== false;
            $providerName = $showProvider && ! isset($identity['logo']) ? ($identity['name'] ?? '') : '';
            $hasProviderLogo = $showProvider && isset($identity['logo']);
            $design = app(StampCatalog::class)->providerPresentation($design, $providerName, $hasProviderLogo);
            $appearance = app(StampPresentation::class)->appearance($design, data_get($voucher, 'metadata.instructions.metadata.custom.stamp_presentation'));
            $design = (new Appearance)->apply($design, $appearance);
            $palette = app(StampCatalog::class)->markerPalette($design);
            $kind = $rider->stamp?->claim_marker?->value ?? 'qr';
            $qr = in_array($kind, ['qr', 'both'], true) ? base64_decode(explode(',', $this->claimQr->render($claimUrl), 2)[1], true) : null;
            $composition = (new QrPresentation)->compose($design, $qr, $voucher->code, $kind, $rider->stamp?->claim_marker_position?->value ?? 'bottom_right', $palette['paper'], $palette['trim'], $appearance['qr']);
            $badges = $this->systemIndicators($composition['design'], $this->indicators->resolve($voucher), $rider->stamp?->claim_marker_position?->value ?? 'bottom_right', $kind);
            imagedestroy($canvas);

            return app(Renderer::class)->png($badges['design'], [
                'provider_name' => $providerName,
                'brand' => $rider->stamp?->show_logo === false ? '' : 'Pay Code',
                'tagline' => $rider->stamp?->show_tagline === false || ! $appearance['options']['show_tagline'] ? '' : 'The instruction that moves money.',
                'amount' => $formattedAmount,
                'recipient' => $recipient->visible ? $recipient->label : '',
                'purpose' => isset($design->document['customization']) && $copy->source === 'custom' && blank($rider->stamp?->title) ? $copy->description : $copy->plainText(),
                'expiry' => $voucher->expires_at === null ? 'No expiry' : 'Expires '.$voucher->expires_at->format('M j, Y'),
                'instructions' => implode(' · ', $this->indicators->resolve($voucher)),
            ], [...($hasProviderLogo ? ['provider_logo' => $identity['logo']] : []), ...$composition['images'], ...$badges['images']]);
        }
        $this->paintBrandBackground($canvas, $palette);
        $artwork = $this->resolveArtwork($voucher);

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
        $this->paintIndicators(
            $canvas,
            $voucher,
            $this->indicators->resolve($voucher),
        );
        $this->paintCopy(
            $canvas,
            $voucher,
            $copy->title,
            $copy->description,
            $copy->rasterDescription,
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

    /**
     * @param  list<string>  $keys
     * @return array{design: Design, images: array<string, string>}
     */
    public function systemIndicators(Design $design, array $keys, string $position = 'bottom_right', string $marker = 'qr'): array
    {
        $keys = array_map(static fn (string $key): string => match ($key) {
            'validation.selfie' => 'input.selfie',
            'validation.signature' => 'input.signature',
            'validation.location' => 'input.location',
            'validation.otp' => 'input.otp',
            'validation.mobile' => 'input.mobile',
            default => $key,
        }, $keys);
        $keys = array_slice(array_values(array_unique($keys)), 0, 6);
        if ($keys === []) {
            return ['design' => $design, 'images' => []];
        }
        $width = count($keys) * 48 - 8;
        $image = imagecreatetruecolor($width, 40);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        foreach ($keys as $index => $key) {
            $this->paintIndicator($image, $key, $index * 48 + 1, 1, 38);
        }
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);
        if (! is_string($contents)) {
            throw new RuntimeException('Cannot encode Stamp indicators.');
        }
        $right = $marker !== 'none' && $position === 'top_right' ? self::Width - 264 : self::Width - 62;
        $layer = ['type' => 'image', 'slot' => 'claim_indicators', 'x' => $right - $width, 'y' => 65, 'width' => $width, 'height' => 40, 'color' => '#047857'];

        return ['design' => $design->withLayers([...$design->document['layers'], $layer]), 'images' => ['claim_indicators' => $contents]];
    }

    /**
     * @param  array{start: array{int, int, int}, end: array{int, int, int}, glow_primary: array{int, int, int}, glow_secondary: array{int, int, int}}  $palette
     */
    private function paintBrandBackground(GdImage $canvas, array $palette): void
    {
        for ($y = 0; $y < self::Height; $y++) {
            $ratio = $y / self::Height;
            $red = (int) round($palette['start'][0] + (($palette['end'][0] - $palette['start'][0]) * $ratio));
            $green = (int) round($palette['start'][1] + (($palette['end'][1] - $palette['start'][1]) * $ratio));
            $blue = (int) round($palette['start'][2] + (($palette['end'][2] - $palette['start'][2]) * $ratio));
            $color = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, self::Width, $y, $color);
        }

        $primary = imagecolorallocatealpha(
            $canvas,
            $palette['glow_primary'][0],
            $palette['glow_primary'][1],
            $palette['glow_primary'][2],
            58,
        );
        $secondary = imagecolorallocatealpha(
            $canvas,
            $palette['glow_secondary'][0],
            $palette['glow_secondary'][1],
            $palette['glow_secondary'][2],
            72,
        );
        imagefilledellipse($canvas, 1080, 20, 540, 540, $primary);
        imagefilledellipse($canvas, 80, 660, 520, 430, $secondary);
    }

    private function resolveArtwork(Voucher $voucher): ?GdImage
    {
        $rider = $voucher->instructions->rider;
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
            'splash' => $this->embeddedSplashImage($rider->splash)
                ?? $this->splashArtwork->dataUrl($voucher),
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
            $logoPath = dirname(__DIR__, 3).'/resources/assets/images/brand-library/x-change/png/x-change-logo.png';
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

    /**
     * @param  list<string>  $indicatorKeys
     */
    private function paintIndicators(
        GdImage $canvas,
        Voucher $voucher,
        array $indicatorKeys,
    ): void {
        if ($indicatorKeys === []) {
            return;
        }

        $diameter = 38;
        $gap = 10;
        $markerPosition = $voucher->instructions->rider->stamp
            ?->claim_marker_position?->value ?? 'bottom_right';
        $marker = $voucher->instructions->rider->stamp
            ?->claim_marker?->value ?? 'qr';
        $rightEdge = $marker !== 'none' && $markerPosition === 'top_right'
            ? self::Width - 264
            : self::Width - 62;
        $x = $rightEdge - count($indicatorKeys) * ($diameter + $gap) + $gap;
        $y = 66;

        foreach ($indicatorKeys as $indicatorKey) {
            $this->paintIndicator($canvas, $indicatorKey, $x, $y, $diameter);
            $x += $diameter + $gap;
        }
    }

    private function paintIndicator(
        GdImage $canvas,
        string $indicatorKey,
        int $x,
        int $y,
        int $diameter,
    ): void {
        $background = imagecolorallocate($canvas, 4, 120, 87);
        $foreground = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledellipse(
            $canvas,
            $x + (int) round($diameter / 2),
            $y + (int) round($diameter / 2),
            $diameter,
            $diameter,
            $background,
        );
        imagesetthickness($canvas, 2);

        match (true) {
            $indicatorKey === 'outcome.account_funding' => $this->paintLandmarkIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'outcome.collect_payment' => $this->paintCoinIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'outcome.settlement' => $this->paintSettlementIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'input.name' => $this->paintPersonIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'input.email' => $this->paintEnvelopeIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'input.address' => $this->paintLocationIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'input.gross_monthly_income' => $this->paintCoinIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            $indicatorKey === 'input.reference_code' => $this->paintHashIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'mobile') => $this->paintMobileIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'otp') => $this->paintKeyIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'identity')
                || str_contains($indicatorKey, 'kyc') => $this->paintCheckIndicator(
                    $canvas,
                    $x,
                    $y,
                    $foreground,
                ),
            str_contains($indicatorKey, 'selfie') => $this->paintFaceIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'signature') => $this->paintPenIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'location') => $this->paintLocationIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            str_contains($indicatorKey, 'time')
                || str_contains($indicatorKey, 'birth_date') => $this->paintClockIndicator(
                    $canvas,
                    $x,
                    $y,
                    $foreground,
                ),
            $indicatorKey === 'claim.multiple' => $this->paintSplitIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
            default => $this->paintBanknoteIndicator(
                $canvas,
                $x,
                $y,
                $foreground,
            ),
        };

        imagesetthickness($canvas, 1);
    }

    private function paintBanknoteIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imagerectangle($canvas, $x + 9, $y + 12, $x + 29, $y + 26, $color);
        imageellipse($canvas, $x + 19, $y + 19, 7, 7, $color);
    }

    private function paintLandmarkIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 8, $y + 15, $x + 19, $y + 8, $color);
        imageline($canvas, $x + 19, $y + 8, $x + 30, $y + 15, $color);
        imageline($canvas, $x + 9, $y + 29, $x + 29, $y + 29, $color);

        foreach ([12, 19, 26] as $pillarX) {
            imageline(
                $canvas,
                $x + $pillarX,
                $y + 17,
                $x + $pillarX,
                $y + 26,
                $color,
            );
        }
    }

    private function paintCoinIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 20, $y + 17, 13, 13, $color);
        imageline($canvas, $x + 9, $y + 26, $x + 16, $y + 22, $color);
        imageline($canvas, $x + 16, $y + 22, $x + 28, $y + 25, $color);
    }

    private function paintSettlementIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 9, $y + 14, $x + 28, $y + 14, $color);
        imageline($canvas, $x + 24, $y + 10, $x + 28, $y + 14, $color);
        imageline($canvas, $x + 24, $y + 18, $x + 28, $y + 14, $color);
        imageline($canvas, $x + 29, $y + 25, $x + 10, $y + 25, $color);
        imageline($canvas, $x + 14, $y + 21, $x + 10, $y + 25, $color);
        imageline($canvas, $x + 14, $y + 29, $x + 10, $y + 25, $color);
    }

    private function paintMobileIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imagerectangle($canvas, $x + 13, $y + 7, $x + 25, $y + 31, $color);
        imagefilledellipse($canvas, $x + 19, $y + 27, 2, 2, $color);
    }

    private function paintPersonIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 19, $y + 14, 9, 9, $color);
        imagearc($canvas, $x + 19, $y + 29, 20, 15, 190, 350, $color);
    }

    private function paintEnvelopeIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imagerectangle($canvas, $x + 8, $y + 11, $x + 30, $y + 27, $color);
        imageline($canvas, $x + 8, $y + 12, $x + 19, $y + 21, $color);
        imageline($canvas, $x + 30, $y + 12, $x + 19, $y + 21, $color);
    }

    private function paintHashIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 14, $y + 8, $x + 11, $y + 30, $color);
        imageline($canvas, $x + 25, $y + 8, $x + 22, $y + 30, $color);
        imageline($canvas, $x + 8, $y + 15, $x + 29, $y + 15, $color);
        imageline($canvas, $x + 7, $y + 23, $x + 28, $y + 23, $color);
    }

    private function paintKeyIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 14, $y + 16, 10, 10, $color);
        imageline($canvas, $x + 18, $y + 20, $x + 29, $y + 29, $color);
        imageline($canvas, $x + 25, $y + 25, $x + 29, $y + 21, $color);
    }

    private function paintCheckIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 10, $y + 20, $x + 17, $y + 27, $color);
        imageline($canvas, $x + 17, $y + 27, $x + 29, $y + 11, $color);
    }

    private function paintFaceIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 19, $y + 17, 11, 11, $color);
        imagearc($canvas, $x + 19, $y + 31, 20, 14, 190, 350, $color);
    }

    private function paintPenIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 10, $y + 28, $x + 27, $y + 11, $color);
        imageline($canvas, $x + 13, $y + 30, $x + 30, $y + 13, $color);
        imageline($canvas, $x + 10, $y + 28, $x + 13, $y + 30, $color);
    }

    private function paintLocationIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 19, $y + 16, 14, 14, $color);
        imagefilledellipse($canvas, $x + 19, $y + 16, 3, 3, $color);
        imageline($canvas, $x + 14, $y + 21, $x + 19, $y + 30, $color);
        imageline($canvas, $x + 24, $y + 21, $x + 19, $y + 30, $color);
    }

    private function paintClockIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageellipse($canvas, $x + 19, $y + 19, 20, 20, $color);
        imageline($canvas, $x + 19, $y + 19, $x + 19, $y + 12, $color);
        imageline($canvas, $x + 19, $y + 19, $x + 25, $y + 23, $color);
    }

    private function paintSplitIndicator(
        GdImage $canvas,
        int $x,
        int $y,
        int $color,
    ): void {
        imageline($canvas, $x + 12, $y + 9, $x + 12, $y + 19, $color);
        imageline($canvas, $x + 12, $y + 19, $x + 27, $y + 28, $color);
        imageline($canvas, $x + 12, $y + 19, $x + 27, $y + 10, $color);
        imagefilledellipse($canvas, $x + 28, $y + 10, 4, 4, $color);
        imagefilledellipse($canvas, $x + 28, $y + 28, 4, 4, $color);
    }

    private function paintCopy(
        GdImage $canvas,
        Voucher $voucher,
        string $title,
        string $description,
        string $rasterDescription,
        int $textColor,
        int $mutedColor,
        bool $showCopy,
    ): void {
        $amount = $this->amounts->format($voucher);
        $this->paintAmount($canvas, $amount, 72, 254, $textColor);

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
        $this->paintDescription(
            canvas: $canvas,
            description: $description,
            rasterDescription: $rasterDescription,
            x: 72,
            y: $titleBottom + 24,
            color: $mutedColor,
        );
    }

    private function paintAmount(
        GdImage $canvas,
        string $amount,
        int $x,
        int $baseline,
        int $color,
    ): void {
        if (! str_starts_with($amount, '₱')) {
            $this->drawText($canvas, $amount, 46, $x, $baseline, $color);

            return;
        }

        $this->drawText($canvas, 'P', 46, $x, $baseline, $color);
        imagesetthickness($canvas, 2);
        imageline(
            $canvas,
            $x - 1,
            $baseline - 28,
            $x + 39,
            $baseline - 28,
            $color,
        );
        imageline(
            $canvas,
            $x - 1,
            $baseline - 21,
            $x + 39,
            $baseline - 21,
            $color,
        );
        imagesetthickness($canvas, 1);
        $this->drawText(
            $canvas,
            mb_substr($amount, 1),
            46,
            $x + 43,
            $baseline,
            $color,
        );
    }

    private function paintDescription(
        GdImage $canvas,
        string $description,
        string $rasterDescription,
        int $x,
        int $y,
        int $color,
    ): void {
        if (! $this->containsKnownSymbols($description)) {
            $this->drawWrappedText(
                $canvas,
                $rasterDescription,
                18,
                $x,
                $y,
                720,
                30,
                $color,
                3,
            );

            return;
        }

        $segments = preg_split('/\s*·\s*/u', trim($description)) ?: [];
        $line = 0;

        foreach ($segments as $segment) {
            if ($line >= 3) {
                break;
            }

            $symbols = $this->knownSymbols($segment);

            if ($symbols !== [] && $this->containsOnlyKnownSymbols($segment)) {
                $this->paintSymbolRow(
                    $canvas,
                    $symbols,
                    $x,
                    $y + ($line * 30),
                );
                $line++;

                continue;
            }

            $text = $this->rasterSafeText($segment);

            if ($text === '') {
                continue;
            }

            $this->drawWrappedText(
                $canvas,
                $text,
                18,
                $x,
                $y + ($line * 30),
                720,
                30,
                $color,
                1,
            );
            $line++;
        }
    }

    /**
     * @return list<string>
     */
    private function knownSymbols(string $text): array
    {
        preg_match_all(
            '/🤝|❤️|❤|✌️|✌|🔫|✈️|✈|⭐/u',
            $text,
            $matches,
        );

        return array_values(array_map(
            static fn (string $symbol): string => match ($symbol) {
                '❤️', '❤' => 'heart',
                '✌️', '✌' => 'peace',
                '🔫' => 'water_pistol',
                '✈️', '✈' => 'airplane',
                '⭐' => 'star',
                default => 'handshake',
            },
            $matches[0] ?? [],
        ));
    }

    private function containsKnownSymbols(string $text): bool
    {
        return $this->knownSymbols($text) !== [];
    }

    private function containsOnlyKnownSymbols(string $text): bool
    {
        $withoutSymbols = preg_replace(
            '/🤝|❤️|❤|✌️|✌|🔫|✈️|✈|⭐/u',
            '',
            $text,
        ) ?? $text;
        $withoutSpacing = preg_replace(
            '/[\s\x{00A0}]+/u',
            '',
            $withoutSymbols,
        ) ?? $withoutSymbols;

        return $withoutSpacing === '';
    }

    private function rasterSafeText(string $text): string
    {
        $text = str_replace(
            ['🤝', '❤️', '❤', '✌️', '✌', '🔫', '✈️', '✈', '⭐'],
            [
                'Handshake',
                'Heart',
                'Heart',
                'Peace',
                'Peace',
                'Water pistol',
                'Flight',
                'Flight',
                'Star',
            ],
            $text,
        );

        return trim(preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{FE0F}\x{200D}]/u',
            '',
            $text,
        ) ?? $text);
    }

    /**
     * @param  list<string>  $symbols
     */
    private function paintSymbolRow(
        GdImage $canvas,
        array $symbols,
        int $x,
        int $baseline,
    ): void {
        $cursor = $x;

        foreach ($symbols as $symbol) {
            $this->paintSymbol($canvas, $symbol, $cursor, $baseline - 20);
            $cursor += 40;
        }
    }

    private function paintSymbol(
        GdImage $canvas,
        string $symbol,
        int $x,
        int $y,
    ): void {
        match ($symbol) {
            'heart' => $this->paintHeart($canvas, $x, $y),
            'peace' => $this->paintPeace($canvas, $x, $y),
            'water_pistol' => $this->paintWaterPistol($canvas, $x, $y),
            'airplane' => $this->paintAirplane($canvas, $x, $y),
            'star' => $this->paintStar($canvas, $x, $y),
            default => $this->paintHandshake($canvas, $x, $y),
        };
    }

    private function paintHandshake(GdImage $canvas, int $x, int $y): void
    {
        $left = imagecolorallocate($canvas, 251, 191, 142);
        $right = imagecolorallocate($canvas, 217, 154, 101);
        $cuff = imagecolorallocate($canvas, 56, 189, 248);
        imagesetthickness($canvas, 6);
        imageline($canvas, $x + 3, $y + 7, $x + 15, $y + 18, $left);
        imageline($canvas, $x + 27, $y + 7, $x + 16, $y + 18, $right);
        imagesetthickness($canvas, 1);
        imagefilledellipse($canvas, $x + 15, $y + 18, 10, 8, $left);
        imagefilledrectangle($canvas, $x, $y + 3, $x + 6, $y + 10, $cuff);
        imagefilledrectangle($canvas, $x + 24, $y + 3, $x + 30, $y + 10, $cuff);
    }

    private function paintHeart(GdImage $canvas, int $x, int $y): void
    {
        $red = imagecolorallocate($canvas, 244, 63, 94);
        imagefilledellipse($canvas, $x + 10, $y + 9, 16, 16, $red);
        imagefilledellipse($canvas, $x + 21, $y + 9, 16, 16, $red);
        imagefilledpolygon($canvas, [
            $x + 3, $y + 10,
            $x + 28, $y + 10,
            $x + 16, $y + 27,
        ], $red);
    }

    private function paintPeace(GdImage $canvas, int $x, int $y): void
    {
        $green = imagecolorallocate($canvas, 52, 211, 153);
        imagesetthickness($canvas, 3);
        imageellipse($canvas, $x + 15, $y + 14, 25, 25, $green);
        imageline($canvas, $x + 15, $y + 2, $x + 15, $y + 26, $green);
        imageline($canvas, $x + 15, $y + 14, $x + 7, $y + 23, $green);
        imageline($canvas, $x + 15, $y + 14, $x + 23, $y + 23, $green);
        imagesetthickness($canvas, 1);
    }

    private function paintWaterPistol(
        GdImage $canvas,
        int $x,
        int $y,
    ): void {
        $green = imagecolorallocate($canvas, 34, 197, 94);
        $orange = imagecolorallocate($canvas, 251, 146, 60);
        $blue = imagecolorallocate($canvas, 56, 189, 248);
        imagefilledrectangle($canvas, $x + 2, $y + 5, $x + 21, $y + 14, $green);
        imagefilledrectangle($canvas, $x + 20, $y + 7, $x + 30, $y + 11, $orange);
        imagefilledpolygon($canvas, [
            $x + 8, $y + 13,
            $x + 19, $y + 13,
            $x + 15, $y + 26,
            $x + 10, $y + 26,
        ], $blue);
        imagefilledellipse($canvas, $x + 7, $y + 9, 5, 5, $orange);
    }

    private function paintAirplane(GdImage $canvas, int $x, int $y): void
    {
        $blue = imagecolorallocate($canvas, 56, 189, 248);
        imagefilledpolygon($canvas, [
            $x + 1, $y + 12,
            $x + 12, $y + 10,
            $x + 25, $y + 2,
            $x + 29, $y + 4,
            $x + 20, $y + 13,
            $x + 29, $y + 21,
            $x + 25, $y + 23,
            $x + 12, $y + 16,
            $x + 4, $y + 23,
            $x + 7, $y + 15,
        ], $blue);
    }

    private function paintStar(GdImage $canvas, int $x, int $y): void
    {
        $amber = imagecolorallocate($canvas, 250, 204, 21);
        $points = [];

        for ($index = 0; $index < 10; $index++) {
            $radius = $index % 2 === 0 ? 14 : 6;
            $angle = deg2rad(-90 + ($index * 36));
            $points[] = (int) round($x + 15 + (cos($angle) * $radius));
            $points[] = (int) round($y + 14 + (sin($angle) * $radius));
        }

        imagefilledpolygon($canvas, $points, $amber);
    }

    /** @param array{paper: string, trim: string}|null $markerPalette */
    private function paintClaimMarker(
        GdImage $canvas,
        Voucher $voucher,
        string $claimUrl,
        int $textColor,
        ?array $markerPalette = null,
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
        $underlay = null;
        if ($markerPalette !== null) {
            $underlay = imagecreatetruecolor($boxWidth + 1, $boxHeight + 1);
            imagecopy($underlay, $canvas, 0, 0, $x, $y, $boxWidth + 1, $boxHeight + 1);
        }

        if ($showQr) {
            $qr = $this->imageFromDataUrl($this->claimQr->render($claimUrl));

            if ($qr instanceof GdImage) {
                $paper = $markerPalette['paper'] ?? '#FFFFFF';
                $paperRgb = [hexdec(substr($paper, 1, 2)), hexdec(substr($paper, 3, 2)), hexdec(substr($paper, 5, 2))];
                $trim = $markerPalette['trim'] ?? '#FFFFFF';
                $trimColor = imagecolorallocate($canvas, hexdec(substr($trim, 1, 2)), hexdec(substr($trim, 3, 2)), hexdec(substr($trim, 5, 2)));
                $paperColor = imagecolorallocate($canvas, ...$paperRgb);
                imagefilledrectangle($canvas, $x, $y, $x + 190, $y + 190, $trimColor);
                imagefilledrectangle($canvas, $x + 2, $y + 2, $x + 188, $y + 188, $paperColor);
                if ($underlay instanceof GdImage) {
                    imagecopymerge($canvas, $underlay, $x, $y, 0, 0, $boxWidth + 1, $boxHeight + 1, 70);
                }
                if ($markerPalette !== null) {
                    $this->styleStampQr($qr, $paperRgb);
                }
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
        if ($underlay instanceof GdImage) {
            imagedestroy($underlay);
        }
    }

    /** @param array{int, int, int} $paper */
    private function styleStampQr(GdImage $qr, array $paper): void
    {
        imagealphablending($qr, false);
        imagesavealpha($qr, true);
        $width = imagesx($qr);
        $height = imagesy($qr);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorsforindex($qr, imagecolorat($qr, $x, $y));
                $rgb = [$color['red'], $color['green'], $color['blue']];
                $ink = min(1, max(0, 299 - 100 * array_sum($rgb) / 255)) * .9;
                $center = abs($x + .5 - $width / 2) < $width * 22 / 256
                    && abs($y + .5 - $height / 2) < $height * 21.5 / 256;
                $backing = $center ? .3 : .5;
                $alpha = $ink + $backing * (1 - $ink);
                foreach ($rgb as $channel => &$value) {
                    $value = (int) round(($value * $ink + $paper[$channel] * $backing * (1 - $ink)) / $alpha);
                }
                unset($value);
                imagesetpixel($qr, $x, $y, imagecolorallocatealpha($qr, ...[...$rgb, (int) round((1 - $alpha) * 127)]));
            }
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
