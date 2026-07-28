<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use DOMDocument;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimShareMetadataData;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;

final readonly class RiderStampClaimShareMetadataResolver implements ClaimShareMetadataResolverContract
{
    public function __construct(
        private RiderUrlArtworkPreviewResolver $urlArtwork,
    ) {}

    public function resolve(Voucher $voucher, string $claimUrl): ClaimShareMetadataData
    {
        $rider = $voucher->instructions->rider;
        $title = $this->safeText(
            $rider->stamp?->title,
            "Pay Code {$voucher->code}",
            90,
        );
        $description = $this->safeText(
            $rider->stamp?->description,
            $this->safeText(
                $rider->message,
                (string) config(
                    'x-change.claim.share.default_description',
                    'A Pay Code is ready to claim securely in X-Change.',
                ),
                180,
            ),
            180,
        );
        $imageUrl = $this->resolveImageUrl($rider)
            ?? $this->absoluteWebUrl(
                config(
                    'x-change.claim.share.default_image',
                    '/vendor/x-change/images/logo-orange.png',
                ),
            );

        return new ClaimShareMetadataData(
            title: $title,
            description: $description,
            url: $claimUrl,
            siteName: (string) config(
                'x-change.claim.share.site_name',
                config('x-change.branding.name', 'X-Change'),
            ),
            imageUrl: $imageUrl,
            imageAlt: "{$title} preview",
        );
    }

    private function resolveImageUrl(RiderInstructionData $rider): ?string
    {
        $artworkSource = $rider->stamp?->artwork_source?->value
            ?? $rider->stamp?->source->value
            ?? $rider->og_source
            ?? 'automatic';

        if ($artworkSource === 'url' && filled($rider->url)) {
            $resolved = $this->urlArtwork->resolve((string) $rider->url);
            $publicImageUrl = $this->absoluteWebUrl(
                $resolved['public_image_url'] ?? null,
            );

            if ($publicImageUrl !== null) {
                return $publicImageUrl;
            }
        }

        if (in_array($artworkSource, ['splash', 'automatic', 'url'], true)) {
            return $this->splashImageUrl($rider->splash);
        }

        return null;
    }

    private function splashImageUrl(?string $splash): ?string
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
            $imageUrl = $this->absoluteWebUrl($image->getAttribute('src'));

            if ($imageUrl !== null) {
                return $imageUrl;
            }
        }

        return null;
    }

    private function absoluteWebUrl(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        $parts = parse_url($value);

        if (
            ! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $value;
    }

    private function safeText(mixed $value, string $fallback, int $limit): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $text = Str::of(
            html_entity_decode(
                strip_tags($value),
                ENT_QUOTES | ENT_HTML5,
            ),
        )->squish()->limit($limit)->toString();

        return $text === '' ? $fallback : $text;
    }
}
