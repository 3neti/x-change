<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

final readonly class ScenarioRunContext
{
    public function __construct(
        public Command $command,
        public string $scenarioKey,
        public array $scenario,
        public Model $issuer,
        public mixed $generated,
        public mixed $voucher,
        public array $attempts,
        public string $baseClaimMobile,
        public array $estimate,
        public string $idempotencyKey,
        public ?SettlementEnvelopeReadinessContract $readiness = null,
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
        return $this->command->option('only-attempt');
    }

    public function wantsJson(): bool
    {
        return (bool) $this->command->option('json');
    }
}
