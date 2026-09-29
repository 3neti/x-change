<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Funding;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use LogicException;

class QrPhFundingSimulatorGuard
{
    private int $rollbackLifecycleScopeDepth = 0;

    public function __construct(
        private readonly Application $application,
    ) {}

    public function available(): bool
    {
        return ! $this->application->isProduction()
            && ($this->rollbackLifecycleScopeDepth > 0 || $this->application->environment(
                (array) config('x-change.funding.simulator.allowed_environments', ['local', 'testing']),
            ))
            && (bool) config('x-change.funding.simulator.enabled', false)
            && (bool) config('x-change.funding.providers.qrph_simulator.enabled', false);
    }

    public function withinRollbackLifecycle(Closure $callback): mixed
    {
        $this->rollbackLifecycleScopeDepth++;

        try {
            return $callback();
        } finally {
            $this->rollbackLifecycleScopeDepth--;
        }
    }

    public function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new LogicException('QR Ph funding simulation is unavailable in this environment.');
        }
    }
}
