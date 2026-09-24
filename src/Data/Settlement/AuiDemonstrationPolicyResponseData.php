<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;

final readonly class AuiDemonstrationPolicyResponseData
{
    public const SCHEMA = 'x-change.aui-demonstration-policy-response.v1';

    public function __construct(
        public string $policyReference,
        public string $productCode,
        public CarbonImmutable $effectiveAt,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'status' => 'issued_demo',
            'policy_reference' => $this->policyReference,
            'product_code' => $this->productCode,
            'effective_at' => $this->effectiveAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'document_ready' => false,
            'demonstration_only' => true,
        ];
    }

    public function outcome(): PolicyCompletionOutcomeData
    {
        return new PolicyCompletionOutcomeData(
            status: PolicyCompletionOutcomeStatus::Succeeded,
            resultCode: 'policy_issued_demo',
            providerReference: $this->policyReference,
            safeResult: [
                'decision' => 'issued_demo',
                'document_ready' => false,
                'provider_status' => 'issued_demo',
                'reason_code' => 'demonstration_only',
                'retryable' => false,
            ],
        );
    }
}
