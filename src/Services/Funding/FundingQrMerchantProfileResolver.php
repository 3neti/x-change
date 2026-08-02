<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\Merchant\Services\MerchantDisplayNameRenderer;

final class FundingQrMerchantProfileResolver
{
    private const MetadataVersion = 'funding-qr-merchant-v1';

    public function __construct(
        private readonly MerchantProfileRepositoryContract $profiles,
        private readonly MerchantDisplayNameRenderer $displayNames,
    ) {}

    public function resolve(Model $owner): FundingQrMerchantData
    {
        $merchant = $this->profiles->findOrCreateForUser($owner);

        if (! $merchant->is_active) {
            throw ValidationException::withMessages([
                'merchant_profile' => 'The QR merchant profile is inactive.',
            ]);
        }

        $displayName = $this->displayNames->render(
            $merchant,
            (string) config('x-change.product.name', 'X-Change'),
        );
        $displayName = $this->boundedDisplayName(
            $displayName,
            trim((string) $merchant->name),
        );
        $city = trim((string) $merchant->city);
        $city = $city !== ''
            ? $city
            : (string) config('merchant.qr_profile.default_city', 'Manila');
        $categoryCode = trim((string) $merchant->merchant_category_code);
        $profileReference = 'merchant:'.$merchant->uuid;
        $fingerprint = hash('sha256', json_encode([
            'display_name' => $displayName,
            'city' => $city,
            'category_code' => $categoryCode,
            'metadata_version' => self::MetadataVersion,
        ], JSON_THROW_ON_ERROR));

        return new FundingQrMerchantData(
            displayName: $displayName,
            city: $city,
            categoryCode: $categoryCode !== '' ? $categoryCode : null,
            profileReference: $profileReference,
            profileFingerprint: $fingerprint,
            metadataVersion: self::MetadataVersion,
        );
    }

    private function boundedDisplayName(string $displayName, string $merchantName): string
    {
        $maximumLength = max(1, (int) config('merchant.qr_profile.name_max_length', 25));

        if (mb_strlen($displayName, 'UTF-8') <= $maximumLength) {
            return $displayName;
        }

        $fallback = $merchantName !== ''
            ? $merchantName
            : $displayName;

        return mb_substr($fallback, 0, $maximumLength, 'UTF-8');
    }
}
