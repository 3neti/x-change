<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Keepsake;

final readonly class InstanceKeepsakePlan
{
    /** @param list<InstanceKeepsakeContribution> $contributions */
    public function __construct(
        public string $hash,
        public string $observedAt,
        public array $contributions,
        public int $userCount,
        public int $payCodeCount,
        public int $artifactCount,
        public int $artifactBytes,
        public int $omissionCount,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'schema' => 'x-change.instance-keepsake-plan.v1',
            'status' => $this->omissionCount === 0 ? 'ready' : 'review_required',
            'plan_hash' => $this->hash,
            'observed_at' => $this->observedAt,
            'users' => $this->userCount,
            'pay_codes' => $this->payCodeCount,
            'artifacts' => $this->artifactCount,
            'artifact_bytes' => $this->artifactBytes,
            'omissions' => $this->omissionCount,
            'read_only' => true,
            'provider_calls' => false,
            'moves_money' => false,
            'restores_financial_state' => false,
        ];
    }
}
