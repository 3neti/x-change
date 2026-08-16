<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionInternalPayableSettlementData;
use LBHurtado\XChange\Data\Commercial\CommercialAllocationDispositionPlanData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialAllocationDisposition;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;

final readonly class ApplyCommercialAllocationDisposition
{
    public function __construct(
        private TreasuryPositionOperationContract $positionOperations,
    ) {}

    public function execute(
        CommercialAllocation $allocation,
        CommercialAllocationDispositionPlanData $plan,
    ): CommercialAllocationDisposition {
        return DB::transaction(function () use ($allocation, $plan): CommercialAllocationDisposition {
            CommercialSale::query()
                ->whereKey($allocation->commercial_sale_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = CommercialAllocation::query()
                ->whereKey($allocation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $this->apply($locked, $plan);
        }, attempts: 5);
    }

    private function apply(
        CommercialAllocation $allocation,
        CommercialAllocationDispositionPlanData $plan,
    ): CommercialAllocationDisposition {
        $governance = (array) data_get($allocation->metadata, 'recipient_designation_governance', []);

        if ($plan->policyRuleReference !== $allocation->policy_rule_reference
            || $allocation->status !== 'posted'
            || ($allocation->amount_minor > 0 && blank($allocation->treasury_operation_reference))
            || data_get($allocation->metadata, 'destination_kind') !== 'external_recipient'
            || data_get($allocation->metadata, 'designation_reference') !== $plan->designationReference
            || ($governance['authority_reference'] ?? null) !== $plan->authorityReference
            || ($governance['authority_hash'] ?? null) !== $plan->authorityHash) {
            throw new CommercialSaleConflict(
                'Commercial allocation disposition does not match its posted frozen authority.',
            );
        }

        $existing = CommercialAllocationDisposition::query()
            ->where('commercial_allocation_id', $allocation->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing instanceof CommercialAllocationDisposition) {
            $this->assertReplay($existing, $allocation, $plan);

            return $existing;
        }

        $operationReference = null;

        if ($plan->disposition === CommercialSettlementDisposition::InternalAccountCredit
            && $allocation->amount_minor > 0) {
            $destination = trim((string) $plan->destinationClientFundsPositionReference);

            if ($destination === '') {
                throw new CommercialSaleConflict(
                    'Internal commercial settlement has no governed Client Funds destination.',
                );
            }

            $scope = hash('sha256', $allocation->sale->reference.'|'.$allocation->policy_rule_reference);
            $settlement = $this->positionOperations->settlePayableInternally(
                new TreasuryPositionInternalPayableSettlementData(
                    operationReference: 'commercial-internal-settlement:'.$scope,
                    sourcePositionReference: $allocation->destination_position_reference,
                    destinationPositionReference: $destination,
                    amountMinor: $allocation->amount_minor,
                    currency: $allocation->currency,
                    idempotencyKey: 'commercial-internal-settlement-key:'.$scope,
                    externalReference: $allocation->sale->reference,
                    metadata: [
                        'source' => 'x_change_commercial_disposition',
                        'commercial_sale_reference' => $allocation->sale->reference,
                        'policy_rule_reference' => $allocation->policy_rule_reference,
                        'provider_call' => false,
                        'provider_inventory_changed' => false,
                    ],
                ),
            );
            $operationReference = $settlement->operationReference;
        }

        return CommercialAllocationDisposition::query()->create([
            'commercial_allocation_id' => $allocation->getKey(),
            'disposition' => $plan->disposition,
            'status' => 'committed',
            'designation_reference' => $plan->designationReference,
            'authority_reference' => $plan->authorityReference,
            'authority_hash' => $plan->authorityHash,
            'account_reference_hash' => $plan->accountReferenceHash,
            'principal_reference_hash' => $plan->principalReferenceHash,
            'source_position_reference' => $allocation->destination_position_reference,
            'destination_position_reference' => $plan->destinationClientFundsPositionReference,
            'treasury_operation_reference' => $operationReference,
            'amount_minor' => $allocation->amount_minor,
            'currency' => $allocation->currency,
            'committed_at' => now(),
        ]);
    }

    private function assertReplay(
        CommercialAllocationDisposition $existing,
        CommercialAllocation $allocation,
        CommercialAllocationDispositionPlanData $plan,
    ): void {
        if ($existing->disposition !== $plan->disposition
            || $existing->designation_reference !== $plan->designationReference
            || $existing->authority_reference !== $plan->authorityReference
            || ! hash_equals($existing->authority_hash, $plan->authorityHash)
            || $existing->account_reference_hash !== $plan->accountReferenceHash
            || $existing->principal_reference_hash !== $plan->principalReferenceHash
            || $existing->destination_position_reference !== $plan->destinationClientFundsPositionReference
            || $existing->source_position_reference !== $allocation->destination_position_reference
            || $existing->status !== 'committed'
            || $existing->amount_minor !== $allocation->amount_minor
            || $existing->currency !== $allocation->currency
            || ($plan->disposition === CommercialSettlementDisposition::InternalAccountCredit
                && $allocation->amount_minor > 0
                && blank($existing->treasury_operation_reference))
            || ($plan->disposition === CommercialSettlementDisposition::RetainPayable
                && filled($existing->treasury_operation_reference))) {
            throw new CommercialSaleConflict(
                'The commercial allocation disposition was replayed with different governed authority.',
            );
        }
    }
}
