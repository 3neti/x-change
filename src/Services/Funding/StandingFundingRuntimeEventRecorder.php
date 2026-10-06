<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Str;
use LBHurtado\XChange\Models\StandingFundingRuntimeOutbox;

final class StandingFundingRuntimeEventRecorder
{
    /** @param array<string, scalar|null> $payload */
    public function record(string $eventType, string $aggregateType, string|int $aggregateId, ?int $generation, array $payload = []): StandingFundingRuntimeOutbox
    {
        $safePayload = array_intersect_key($payload, array_flip([
            'provider_code', 'mode', 'previous_mode', 'reason', 'status',
            'failure_classification', 'run_reference', 'address_reference',
            'actor_type', 'actor_id', 'occurred_at',
        ]));

        return StandingFundingRuntimeOutbox::query()->firstOrCreate([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'generation' => $generation,
        ], [
            'reference' => (string) Str::ulid(),
            'payload' => $safePayload,
            'occurred_at' => now(),
        ]);
    }
}
