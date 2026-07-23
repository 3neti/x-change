<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class RequestFundingReconciliation
{
    public function handle(
        FundingSuspenseCase $case,
        FundingReconciliationAction $action,
        string $actorType,
        string $actorId,
        ?int $providerObservationId = null,
    ): FundingReconciliationRequest {
        $actorType = trim($actorType);
        $actorId = trim($actorId);

        if ($actorType === '' || $actorId === '') {
            throw new InvalidArgumentException('A reconciliation requester identity is required.');
        }

        return DB::transaction(function () use (
            $case,
            $action,
            $actorType,
            $actorId,
            $providerObservationId,
        ): FundingReconciliationRequest {
            $lockedCase = FundingSuspenseCase::query()->lockForUpdate()->findOrFail($case->getKey());

            if (! in_array($lockedCase->status, ['open', 'monitoring'], true)) {
                throw new InvalidArgumentException('The funding suspense case is not open for reconciliation.');
            }

            $observationId = $providerObservationId ?? $lockedCase->provider_funding_observation_id;

            if ($action === FundingReconciliationAction::MatchVerifiedObservation && $observationId === null) {
                throw new InvalidArgumentException('Matching requires an immutable provider observation.');
            }

            $payload = array_filter([
                'provider_observation_id' => $observationId,
            ], fn (mixed $value): bool => $value !== null);
            $requestKey = hash('sha256', json_encode([
                'case' => $lockedCase->getKey(),
                'case_updated_at' => $lockedCase->updated_at?->toJSON(),
                'action' => $action->value,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));

            return FundingReconciliationRequest::query()->firstOrCreate(
                ['request_key' => $requestKey],
                [
                    'funding_suspense_case_id' => $lockedCase->getKey(),
                    'action' => $action,
                    'status' => 'pending_approval',
                    'payload' => $payload,
                    'requested_by_type' => $actorType,
                    'requested_by_id' => $actorId,
                    'requested_at' => now(),
                ],
            );
        }, attempts: 3);
    }
}
