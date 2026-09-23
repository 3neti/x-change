<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use Carbon\CarbonImmutable;

final readonly class CanonicalProviderFundingTransactionData
{
    /**
     * @param  list<int>  $evidenceObservationIds
     * @param  list<string>  $verificationSources
     * @param  list<string>  $normalizationVersions
     */
    public function __construct(
        public string $providerCode,
        public string $providerTransactionKey,
        public ?string $providerOperationKey,
        public ?string $requestKey,
        public ?string $fundingAddressFingerprint,
        public ?string $providerAccountFingerprint,
        public int $grossAmountMinor,
        public int $feeAmountMinor,
        public int $netAmountMinor,
        public string $currency,
        public string $providerStatus,
        public ?CarbonImmutable $occurredAt,
        public ?CarbonImmutable $settledAt,
        public ?string $settlementRail,
        public bool $destinationVerified,
        public int $canonicalObservationId,
        public array $evidenceObservationIds,
        public array $verificationSources,
        public array $normalizationVersions,
    ) {}

    public function evidenceCount(): int
    {
        return count($this->evidenceObservationIds);
    }
}
