<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Campaigns;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Enums\CampaignPaymentMonitoringMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Campaigns\CampaignPaymentMonitoringEligibility;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncAdmission;

final class SyncCampaignPaymentAddressesCommand extends Command
{
    protected $signature = 'xchange:campaigns:sync-payment-addresses
        {--provider=netbank : Funding provider code}
        {--limit= : Maximum campaign payment addresses to inspect}';

    protected $description = 'Queue synchronization only for explicitly monitored reusable campaign payment addresses';

    public function handle(
        StandingFundingSyncAdmission $admission,
        CampaignPaymentMonitoringEligibility $eligibility,
    ): int {
        $provider = strtolower(trim((string) $this->option('provider')));

        if ($provider === '' || ! config()->has("x-change.funding.providers.{$provider}")) {
            $this->components->error('The requested funding provider is not configured.');

            return self::INVALID;
        }

        if (! (bool) config('x-change.campaigns.payment_monitoring.scheduled_sync_enabled', false)
            || ! (bool) config('x-change.funding.standing_addresses.enabled', false)
            || ! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            $this->components->info("Campaign payment synchronization for [{$provider}] is disabled.");

            return self::SUCCESS;
        }

        $configuredLimit = max(1, (int) config('x-change.campaigns.payment_monitoring.scheduled_batch_size', 1));
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $minimumIntervalSeconds = max(
            1,
            (int) config('x-change.campaigns.payment_monitoring.scheduled_minimum_interval_seconds', 60),
        );
        $dueBefore = now()->subSeconds($minimumIntervalSeconds);
        $queued = 0;

        StandingFundingAddress::query()
            ->with(['campaignPaymentQrBinding.campaign', 'campaignPaymentQrBinding.monitoringControl'])
            ->where('provider_code', $provider)
            ->where('purpose', FundingAddressPurpose::Payment)
            ->where('status', FundingAddressStatus::Active)
            ->where(function (Builder $query) use ($dueBefore): void {
                $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $dueBefore);
            })
            ->whereHas('campaignPaymentQrBinding.monitoringControl', function (Builder $query): void {
                $query->where('mode', CampaignPaymentMonitoringMode::Live);
            })
            ->whereHas('campaignPaymentQrBinding.campaign', function (Builder $query): void {
                $query->where('status', 'active');
            })
            ->oldest('last_checked_at')
            ->oldest('id')
            ->limit($limit)
            ->each(function (StandingFundingAddress $address) use ($admission, $eligibility, $provider, &$queued): void {
                $binding = $address->campaignPaymentQrBinding;

                if ($binding === null || ! $eligibility->isEligible($binding)) {
                    return;
                }

                $decision = $admission->admit($address, 'campaign_schedule');

                if (! $decision->admitted) {
                    return;
                }

                SyncStandingFundingAddressJob::dispatch(
                    standingFundingAddressId: (int) $address->getKey(),
                    providerCode: $provider,
                    trigger: 'campaign_schedule',
                    runtimeGeneration: $decision->generation,
                    runReference: $decision->runReference,
                    leaseToken: $decision->leaseToken,
                );
                $queued++;
            });

        $this->components->info("Queued {$queued} campaign payment synchronization check(s).");

        return self::SUCCESS;
    }
}
