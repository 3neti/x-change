<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\XChange\Actions\Funding\TransitionFundingIntent;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\FundingIntent;

class VerifyOpenFundingIntentsCommand extends Command
{
    protected $signature = 'xchange:funding:verify-open
        {--provider=netbank : Funding provider code}
        {--limit= : Maximum intents to inspect in this run}';

    protected $description = 'Queue authoritative provider verification for open Funding Intents';

    public function handle(TransitionFundingIntent $transition): int
    {
        $providerCode = trim((string) $this->option('provider'));

        if ($providerCode === '' || ! config()->has("x-change.funding.providers.{$providerCode}")) {
            $this->components->error('The requested funding provider is not configured.');

            return self::INVALID;
        }

        if (! (bool) config("x-change.funding.providers.{$providerCode}.enabled", false)) {
            $this->components->info("Funding provider [{$providerCode}] is disabled; no checks were queued.");

            return self::SUCCESS;
        }

        $configuredLimit = max(
            1,
            (int) config('x-change.funding.scheduled_verification_batch_size', 100),
        );
        $requestedLimit = $this->option('limit');
        $limit = $requestedLimit === null
            ? $configuredLimit
            : min($configuredLimit, max(1, (int) $requestedLimit));
        $graceSeconds = max(
            0,
            (int) config('x-change.funding.settlement_grace_seconds', 300),
        );
        $queued = 0;
        $expired = 0;

        $this->eligibleIntents($providerCode, $limit)
            ->each(function (FundingIntent $intent) use (
                $graceSeconds,
                $providerCode,
                $transition,
                &$queued,
                &$expired,
            ): void {
                if ($this->canExpireAfterConclusiveCheck($intent, $graceSeconds)) {
                    $transition->handle($intent, new FundingIntentTransitionData(
                        status: FundingIntentStatus::Expired,
                        eventType: 'funding_intent_expired_after_settlement_grace',
                        actorType: 'system',
                        actorId: 'funding-scheduler',
                        expectedVersion: $intent->version,
                        metadata: [
                            'trigger' => FundingVerificationTrigger::Schedule->value,
                            'provider_code' => $providerCode,
                            'settlement_grace_seconds' => $graceSeconds,
                        ],
                    ));
                    $expired++;

                    return;
                }

                VerifyFundingIntentJob::dispatch(
                    fundingIntentId: (int) $intent->getKey(),
                    providerCode: $providerCode,
                    trigger: FundingVerificationTrigger::Schedule,
                    actorId: 'funding-scheduler',
                );
                $queued++;
            });

        $this->components->info("Queued {$queued} verification check(s); expired {$expired} intent(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<FundingIntent>
     */
    private function eligibleIntents(string $providerCode, int $limit): Builder
    {
        return FundingIntent::query()
            ->where('provider_code', $providerCode)
            ->whereNotNull('expires_at')
            ->whereIn('status', [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
                FundingIntentStatus::Verified,
            ])
            ->oldest('expires_at')
            ->oldest('id')
            ->limit($limit);
    }

    private function canExpireAfterConclusiveCheck(
        FundingIntent $intent,
        int $graceSeconds,
    ): bool {
        if ($intent->status === FundingIntentStatus::Verified) {
            return false;
        }

        $graceBoundary = $intent->expires_at?->addSeconds($graceSeconds);

        if ($graceBoundary === null || $graceBoundary->isFuture()) {
            return false;
        }

        return $intent->events()
            ->where('event_type', 'provider_funds_not_observed')
            ->where('occurred_at', '>=', $graceBoundary)
            ->exists();
    }
}
