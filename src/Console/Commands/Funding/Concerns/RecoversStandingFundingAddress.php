<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding\Concerns;

use LBHurtado\XChange\Services\Funding\StandingFundingAddressRecovery;

trait RecoversStandingFundingAddress
{
    abstract protected function recoveryAction(): string;

    public function handle(StandingFundingAddressRecovery $recovery): int
    {
        $addressId = filter_var($this->argument('address'), FILTER_VALIDATE_INT);
        $generation = filter_var($this->option('generation'), FILTER_VALIDATE_INT);
        if ($addressId === false || $generation === false) {
            $this->components->error('A valid address and --generation are required.');

            return self::INVALID;
        }
        $this->line(json_encode([
            'action' => $this->recoveryAction(),
            'address_id' => $addressId,
            'expected_generation' => $generation,
            'apply' => (bool) $this->option('apply'),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->components->warn('Preview only. Re-run with --apply to mutate runtime state.');

            return self::SUCCESS;
        }
        $state = $recovery->apply(
            $addressId,
            $this->recoveryAction(),
            $generation,
            (string) $this->option('actor'),
            (string) $this->option('reason'),
        );
        $this->components->info("Address {$addressId} is {$state->status->value}.");

        return self::SUCCESS;
    }
}
