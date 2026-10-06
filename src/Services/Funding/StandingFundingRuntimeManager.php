<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;

final readonly class StandingFundingRuntimeManager
{
    public function __construct(private StandingFundingRuntimeEventRecorder $events) {}

    public function control(string $providerCode): StandingFundingRuntimeControl
    {
        return StandingFundingRuntimeControl::query()->firstOrCreate(
            ['provider_code' => strtolower(trim($providerCode))],
            [
                'mode' => StandingFundingRuntimeMode::Disabled,
                'generation' => 1,
                'batch_limit' => 1,
                'backlog_ceiling' => max(1, (int) config('x-change.funding.standing_addresses.runtime.backlog_ceiling', 25)),
                'last_transition' => 'runtime_initialized',
                'transitioned_at' => now(),
            ],
        );
    }

    public function transition(string $providerCode, StandingFundingRuntimeMode $mode, string $reason, string $actorType, string $actorId, ?int $canaryAddressId = null, ?int $expectedGeneration = null): StandingFundingRuntimeControl
    {
        return DB::transaction(function () use ($providerCode, $mode, $reason, $actorType, $actorId, $canaryAddressId, $expectedGeneration): StandingFundingRuntimeControl {
            $control = StandingFundingRuntimeControl::query()->where('provider_code', strtolower(trim($providerCode)))->lockForUpdate()->first()
                ?? StandingFundingRuntimeControl::query()->create([
                    'provider_code' => strtolower(trim($providerCode)),
                    'mode' => StandingFundingRuntimeMode::Disabled,
                    'transitioned_at' => now(),
                ]);

            if ($expectedGeneration !== null && $control->generation !== $expectedGeneration) {
                throw new \LogicException('Standing Funding runtime generation is stale.');
            }

            $previous = $control->mode;
            $control->forceFill([
                'mode' => $mode,
                'generation' => $control->generation + 1,
                'canary_address_id' => $mode === StandingFundingRuntimeMode::Canary ? $canaryAddressId : null,
                'batch_limit' => $mode === StandingFundingRuntimeMode::Canary ? 1 : $control->batch_limit,
                'last_transition' => $reason,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'transitioned_at' => now(),
                'circuit_open_until' => $mode === StandingFundingRuntimeMode::CircuitOpen ? $control->circuit_open_until : null,
            ])->save();

            $this->events->record('standing_funding.runtime.mode_changed', 'standing_funding_runtime', $control->provider_code, $control->generation, [
                'provider_code' => $control->provider_code,
                'mode' => $mode->value,
                'previous_mode' => $previous->value,
                'reason' => $reason,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'occurred_at' => now()->toIso8601String(),
            ]);

            return $control->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function read(string $providerCode): array
    {
        $provider = strtolower(trim($providerCode));
        $control = StandingFundingRuntimeControl::query()->where('provider_code', $provider)->first();

        if (! $control instanceof StandingFundingRuntimeControl) {
            return [
                'provider' => $provider,
                'mode' => StandingFundingRuntimeMode::Disabled->value,
                'generation' => null,
                'batch_limit' => 0,
                'backlog_ceiling' => 0,
                'canary_address_id' => null,
                'circuit_open_until' => null,
                'last_transition' => 'runtime_state_absent',
            ];
        }

        return [
            'provider' => $control->provider_code,
            'mode' => $control->mode->value,
            'generation' => $control->generation,
            'batch_limit' => $control->batch_limit,
            'backlog_ceiling' => $control->backlog_ceiling,
            'canary_address_id' => $control->canary_address_id,
            'circuit_open_until' => $control->circuit_open_until?->toIso8601String(),
            'last_transition' => $control->last_transition,
        ];
    }
}
