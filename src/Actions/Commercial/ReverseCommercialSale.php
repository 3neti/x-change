<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialReversalData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Commercial\CommercialSaleReversalPolicy;

final readonly class ReverseCommercialSale
{
    public function __construct(
        private TreasuryPositionOperationContract $positionOperations,
        private CommercialSaleReversalPolicy $policy,
    ) {}

    public function execute(string $commercialSaleReference, string $reasonReference): CommercialSale
    {
        return DB::transaction(function () use ($commercialSaleReference, $reasonReference): CommercialSale {
            $sale = CommercialSale::query()
                ->where('reference', $commercialSaleReference)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status === 'reversed') {
                return $sale->load('allocations');
            }

            if ($sale->status !== 'posted' || blank($sale->charge_operation_reference)) {
                throw new CommercialSaleConflict('Only a fully posted commercial sale can be reversed.');
            }

            $reason = trim($reasonReference);

            if ($reason === '') {
                throw new CommercialSaleConflict('A commercial reversal reason reference is required.');
            }

            $this->policy->assertMayReverse($sale, $reason);

            $allocations = $sale->allocations()
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                if ($allocation->status === 'reversed') {
                    continue;
                }

                $reversalReference = null;

                if ($allocation->amount_minor > 0) {
                    if (blank($allocation->treasury_operation_reference)) {
                        throw new CommercialSaleConflict('A posted allocation is missing its Treasury operation reference.');
                    }

                    $scope = hash('sha256', $sale->reference.'|'.$allocation->policy_rule_reference.'|'.$reason);
                    $reversal = $this->positionOperations->reverseCommercialMovement(
                        new TreasuryPositionCommercialReversalData(
                            operationReference: 'commercial-allocation-reversal:'.$scope,
                            reversesOperationReference: $allocation->treasury_operation_reference,
                            sourcePositionReference: $allocation->destination_position_reference,
                            destinationPositionReference: $sale->commercial_clearing_position_reference,
                            amountMinor: $allocation->amount_minor,
                            currency: $allocation->currency,
                            idempotencyKey: 'commercial-allocation-reversal-key:'.$scope,
                            externalReference: $reason,
                            metadata: [
                                'source' => 'x_change_commercial_reversal',
                                'commercial_sale_reference' => $sale->reference,
                                'policy_rule_reference' => $allocation->policy_rule_reference,
                            ],
                        ),
                    );
                    $reversalReference = $reversal->operationReference;
                }

                CommercialAllocation::query()
                    ->whereKey($allocation->getKey())
                    ->update([
                        'status' => 'reversed',
                        'treasury_reversal_operation_reference' => $reversalReference,
                        'updated_at' => now(),
                    ]);
            }

            $scope = hash('sha256', $sale->reference.'|charge|'.$reason);
            $this->positionOperations->reverseCommercialMovement(
                new TreasuryPositionCommercialReversalData(
                    operationReference: 'commercial-charge-reversal:'.$scope,
                    reversesOperationReference: $sale->charge_operation_reference,
                    sourcePositionReference: $sale->commercial_clearing_position_reference,
                    destinationPositionReference: $sale->source_client_funds_position_reference,
                    amountMinor: $sale->total_price_minor,
                    currency: $sale->currency,
                    idempotencyKey: 'commercial-charge-reversal-key:'.$scope,
                    externalReference: $reason,
                    metadata: [
                        'source' => 'x_change_commercial_reversal',
                        'commercial_sale_reference' => $sale->reference,
                    ],
                ),
            );

            CommercialSale::query()
                ->whereKey($sale->getKey())
                ->update([
                    'status' => 'reversed',
                    'reversed_at' => now(),
                    'updated_at' => now(),
                ]);

            return $sale->fresh('allocations');
        }, attempts: 5);
    }
}
