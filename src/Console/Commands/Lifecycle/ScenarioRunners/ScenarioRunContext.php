<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleOutputContract;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

final readonly class ScenarioRunContext
{
    public function __construct(
        public LifecycleOutputContract $output,
        public string $scenarioKey,
        public array $scenario,
        public Model $issuer,
        public mixed $generated,
        public mixed $voucher,
        public array $attempts,
        public string $baseClaimMobile,
        public array $estimate,
        public string $idempotencyKey,
        public SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function mode(): ?string
    {
        return $this->scenario['mode'] ?? null;
    }

    public function label(): string
    {
        return $this->scenario['label'] ?? $this->scenarioKey;
    }

    public function selectedAttempt(): mixed
    {
        return data_get($this->scenario, '_runtime.selected_attempt');
    }

    public function wantsJson(): bool
    {
        return $this->output->isJson();
    }

    public function acceptPending(): bool
    {
        return $this->output->acceptPending();
    }
}
