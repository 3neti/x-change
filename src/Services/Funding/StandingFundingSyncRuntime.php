<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Enums\StandingFundingAddressSyncStatus;
use LBHurtado\XChange\Enums\StandingFundingFailureClassification;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Enums\StandingFundingSyncRunStatus;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;
use LBHurtado\XChange\Models\StandingFundingSyncRun;
use Throwable;

final readonly class StandingFundingSyncRuntime
{
    public function __construct(
        private StandingFundingFailureClassifier $classifier,
        private StandingFundingRuntimeEventRecorder $events,
    ) {}

    public function start(string $runReference, string $leaseToken, int $generation): bool
    {
        return DB::transaction(function () use ($runReference, $leaseToken, $generation): bool {
            $run = StandingFundingSyncRun::query()->where('reference', $runReference)->lockForUpdate()->first();
            if (! $run instanceof StandingFundingSyncRun) {
                return false;
            }
            if ($run->status !== StandingFundingSyncRunStatus::Queued) {
                return false;
            }
            $control = StandingFundingRuntimeControl::query()->where('provider_code', $run->provider_code)->lockForUpdate()->first();
            $state = StandingFundingAddressState::query()->where('standing_funding_address_id', $run->standing_funding_address_id)->lockForUpdate()->first();
            $admittedGeneration = $control?->mode === StandingFundingRuntimeMode::Draining
                ? $control->generation - 1
                : $control?->generation;
            if (! $control instanceof StandingFundingRuntimeControl || ! $state instanceof StandingFundingAddressState
                || ! $control->mode->allowsAdmittedWorkToStart() || $admittedGeneration !== $generation
                || $run->generation !== $generation
                || $state->generation !== $generation || $state->lease_token !== $leaseToken
                || $state->lease_expires_at?->isPast()) {
                $run->forceFill(['status' => StandingFundingSyncRunStatus::Stale, 'finished_at' => now()])->save();

                return false;
            }
            $run->forceFill(['status' => StandingFundingSyncRunStatus::Running, 'started_at' => now()])->save();
            $state->forceFill(['status' => StandingFundingAddressSyncStatus::Running])->save();

            return true;
        });
    }

    /** @param array<string, mixed> $summary */
    public function succeed(string $runReference, array $summary = []): void
    {
        DB::transaction(function () use ($runReference, $summary): void {
            $run = StandingFundingSyncRun::query()->where('reference', $runReference)->lockForUpdate()->firstOrFail();
            $state = StandingFundingAddressState::query()->where('standing_funding_address_id', $run->standing_funding_address_id)->lockForUpdate()->firstOrFail();
            $run->forceFill(['status' => StandingFundingSyncRunStatus::Succeeded, 'summary' => $summary, 'finished_at' => now()])->save();
            $state->forceFill([
                'status' => StandingFundingAddressSyncStatus::Idle,
                'lease_token' => null,
                'lease_expires_at' => null,
                'next_eligible_at' => now()->addSeconds(max(1, (int) config('x-change.funding.standing_addresses.scheduled_minimum_interval_seconds', 60))),
                'consecutive_failures' => 0,
                'last_failure_classification' => null,
                'last_failure_type' => null,
            ])->save();
        });
    }

    public function fail(string $runReference, Throwable $failure, bool $providerCallStarted = true): StandingFundingFailureClassification
    {
        $classification = $this->classifier->classify($failure, $providerCallStarted);
        try {
            DB::transaction(function () use ($runReference, $failure, $classification): void {
                $run = StandingFundingSyncRun::query()->where('reference', $runReference)->lockForUpdate()->firstOrFail();
                $state = StandingFundingAddressState::query()->where('standing_funding_address_id', $run->standing_funding_address_id)->lockForUpdate()->firstOrFail();
                $control = StandingFundingRuntimeControl::query()->where('provider_code', $run->provider_code)->lockForUpdate()->firstOrFail();
                $failures = $state->consecutive_failures + 1;
                $ambiguous = $classification === StandingFundingFailureClassification::AmbiguousAfterProviderCall;
                $permanent = $classification === StandingFundingFailureClassification::ConfigurationPermanent;
                $resource = $classification === StandingFundingFailureClassification::DatabaseResourceExhausted;
                $state->forceFill([
                    'status' => $ambiguous ? StandingFundingAddressSyncStatus::Ambiguous : ($permanent ? StandingFundingAddressSyncStatus::Quarantined : StandingFundingAddressSyncStatus::Cooldown),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'next_eligible_at' => ($ambiguous || $permanent) ? null : now()->addSeconds(min(3600, 30 * (2 ** min(6, $failures - 1)))),
                    'consecutive_failures' => $failures,
                    'last_failure_classification' => $classification,
                    'last_failure_type' => class_basename($failure),
                    'ambiguous_at' => $ambiguous ? now() : null,
                    'quarantined_at' => $permanent ? now() : null,
                    'quarantine_reason' => $permanent ? $classification->value : null,
                ])->save();
                $run->forceFill([
                    'status' => $ambiguous ? StandingFundingSyncRunStatus::Ambiguous : StandingFundingSyncRunStatus::Failed,
                    'failure_classification' => $classification,
                    'failure_type' => class_basename($failure),
                    'finished_at' => now(),
                ])->save();
                $control->increment('consecutive_failures');

                $threshold = max(1, (int) config('x-change.funding.standing_addresses.runtime.circuit_failure_threshold', 3));
                if ($resource || $control->consecutive_failures >= $threshold) {
                    $control->forceFill([
                        'mode' => StandingFundingRuntimeMode::CircuitOpen,
                        'generation' => $control->generation + 1,
                        'circuit_open_until' => now()->addSeconds(max(60, (int) config('x-change.funding.standing_addresses.runtime.circuit_cooldown_seconds', 900))),
                        'last_transition' => $classification->value,
                        'transitioned_at' => now(),
                    ])->save();
                    $this->events->record('standing_funding.runtime.circuit_opened', 'standing_funding_runtime', $control->provider_code, $control->generation, [
                        'provider_code' => $control->provider_code,
                        'mode' => StandingFundingRuntimeMode::CircuitOpen->value,
                        'failure_classification' => $classification->value,
                        'run_reference' => $run->reference,
                        'occurred_at' => now()->toIso8601String(),
                    ]);
                }

                $eventType = $ambiguous ? 'standing_funding.sync.ambiguous_detected' : ($resource ? 'standing_funding.sync.resource_exhausted' : 'standing_funding.sync.terminal_failure');
                $this->events->record($eventType, 'standing_funding_sync_run', $run->reference, $run->generation, [
                    'provider_code' => $run->provider_code,
                    'status' => $run->status->value,
                    'failure_classification' => $classification->value,
                    'run_reference' => $run->reference,
                    'occurred_at' => now()->toIso8601String(),
                ]);
            });
        } catch (Throwable $persistenceFailure) {
            try {
                Log::critical('Standing Funding runtime failure evidence could not be persisted.', [
                    'run_reference_hash' => hash('sha256', $runReference),
                    'failure_classification' => $classification->value,
                    'persistence_failure_type' => class_basename($persistenceFailure),
                ]);
            } catch (Throwable) {
            }
        }

        return $classification;
    }
}
