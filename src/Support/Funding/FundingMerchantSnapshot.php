<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Funding;

use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;

class FundingMerchantSnapshot
{
    /**
     * @return array<string, string|null>
     */
    public static function fromData(FundingQrMerchantData $merchant): array
    {
        return [
            'displayName' => $merchant->displayName,
            'city' => $merchant->city,
            'categoryCode' => $merchant->categoryCode,
            'profileReference' => $merchant->profileReference,
            'profileFingerprint' => $merchant->profileFingerprint,
            'metadataVersion' => $merchant->metadataVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function toData(array $snapshot): FundingQrMerchantData
    {
        return new FundingQrMerchantData(
            displayName: (string) ($snapshot['displayName'] ?? ''),
            city: (string) ($snapshot['city'] ?? ''),
            categoryCode: self::optionalString($snapshot['categoryCode'] ?? null),
            profileReference: self::optionalString($snapshot['profileReference'] ?? null),
            profileFingerprint: self::optionalString($snapshot['profileFingerprint'] ?? null),
            metadataVersion: (string) ($snapshot['metadataVersion'] ?? 'funding-qr-merchant-v1'),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
