<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding\Concerns;

use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeManager;

trait TransitionsStandingFundingRuntime
{
    abstract protected function targetMode(): StandingFundingRuntimeMode;

    public function handle(StandingFundingRuntimeManager $runtime): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));
        $generation = filter_var($this->option('generation'), FILTER_VALIDATE_INT);
        $addressId = filter_var($this->option('address'), FILTER_VALIDATE_INT) ?: null;
        if ($generation === false || $generation < 1) {
            $this->components->error('A valid --generation is required.');

            return self::INVALID;
        }
        if ($this->targetMode() === StandingFundingRuntimeMode::Canary && $addressId === null) {
            $this->components->error('Canary mode requires --address.');

            return self::INVALID;
        }
        $this->line(json_encode([
            'provider' => $provider,
            'mode' => $this->targetMode()->value,
            'expected_generation' => $generation,
            'canary_address_id' => $addressId,
            'apply' => (bool) $this->option('apply'),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->components->warn('Preview only. Re-run with --apply to mutate runtime state.');

            return self::SUCCESS;
        }
        $control = $runtime->transition(
            $provider,
            $this->targetMode(),
            (string) $this->option('reason'),
            'operator',
            (string) $this->option('actor'),
            $addressId,
            $generation,
        );
        $this->components->info("Runtime is {$control->mode->value} at generation {$control->generation}.");

        return self::SUCCESS;
    }
}
