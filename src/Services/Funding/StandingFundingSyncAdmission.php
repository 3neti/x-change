<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\XChange\Data\Funding\StandingFundingAdmissionData;
use LBHurtado\XChange\Enums\StandingFundingAddressSyncStatus;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Enums\StandingFundingSyncRunStatus;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;
use LBHurtado\XChange\Models\StandingFundingSyncRun;

final class StandingFundingSyncAdmission
{
    public function admit(StandingFundingAddress $address, string $trigger, ?int $webhookReceiptId = null): StandingFundingAdmissionData
    {
        return DB::transaction(function () use ($address, $trigger, $webhookReceiptId): StandingFundingAdmissionData {
            $control = StandingFundingRuntimeControl::query()->where('provider_code', $address->provider_code)->lockForUpdate()->first();

            if (! $control instanceof StandingFundingRuntimeControl || ! $control->mode->admitsNewWork()) {
                return new StandingFundingAdmissionData(false, 'runtime_disabled');
            }

            if ($control->mode === StandingFundingRuntimeMode::Canary && $control->canary_address_id !== $address->getKey()) {
                return new StandingFundingAdmissionData(false, 'not_canary_address');
            }

            $backlog = StandingFundingSyncRun::query()->where('provider_code', $address->provider_code)->whereIn('status', [StandingFundingSyncRunStatus::Queued, StandingFundingSyncRunStatus::Running])->count();
            if ($backlog >= min($control->batch_limit, $control->backlog_ceiling)) {
                return new StandingFundingAdmissionData(false, 'backlog_ceiling');
            }

            $state = StandingFundingAddressState::query()->where('standing_funding_address_id', $address->getKey())->lockForUpdate()->first()
                ?? StandingFundingAddressState::query()->create([
                    'standing_funding_address_id' => $address->getKey(),
                    'provider_code' => $address->provider_code,
                    'status' => StandingFundingAddressSyncStatus::Idle,
                    'generation' => $control->generation,
                ]);

            if (in_array($state->status, [StandingFundingAddressSyncStatus::Quarantined, StandingFundingAddressSyncStatus::Ambiguous], true)) {
                return new StandingFundingAdmissionData(false, $state->status->value);
            }

            if ($state->next_eligible_at?->isFuture()) {
                return new StandingFundingAdmissionData(false, 'cooldown');
            }

            if (in_array($state->status, [StandingFundingAddressSyncStatus::Queued, StandingFundingAddressSyncStatus::Running], true)
                && $state->lease_expires_at?->isFuture()) {
                return new StandingFundingAdmissionData(false, 'lease_active');
            }

            $leaseToken = (string) Str::ulid();
            $runReference = (string) Str::ulid();
            $leaseSeconds = max(30, (int) config('x-change.funding.standing_addresses.runtime.lease_seconds', 180));
            $state->forceFill([
                'status' => StandingFundingAddressSyncStatus::Queued,
                'generation' => $control->generation,
                'lease_token' => $leaseToken,
                'lease_expires_at' => now()->addSeconds($leaseSeconds),
            ])->save();
            StandingFundingSyncRun::query()->create([
                'reference' => $runReference,
                'standing_funding_address_id' => $address->getKey(),
                'provider_code' => $address->provider_code,
                'generation' => $control->generation,
                'lease_token' => $leaseToken,
                'trigger' => strtolower(trim($trigger)),
                'webhook_receipt_id' => $webhookReceiptId,
                'status' => StandingFundingSyncRunStatus::Queued,
            ]);

            return new StandingFundingAdmissionData(true, 'admitted', $runReference, $leaseToken, $control->generation);
        });
    }
}
