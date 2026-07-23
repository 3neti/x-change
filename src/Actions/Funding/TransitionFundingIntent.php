<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Exceptions\FundingIntentTransitionDenied;
use LBHurtado\XChange\Models\FundingIntent;

class TransitionFundingIntent
{
    public function handle(FundingIntent $intent, FundingIntentTransitionData $transition): FundingIntent
    {
        $eventType = trim($transition->eventType);
        $actorType = trim($transition->actorType);
        $actorId = trim($transition->actorId);

        if ($eventType === '' || $actorType === '' || $actorId === '') {
            throw new InvalidArgumentException('Event type and actor identity are required.');
        }

        return DB::transaction(function () use ($intent, $transition, $eventType, $actorType, $actorId): FundingIntent {
            $locked = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if ($transition->expectedVersion !== null && $locked->version !== $transition->expectedVersion) {
                throw FundingIntentConflict::version($transition->expectedVersion, $locked->version);
            }

            $currentStatus = $locked->status;

            if (! $currentStatus->canTransitionTo($transition->status)) {
                throw FundingIntentTransitionDenied::from($currentStatus, $transition->status);
            }

            if (in_array($transition->status, [
                FundingIntentStatus::Verified,
                FundingIntentStatus::Settled,
                FundingIntentStatus::Reversed,
            ], true)
                && ($transition->providerObservationId === null
                    || $transition->providerObservationId <= 0
                    || trim((string) $transition->providerTransactionId) === ''
                    || trim((string) $transition->evidenceReference) === '')) {
                throw new InvalidArgumentException(
                    'Verified, settled, and reversed Funding Intent transitions require authoritative provider evidence.',
                );
            }

            $nextVersion = $locked->version + 1;
            $now = now();
            $timestamps = match ($transition->status) {
                FundingIntentStatus::AwaitingFunds => ['instructions_created_at' => $locked->instructions_created_at ?? $now],
                FundingIntentStatus::EvidenceReceived => ['evidence_received_at' => $now],
                FundingIntentStatus::Verified => ['verified_at' => $now],
                FundingIntentStatus::Settled => ['settled_at' => $now],
                FundingIntentStatus::Cancelled => ['cancelled_at' => $now],
                FundingIntentStatus::Expired => ['expired_at' => $now],
                FundingIntentStatus::Reversed => ['reversed_at' => $now],
                default => [],
            };

            $locked->forceFill([
                'status' => $transition->status,
                'version' => $nextVersion,
                'matched_observation_id' => $transition->providerObservationId
                    ?? $locked->matched_observation_id,
                'provider_transaction_id' => $transition->providerTransactionId
                    ?? $locked->provider_transaction_id,
                ...$timestamps,
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => $eventType,
                'from_status' => $currentStatus,
                'to_status' => $transition->status,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'evidence_reference' => $transition->evidenceReference,
                'metadata' => $transition->metadata,
                'occurred_at' => $now,
            ]);

            return $locked->refresh()->load('events');
        }, 3);
    }
}
