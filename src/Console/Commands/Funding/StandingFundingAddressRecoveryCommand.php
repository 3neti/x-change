<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressRecovery;

final class StandingFundingAddressRecoveryCommand extends Command
{
    protected $signature = 'xchange:funding:standing-runtime:recover-address
        {action : quarantine, release-stale-lease, reconcile-ambiguous, or retry}
        {address : Standing Funding Address ID}
        {--generation= : Required current generation}
        {--reason=operator_recovery}
        {--actor=operator}
        {--apply : Apply the previewed recovery}';

    protected $description = 'Preview or apply a scoped Standing Funding Address recovery operation';

    public function handle(StandingFundingAddressRecovery $recovery): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        $addressId = filter_var($this->argument('address'), FILTER_VALIDATE_INT);
        $generation = filter_var($this->option('generation'), FILTER_VALIDATE_INT);
        if (! in_array($action, ['quarantine', 'release-stale-lease', 'reconcile-ambiguous', 'retry'], true)
            || $addressId === false || $generation === false) {
            $this->components->error('A supported action, address, and --generation are required.');

            return self::INVALID;
        }
        $this->line(json_encode(['action' => $action, 'address_id' => $addressId, 'expected_generation' => $generation, 'apply' => (bool) $this->option('apply')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->components->warn('Preview only. Re-run with --apply to mutate runtime state.');

            return self::SUCCESS;
        }
        $state = $recovery->apply($addressId, $action, $generation, (string) $this->option('actor'), (string) $this->option('reason'));
        $this->components->info("Address {$addressId} is {$state->status->value}.");

        return self::SUCCESS;
    }
}
