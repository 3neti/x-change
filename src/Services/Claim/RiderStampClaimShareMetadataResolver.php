<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimShareMetadataData;
use LBHurtado\XChange\Data\Claim\RiderStampArtifactData;

final readonly class RiderStampClaimShareMetadataResolver implements ClaimShareMetadataResolverContract
{
    public function __construct(
        private RiderStampCopyResolverContract $copy,
    ) {}

    public function resolve(
        Voucher $voucher,
        string $claimUrl,
        string $shareCardUrl,
    ): ClaimShareMetadataData {
        $copy = $this->copy->resolve($voucher);
        $share = data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            ? (array) data_get($voucher->metadata, 'instructions.presentation.share', []) : [];
        $title = is_string($share['title'] ?? null) && trim($share['title']) !== '' ? trim($share['title']) : $copy->title;
        $description = is_string($share['description'] ?? null) && trim($share['description']) !== '' ? trim($share['description']) : $copy->description;
        $artifact = RiderStampArtifactData::fromArray(data_get($voucher, 'instructions.metadata.custom.rider_stamp_artifact'));

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
            imageWidth: $artifact?->width ?? 1200,
            imageHeight: $artifact?->height ?? 630,
        );
    }
}
