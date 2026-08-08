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
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutBatchRequestData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatchLine;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;

final readonly class RequestPartnerCommissionPayoutBatch
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private CommercialAccountingJournal $journal,
    ) {}

    /** @throws JsonException */
    public function execute(Model $maker, PartnerCommissionPayoutBatchRequestData $request): PartnerCommissionPayoutBatch
    {
        $this->authorize($maker);
        $partner = CommercialPartner::query()
            ->active()
            ->where('reference', $request->partnerReference)
            ->first();

        if (! $partner instanceof CommercialPartner) {
            throw new CommercialSaleConflict('Commission payout requires an active governed Commercial Partner.');
        }

        $destinationRevision = $partner->destinationRevisions()
            ->approved()
            ->where('provider', mb_strtolower($request->provider))
            ->where('connection_reference', $request->connectionReference)
            ->where('currency', mb_strtoupper($request->currency))
            ->first();

        if (! $destinationRevision instanceof CommercialPartnerDestinationRevision) {
            throw new CommercialSaleConflict('Commission payout requires an approved Partner destination.');
        }

        $destination = $destinationRevision->destination;

        $payload = [
            ...$request->toArray(),
            'commercial_partner_id' => $partner->getKey(),
            'destination_revision_id' => $destinationRevision->getKey(),
            'destination_hash' => $destinationRevision->destination_hash,
        ];
        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $destination,
            $destinationRevision,
            $maker,
            $partner,
            $request,
            $requestHash,
        ): PartnerCommissionPayoutBatch {
            $existing = PartnerCommissionPayoutBatch::query()
                ->where('request_idempotency_key', $request->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PartnerCommissionPayoutBatch) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new CommercialSaleConflict('Commission payout request key was reused with different input.');
                }

                return $existing;
            }

            $periodStart = Carbon::parse($request->periodStartedAt);
            $periodEnd = Carbon::parse($request->periodEndedAt);
            $allocations = CommercialAllocation::query()
                ->with('sale')
                ->where('category', 'partner_commission')
                ->where('status', 'posted')
                ->where('currency', mb_strtoupper($request->currency))
                ->where('commercial_partner_id', $partner->getKey())
                ->whereHas('sale', fn ($query) => $query
                    ->where('status', 'posted')
                    ->whereBetween('accepted_at', [$periodStart, $periodEnd]))
                ->whereNotIn('id', PartnerCommissionPayoutBatchLine::query()->select('commercial_allocation_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(function (CommercialAllocation $allocation) use ($request): bool {
                    $context = (array) data_get($allocation->sale->snapshot, 'accounting_context', []);

                    return data_get($context, 'provider') === mb_strtolower($request->provider)
                        && data_get($context, 'connection_reference') === $request->connectionReference;
                })
                ->values();

            if ($allocations->isEmpty()) {
                throw new CommercialSaleConflict('No unpaid earned commission matches this partner and period.');
            }

            $positionReferences = $allocations->pluck('destination_position_reference')->unique();

            if ($positionReferences->count() !== 1) {
                throw new CommercialSaleConflict('Commission allocations do not share one partner payable Position.');
            }

            $partnerRevisionIds = $allocations->pluck('commercial_partner_revision_id')->unique();

            if ($partnerRevisionIds->count() !== 1) {
                throw new CommercialSaleConflict('Commission allocations span multiple Partner revisions and must be paid separately.');
            }

            $partnerRevision = CommercialPartnerRevision::query()->findOrFail($partnerRevisionIds->sole());

            $amountMinor = (int) $allocations->sum('amount_minor');
            $batch = PartnerCommissionPayoutBatch::query()->create([
                'reference' => $request->reference,
                'partner_reference' => $request->partnerReference,
                'commercial_partner_id' => $partner->getKey(),
                'commercial_partner_revision_id' => $partnerRevision->getKey(),
                'commercial_partner_destination_revision_id' => $destinationRevision->getKey(),
                'provider' => mb_strtolower($request->provider),
                'connection_reference' => $request->connectionReference,
                'position_reference' => $positionReferences->sole(),
                'amount_minor' => $amountMinor,
                'currency' => mb_strtoupper($request->currency),
                'status' => PartnerCommissionPayoutBatchStatus::AwaitingApproval,
                'destination' => [
                    ...$destination,
                    'settlement_rail' => $this->rail($amountMinor),
                ],
                'destination_hash' => $destinationRevision->destination_hash,
                'destination_summary' => $destinationRevision->destination_summary,
                'request_idempotency_key' => $request->idempotencyKey,
                'request_hash' => $requestHash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => $maker->getKey(),
                'metadata' => $request->metadata,
                'period_started_at' => $periodStart,
                'period_ended_at' => $periodEnd,
                'requested_at' => now(),
            ]);

            foreach ($allocations as $allocation) {
                PartnerCommissionPayoutBatchLine::query()->create([
                    'batch_id' => $batch->getKey(),
                    'commercial_allocation_id' => $allocation->getKey(),
                    'amount_minor' => $allocation->amount_minor,
                ]);
            }

            $batch = $batch->load('lines');
            $this->journal->recordPartnerPayoutBatch(
                $batch,
                $maker->getMorphClass().':'.$maker->getKey(),
                'commercial_payout_maker',
            );

            return $batch;
        }, attempts: 5);
    }

    private function authorize(Model $maker): void
    {
        if ($maker->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($maker, CommercialOperatorCapability::RequestCommissionPayouts)) {
            throw new AuthorizationException('Operator lacks commission payout request authority.');
        }
    }

    private function rail(int $amountMinor): string
    {
        return $amountMinor >= 5_000_000 ? 'PESONET' : 'INSTAPAY';
    }
}
