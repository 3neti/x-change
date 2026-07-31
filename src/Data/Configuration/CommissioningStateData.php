<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

use LBHurtado\XChange\Enums\CommissioningState;

final readonly class CommissioningStateData
{
    /**
     * @param  list<string>  $missingVariables
     */
    public function __construct(
        public CommissioningState $state,
        public string $profile,
        public array $missingVariables = [],
        public ?string $reason = null,
    ) {}

    public function isOperational(): bool
    {
        return $this->state === CommissioningState::Operational;
    }
}
