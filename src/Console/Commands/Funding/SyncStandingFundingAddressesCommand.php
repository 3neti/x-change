<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class SyncStandingFundingAddressesCommand extends Command
{
    protected $signature = 'xchange:funding:sync-standing
        {--provider=netbank : Funding provider code}
        {--limit= : Maximum addresses to inspect in this run}';

    protected $description = 'Queue authoritative provider synchronization for active Standing Funding Addresses';

    public function handle(): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));

        if ($provider === '' || ! config()->has("x-change.funding.providers.{$provider}")) {
            $this->components->error('The requested funding provider is not configured.');

            return self::INVALID;
        }

        if (! (bool) config('x-change.funding.standing_addresses.enabled', false)
            || ! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            $this->components->info("Standing Funding Address synchronization for [{$provider}] is disabled.");

            return self::SUCCESS;
        }

        $configuredLimit = max(
            1,
            (int) config('x-change.funding.standing_addresses.scheduled_batch_size', 100),
        );
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $queued = 0;

        StandingFundingAddress::query()
            ->where('provider_code', $provider)
            ->where('status', FundingAddressStatus::Active)
            ->oldest('last_checked_at')
            ->oldest('id')
            ->limit($limit)
            ->each(function (StandingFundingAddress $address) use ($provider, &$queued): void {
                SyncStandingFundingAddressJob::dispatch(
                    standingFundingAddressId: (int) $address->getKey(),
                    providerCode: $provider,
                    trigger: 'schedule',
                );
                $queued++;
            });

        $this->components->info("Queued {$queued} Standing Funding Address synchronization check(s).");

        return self::SUCCESS;
    }
}
