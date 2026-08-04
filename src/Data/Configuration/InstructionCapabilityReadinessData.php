<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

final readonly class InstructionCapabilityReadinessData
{
    /**
     * @param  list<string>  $missingConfiguration
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public bool $issuanceAllowed,
        public bool $claimRetryable,
        public ?string $reason = null,
        public array $missingConfiguration = [],
        public string $source = 'x-change',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'issuance_allowed' => $this->issuanceAllowed,
            'claim_retryable' => $this->claimRetryable,
            'reason' => $this->reason,
            'missing_configuration' => $this->missingConfiguration,
            'source' => $this->source,
        ];
    }
}
