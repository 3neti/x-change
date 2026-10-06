<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeManager;

final class StandingFundingRuntimeTransitionCommand extends Command
{
    protected $signature = 'xchange:funding:standing-runtime:transition
        {mode : paused, draining, canary, bounded, scheduled, or disabled}
        {--provider=netbank}
        {--generation= : Required current generation}
        {--address= : Required canary address ID}
        {--reason=operator_recovery}
        {--actor=operator}
        {--apply : Apply the previewed transition}';

    protected $description = 'Preview or apply a generation-fenced Standing Funding runtime transition';

    public function handle(StandingFundingRuntimeManager $runtime): int
    {
        $mode = StandingFundingRuntimeMode::tryFrom(strtolower(trim((string) $this->argument('mode'))));
        if (! $mode instanceof StandingFundingRuntimeMode || $mode === StandingFundingRuntimeMode::CircuitOpen) {
            $this->components->error('The requested runtime mode is not operator-selectable.');

            return self::INVALID;
        }
        $provider = strtolower(trim((string) $this->option('provider')));
        $generation = filter_var($this->option('generation'), FILTER_VALIDATE_INT);
        if ($generation === false || $generation < 1) {
            $this->components->error('A valid --generation is required.');

            return self::INVALID;
        }
        $addressId = filter_var($this->option('address'), FILTER_VALIDATE_INT) ?: null;
        if ($mode === StandingFundingRuntimeMode::Canary && $addressId === null) {
            $this->components->error('Canary mode requires --address.');

            return self::INVALID;
        }
        $this->line(json_encode(['provider' => $provider, 'mode' => $mode->value, 'expected_generation' => $generation, 'canary_address_id' => $addressId, 'apply' => (bool) $this->option('apply')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->components->warn('Preview only. Re-run with --apply to mutate runtime state.');

            return self::SUCCESS;
        }
        $control = $runtime->transition($provider, $mode, (string) $this->option('reason'), 'operator', (string) $this->option('actor'), $addressId, $generation);
        $this->components->info("Runtime is {$control->mode->value} at generation {$control->generation}.");

        return self::SUCCESS;
    }
}
