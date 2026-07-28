<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimShareMetadataData;

final readonly class RiderStampClaimShareMetadataResolver implements ClaimShareMetadataResolverContract
{
    public function resolve(
        Voucher $voucher,
        string $claimUrl,
        string $shareCardUrl,
    ): ClaimShareMetadataData {
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

        return new ClaimShareMetadataData(
            title: $title,
            description: $description,
            url: $claimUrl,
            siteName: (string) config(
                'x-change.claim.share.site_name',
                config('x-change.branding.name', 'X-Change'),
            ),
            imageUrl: $shareCardUrl,
            imageAlt: "{$title} preview",
        );
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
