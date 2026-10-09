<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;

final readonly class MedicardDemoBenefitResponseData
{
    public const SCHEMA = 'x-change.medicard-demo-benefit-response.v1';

    public const RESULT_CODE = 'benefit_ready_demo';

    public const PRODUCT_CODE = 'MEDICARD_DEMO_DAY';

    public function __construct(
        public string $demoReference,
        public CarbonImmutable $effectiveAt,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'status' => 'ready_demo',
            'result_code' => self::RESULT_CODE,
            'demo_reference' => $this->demoReference,
            'product_code' => self::PRODUCT_CODE,
            'effective_at' => $this->effectiveAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'demonstration_only' => true,
            'membership_created' => false,
            'healthcare_coverage_created' => false,
            'treatment_authorized' => false,
        ];
    }

    public function outcome(): PolicyCompletionOutcomeData
    {
        return new PolicyCompletionOutcomeData(
            status: PolicyCompletionOutcomeStatus::Succeeded,
            resultCode: self::RESULT_CODE,
            providerReference: $this->demoReference,
            safeResult: [
                'decision' => 'ready_demo',
                'document_ready' => false,
                'provider_status' => 'ready_demo',
                'reason_code' => 'demonstration_only',
                'retryable' => false,
            ],
        );
    }
}
