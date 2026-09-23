<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

final readonly class CampaignPolicyLifecycleData
{
    public const string SCHEMA = 'x-change.campaign-policy-lifecycle.v1';

    public function __construct(
        public string $stage,
        public bool $attentionRequired,
        public array $campaign,
        public array $payment,
        public array $coverage,
        public ?array $completion,
        public ?array $policy,
        public ?string $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toSafeArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'stage' => $this->stage,
            'attention_required' => $this->attentionRequired,
            'campaign' => $this->campaign,
            'payment' => $this->payment,
            'coverage' => $this->coverage,
            'completion' => $this->completion,
            'policy' => $this->policy,
            'updated_at' => $this->updatedAt,
        ];
    }
}
