<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingSyncRun;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeManager;

final class StandingFundingRuntimeStatusCommand extends Command
{
    protected $signature = 'xchange:funding:standing-runtime:status {--provider=netbank}';

    protected $description = 'Show the sanitized Standing Funding runtime posture';

    public function handle(StandingFundingRuntimeManager $runtime): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));
        $facts = $runtime->read($provider);
        $facts['active_runs'] = StandingFundingSyncRun::query()->where('provider_code', $provider)->whereIn('status', ['queued', 'running'])->count();
        $facts['quarantined_addresses'] = StandingFundingAddressState::query()->where('provider_code', $provider)->where('status', 'quarantined')->count();
        $facts['ambiguous_addresses'] = StandingFundingAddressState::query()->where('provider_code', $provider)->where('status', 'ambiguous')->count();
        $this->table(['Fact', 'Value'], collect($facts)->map(fn ($value, $key) => [$key, $value ?? 'none'])->values()->all());

        return self::SUCCESS;
    }
}
