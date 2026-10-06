<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\StandingFundingAddressSyncStatus;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;

final readonly class StandingFundingAddressRecovery
{
    public function __construct(private StandingFundingRuntimeEventRecorder $events) {}

    public function apply(int $addressId, string $action, int $expectedGeneration, string $actorId, string $reason): StandingFundingAddressState
    {
        return DB::transaction(function () use ($addressId, $action, $expectedGeneration, $actorId, $reason): StandingFundingAddressState {
            $state = StandingFundingAddressState::query()->where('standing_funding_address_id', $addressId)->lockForUpdate()->firstOrFail();
            $control = StandingFundingRuntimeControl::query()->where('provider_code', $state->provider_code)->lockForUpdate()->firstOrFail();
            if ($control->generation !== $expectedGeneration) {
                throw new \LogicException('Standing Funding runtime generation is stale.');
            }

            $eventType = match ($action) {
                'quarantine' => 'standing_funding.address.quarantined',
                'release-stale-lease' => 'standing_funding.address.released',
                'reconcile-ambiguous' => 'standing_funding.sync.reconciled',
                'retry' => 'standing_funding.recovery.command_executed',
                default => throw new \InvalidArgumentException('Unsupported recovery action.'),
            };
            if ($action === 'release-stale-lease' && $state->lease_expires_at?->isFuture()) {
                throw new \LogicException('The lease is not stale.');
            }
            if ($action === 'reconcile-ambiguous' && $state->status !== StandingFundingAddressSyncStatus::Ambiguous) {
                throw new \LogicException('The address is not awaiting ambiguous-outcome reconciliation.');
            }
            $state->forceFill(match ($action) {
                'quarantine' => ['status' => StandingFundingAddressSyncStatus::Quarantined, 'quarantined_at' => now(), 'quarantine_reason' => $reason, 'lease_token' => null, 'lease_expires_at' => null],
                default => ['status' => StandingFundingAddressSyncStatus::Idle, 'lease_token' => null, 'lease_expires_at' => null, 'next_eligible_at' => null, 'ambiguous_at' => null, 'quarantined_at' => null, 'quarantine_reason' => null],
            })->save();
            $this->events->record($eventType, 'standing_funding_address', $addressId, $control->generation, [
                'provider_code' => $state->provider_code,
                'status' => $state->status->value,
                'reason' => $reason,
                'actor_type' => 'operator',
                'actor_id' => $actorId,
                'occurred_at' => now()->toIso8601String(),
            ]);

            return $state->refresh();
        });
    }
}
