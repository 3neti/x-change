<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum StandingFundingRuntimeMode: string
{
    case Disabled = 'disabled';
    case Canary = 'canary';
    case Bounded = 'bounded';
    case Scheduled = 'scheduled';
    case Draining = 'draining';
    case Paused = 'paused';
    case CircuitOpen = 'circuit_open';

    public function admitsNewWork(): bool
    {
        return in_array($this, [self::Canary, self::Bounded, self::Scheduled], true);
    }

    public function allowsAdmittedWorkToStart(): bool
    {
        return $this->admitsNewWork() || $this === self::Draining;
    }
}
