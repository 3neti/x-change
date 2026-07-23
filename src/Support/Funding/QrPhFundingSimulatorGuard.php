<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Funding;

use Illuminate\Contracts\Foundation\Application;
use LogicException;

class QrPhFundingSimulatorGuard
{
    public function __construct(
        private readonly Application $application,
    ) {}

    public function available(): bool
    {
        return ! $this->application->isProduction()
            && $this->application->environment(
                (array) config('x-change.funding.simulator.allowed_environments', ['local', 'testing']),
            )
            && (bool) config('x-change.funding.simulator.enabled', false)
            && (bool) config('x-change.funding.providers.qrph_simulator.enabled', false);
    }

    public function assertAvailable(): void
    {
        if (! $this->available()) {
            throw new LogicException('QR Ph funding simulation is unavailable in this environment.');
        }
    }
}
