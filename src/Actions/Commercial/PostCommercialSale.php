<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialChargeData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;
use LBHurtado\XChange\Services\Commercial\CommercialPartnerAttributionResolver;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialSaleSnapshotData;

final readonly class PostCommercialSale
{
    public function __construct(
        private TreasuryPositionOperationContract $positionOperations,
        private CommercialAccountingJournal $journal,
        private CommercialPartnerAttributionResolver $partners,
    ) {}

    /**
     * @param  array<string, string>  $destinationPositionReferences
     *
     * @throws JsonException
     */
    public function execute(
        CommercialSaleSnapshotData $snapshot,
        string $sourceClientFundsPositionReference,
        string $commercialClearingPositionReference,
        array $destinationPositionReferences,
    ): CommercialSale {
        $snapshotArray = $snapshot->toArray();
        $partnerAttributions = $this->partners->forSnapshot($snapshot);
        $snapshotArray['partner_governance'] = $partnerAttributions;
        $snapshotHash = hash(
            'sha256',
            json_encode($snapshotArray, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return DB::transaction(function () use (
            $commercialClearingPositionReference,
            $destinationPositionReferences,
            $snapshot,
            $snapshotArray,
            $snapshotHash,
            $partnerAttributions,
            $sourceClientFundsPositionReference,
        ): CommercialSale {
            $sale = CommercialSale::query()
                ->where('reference', $snapshot->reference)
                ->orWhere('acceptance_event_reference', $snapshot->acceptanceEventReference)
                ->lockForUpdate()
                ->first();

            if ($sale !== null) {
                $this->assertReplayMatches($sale, $snapshotHash);
                $this->assertPositionsMatch(
                    $sale,
                    $sourceClientFundsPositionReference,
                    $commercialClearingPositionReference,
                );

                if ($sale->status === 'posted') {
                    return $sale->load('allocations');
                }
            } else {
                $sale = CommercialSale::query()->create([
                    'reference' => $snapshot->reference,
                    'acceptance_event_reference' => $snapshot->acceptanceEventReference,
                    'source_commercial_event_reference' => $snapshot->quoteSnapshot->sourceCommercialEventReference,
                    'buyer_reference' => $snapshot->buyerReference,
                    'quote_reference' => $snapshot->quoteSnapshot->reference,
                    'catalog_reference' => $snapshot->quoteSnapshot->catalogSnapshot->reference,
                    'catalog_version' => $snapshot->quoteSnapshot->catalogSnapshot->version,
                    'waterfall_policy_reference' => $snapshot->quoteSnapshot->waterfallPolicySnapshot->reference,
                    'waterfall_policy_version' => $snapshot->quoteSnapshot->waterfallPolicySnapshot->version,
                    'attribution_reference' => $snapshot->quoteSnapshot->attributionSnapshot->reference,
                    'attribution_version' => $snapshot->quoteSnapshot->attributionSnapshot->version,
                    'currency' => $snapshot->quoteSnapshot->currency,
                    'total_price_minor' => $snapshot->quoteSnapshot->totalPriceMinor,
                    'snapshot_hash' => $snapshotHash,
                    'snapshot' => $snapshotArray,
                    'source_client_funds_position_reference' => $sourceClientFundsPositionReference,
                    'commercial_clearing_position_reference' => $commercialClearingPositionReference,
                    'status' => 'accepted',
                    'accepted_at' => $snapshot->acceptedAt,
                ]);

                $this->createAllocations(
                    $sale,
                    $snapshot,
                    $destinationPositionReferences,
                    $partnerAttributions,
                );
            }

            $scope = hash('sha256', $snapshot->reference);
            $charge = $this->positionOperations->charge(
                new TreasuryPositionCommercialChargeData(
                    operationReference: 'commercial-charge:'.$scope,
                    sourcePositionReference: $sourceClientFundsPositionReference,
                    destinationPositionReference: $commercialClearingPositionReference,
                    amountMinor: $snapshot->quoteSnapshot->totalPriceMinor,
                    currency: $snapshot->quoteSnapshot->currency,
                    idempotencyKey: 'commercial-charge-key:'.$scope,
                    externalReference: $snapshot->reference,
                    metadata: [
                        'source' => 'x_change_commercial_sale',
                        'commercial_sale_reference' => $snapshot->reference,
                        'quote_reference' => $snapshot->quoteSnapshot->reference,
                    ],
                ),
            );

            $allocations = $sale->allocations()->lockForUpdate()->get();

            foreach ($allocations as $allocation) {
                if ($allocation->status === 'posted') {
                    continue;
                }

                $operationReference = null;

                if ($allocation->amount_minor > 0) {
                    $allocationScope = hash('sha256', $snapshot->reference.'|'.$allocation->policy_rule_reference);
                    $operation = $this->positionOperations->allocate(
                        new TreasuryPositionAllocationData(
                            operationReference: 'commercial-allocation:'.$allocationScope,
                            sourcePositionReference: $commercialClearingPositionReference,
                            destinationPositionReference: $allocation->destination_position_reference,
                            amountMinor: $allocation->amount_minor,
                            currency: $allocation->currency,
                            idempotencyKey: 'commercial-allocation-key:'.$allocationScope,
                            externalReference: $snapshot->reference,
                            metadata: [
                                'source' => 'x_change_commercial_waterfall',
                                'commercial_sale_reference' => $snapshot->reference,
                                'policy_rule_reference' => $allocation->policy_rule_reference,
                                'category' => $allocation->category,
                                'recipient_reference' => $allocation->recipient_reference,
                                'commercial_partner_id' => $allocation->commercial_partner_id,
                                'commercial_partner_revision_id' => $allocation->commercial_partner_revision_id,
                            ],
                        ),
                    );
                    $operationReference = $operation->operationReference;
                }

                CommercialAllocation::query()
                    ->whereKey($allocation->getKey())
                    ->update([
                        'status' => 'posted',
                        'treasury_operation_reference' => $operationReference,
                        'updated_at' => now(),
                    ]);
            }

            CommercialSale::query()
                ->whereKey($sale->getKey())
                ->update([
                    'status' => 'posted',
                    'charge_operation_reference' => $charge->operationReference,
                    'posted_at' => now(),
                    'updated_at' => now(),
                ]);

            $posted = $sale->fresh('allocations');
            $this->journal->recordSalePosted($posted);

            return $posted;
        }, attempts: 5);
    }

    /**
     * @param  array<string, string>  $destinationPositionReferences
     * @param  array<string, array<string, mixed>>  $partnerAttributions
     */
    private function createAllocations(
        CommercialSale $sale,
        CommercialSaleSnapshotData $snapshot,
        array $destinationPositionReferences,
        array $partnerAttributions,
    ): void {
        $plan = $snapshot->quoteSnapshot->allocationPlan;

        if ($plan->allocationBaseMinor !== $snapshot->quoteSnapshot->totalPriceMinor
            || $plan->totalAllocatedMinor() !== $snapshot->quoteSnapshot->totalPriceMinor) {
            throw new CommercialSaleConflict('The commercial allocation plan does not conserve the quoted total.');
        }

        foreach ($plan->lines as $line) {
            $destination = trim((string) ($destinationPositionReferences[$line->policyRuleReference] ?? ''));

            if ($destination === '') {
                throw new CommercialSaleConflict(
                    "No Treasury destination is configured for waterfall rule [{$line->policyRuleReference}].",
                );
            }

            $this->createAllocation(
                $sale,
                $line,
                $destination,
                $partnerAttributions[$line->policyRuleReference] ?? null,
            );
        }
    }

    private function createAllocation(
        CommercialSale $sale,
        CommercialAllocationLineData $line,
        string $destinationPositionReference,
        ?array $partnerAttribution,
    ): void {
        CommercialAllocation::query()->create([
            'commercial_sale_id' => $sale->getKey(),
            'commercial_partner_id' => $partnerAttribution['commercial_partner_id'] ?? null,
            'commercial_partner_revision_id' => $partnerAttribution['commercial_partner_revision_id'] ?? null,
            'legacy_partner_reference' => ($partnerAttribution['status'] ?? null) === 'legacy_unresolved'
                ? $line->recipientReference
                : null,
            'sequence' => $line->sequence,
            'policy_rule_reference' => $line->policyRuleReference,
            'line_type' => $line->lineType->value,
            'category' => $line->category,
            'recipient_reference' => $line->recipientReference,
            'destination_position_reference' => $destinationPositionReference,
            'amount_minor' => $line->amountMinor,
            'currency' => $line->currency,
            'status' => 'planned',
            'metadata' => [
                'commercial_sale_reference' => $sale->reference,
                'partner_governance' => $partnerAttribution,
            ],
        ]);
    }

    private function assertReplayMatches(CommercialSale $sale, string $snapshotHash): void
    {
        if (! hash_equals($sale->snapshot_hash, $snapshotHash)) {
            throw new CommercialSaleConflict(
                'The commercial acceptance reference was replayed with a different immutable sale snapshot.',
            );
        }
    }

    private function assertPositionsMatch(
        CommercialSale $sale,
        string $sourceClientFundsPositionReference,
        string $commercialClearingPositionReference,
    ): void {
        if ($sale->source_client_funds_position_reference !== $sourceClientFundsPositionReference
            || $sale->commercial_clearing_position_reference !== $commercialClearingPositionReference) {
            throw new CommercialSaleConflict(
                'The commercial sale was replayed against different Treasury Positions.',
            );
        }
    }
}
