<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\AssignedAccountFundingPolicy;
use LBHurtado\XChange\Services\Funding\FreshFundingBoundary;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;

final readonly class CreditAdditionalAssignedFunding
{
    public function __construct(
        private CreateFundingIntent $create,
        private TransitionFundingIntent $transition,
        private SettleVerifiedFundingIntent $settle,
        private AssignedAccountFundingPolicy $policy,
        private FreshFundingBoundary $boundary,
    ) {}

    public function handle(FundingIntent $parent, ProviderFundingObservation $observation): void
    {
        if ($parent->status !== FundingIntentStatus::Settled || ! $this->policy->creditsOwner($parent)
            || $observation->provider_code !== $parent->provider_code || $observation->provider_status !== 'settled'
            || $observation->currency !== $parent->currency || $observation->gross_amount_minor <= 0
            || $observation->net_amount_minor <= 0 || $observation->settled_at === null
            || data_get($observation->metadata, 'destination_verified') !== true) {
            throw new LogicException('The additional account funding is not eligible for automatic credit.');
        }
        $this->boundary->assertEligible($parent);
        $intent = $this->create->handle(new CreateFundingIntentData(
            accountReference: $parent->account_reference,
            provider: $parent->provider_code,
            expectedAmountMinor: $observation->gross_amount_minor,
            currency: $parent->currency,
            idempotencyKey: 'additional-assigned-funding:'.$parent->getKey().':'.hash('sha256', $observation->provider_transaction_id),
            actorType: $parent->created_by_type,
            actorId: $parent->created_by_id,
            metadata: ['source' => 'additional_assigned_funding', 'parent_funding_reference' => $parent->reference],
            destination: FundingDestinationSnapshot::toData($parent->destination_snapshot_ciphertext),
        ));
        if ($intent->status === FundingIntentStatus::Settled) {
            return;
        }
        if ($intent->status !== FundingIntentStatus::PendingInstructions) {
            throw new LogicException('The additional funding request requires review.');
        }
        foreach ([FundingIntentStatus::AwaitingFunds, FundingIntentStatus::Verifying, FundingIntentStatus::Verified] as $status) {
            $intent = $this->transition->handle($intent, new FundingIntentTransitionData(
                status: $status,
                eventType: 'additional_assigned_funding_'.$status->value,
                actorType: 'system', actorId: 'funding-scheduler', expectedVersion: $intent->version,
                evidenceReference: 'provider-observation:'.$observation->getKey(),
                providerObservationId: $observation->getKey(),
                providerTransactionId: $observation->provider_transaction_id,
                metadata: ['parent_funding_reference' => $parent->reference],
            ));
        }
        $this->settle->handle($intent);
    }
}
