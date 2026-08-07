<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Data\Commercial\ProviderCostBatchEvidenceData;
use LBHurtado\XChange\Data\Commercial\ProviderCostEvidenceData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialProviderCostBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\CommercialProviderCostBatchLine;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;

final readonly class RecordProviderCostBatch
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private SettleCommercialProviderCost $settlements,
        private CommercialAccountingJournal $journal,
    ) {}

    /** @throws JsonException */
    public function execute(Model $operator, ProviderCostBatchEvidenceData $evidence): CommercialProviderCostBatch
    {
        $this->authorize($operator);
        $requestHash = hash(
            'sha256',
            json_encode($evidence->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return DB::transaction(function () use ($operator, $evidence, $requestHash): CommercialProviderCostBatch {
            $existing = CommercialProviderCostBatch::query()
                ->where('idempotency_key', $evidence->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialProviderCostBatch) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new CommercialSaleConflict('Provider cost batch key was reused with different evidence.');
                }

                return $existing;
            }

            $periodStart = Carbon::parse($evidence->periodStartedAt);
            $periodEnd = Carbon::parse($evidence->periodEndedAt);
            $allocations = CommercialAllocation::query()
                ->with('sale')
                ->where('category', 'provider_cost')
                ->where('status', 'posted')
                ->where('currency', mb_strtoupper($evidence->currency))
                ->whereDoesntHave(
                    'sale',
                    fn ($query) => $query->where('status', '!=', 'posted'),
                )
                ->whereHas(
                    'sale',
                    fn ($query) => $query->whereBetween('accepted_at', [$periodStart, $periodEnd]),
                )
                ->whereNotIn('id', CommercialProviderCostBatchLine::query()->select('commercial_allocation_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(function (CommercialAllocation $allocation) use ($evidence): bool {
                    $context = (array) data_get($allocation->sale->snapshot, 'accounting_context', []);

                    return data_get($context, 'provider') === mb_strtolower($evidence->provider)
                        && data_get($context, 'connection_reference') === $evidence->connectionReference;
                })
                ->values();
            $expectedAmountMinor = (int) $allocations->sum('amount_minor');
            $varianceAmountMinor = $evidence->observedAmountMinor - $expectedAmountMinor;
            $status = $allocations->isNotEmpty() && $varianceAmountMinor === 0
                ? CommercialProviderCostBatchStatus::Settled
                : CommercialProviderCostBatchStatus::ReviewRequired;

            $batch = CommercialProviderCostBatch::query()->create([
                'reference' => $evidence->reference,
                'provider' => mb_strtolower($evidence->provider),
                'connection_reference' => $evidence->connectionReference,
                'currency' => mb_strtoupper($evidence->currency),
                'evidence_type' => $evidence->evidenceType,
                'evidence_reference' => $evidence->evidenceReference,
                'expected_amount_minor' => $expectedAmountMinor,
                'observed_amount_minor' => $evidence->observedAmountMinor,
                'variance_amount_minor' => $varianceAmountMinor,
                'status' => $status,
                'idempotency_key' => $evidence->idempotencyKey,
                'request_hash' => $requestHash,
                'recorded_by_type' => $operator->getMorphClass(),
                'recorded_by_id' => $operator->getKey(),
                'metadata' => [
                    ...$evidence->metadata,
                    'candidate_allocation_ids' => $allocations->pluck('id')->all(),
                ],
                'period_started_at' => $periodStart,
                'period_ended_at' => $periodEnd,
                'observed_at' => Carbon::parse($evidence->observedAt),
                'settled_at' => $status === CommercialProviderCostBatchStatus::Settled ? now() : null,
            ]);

            if ($status === CommercialProviderCostBatchStatus::ReviewRequired) {
                $this->journal->recordProviderCostBatch($batch);

                return $batch;
            }

            foreach ($allocations as $allocation) {
                $settlement = $this->settlements->execute(new ProviderCostEvidenceData(
                    commercialSaleReference: $allocation->sale->reference,
                    provider: $evidence->provider,
                    connectionReference: $evidence->connectionReference,
                    evidenceType: $evidence->evidenceType,
                    evidenceReference: $evidence->evidenceReference.':allocation:'.$allocation->getKey(),
                    cashMovementObserved: true,
                    observedAmountMinor: $allocation->amount_minor,
                    currency: $evidence->currency,
                    observedAt: $evidence->observedAt,
                    idempotencyKey: $evidence->idempotencyKey.':allocation:'.$allocation->getKey(),
                    metadata: [
                        'provider_cost_batch_reference' => $batch->reference,
                    ],
                ));

                CommercialProviderCostBatchLine::query()->create([
                    'batch_id' => $batch->getKey(),
                    'commercial_allocation_id' => $allocation->getKey(),
                    'settlement_id' => $settlement->getKey(),
                    'expected_amount_minor' => $allocation->amount_minor,
                ]);
            }

            $batch = $batch->load('lines');
            $this->journal->recordProviderCostBatch($batch);

            return $batch;
        }, attempts: 5);
    }

    private function authorize(Model $operator): void
    {
        $systemPrincipal = $this->systemPrincipal->resolve();

        if ($operator->is($systemPrincipal)
            || ! $this->authority->allows($operator, CommercialOperatorCapability::ReconcileProviderCosts)) {
            throw new AuthorizationException('Operator lacks provider-cost reconciliation authority.');
        }
    }
}
