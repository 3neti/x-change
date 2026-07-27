<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Actions\Payment\CompleteVoucherCollection;
use LBHurtado\XChange\Contracts\FundingProjectionPublisherContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingRequestTransferMatch;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingRequestWorkflowPublisher;
use RuntimeException;

final readonly class PayApprovedFundingRequest
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private CompleteVoucherCollection $collections,
        private EnvelopeService $envelopes,
        private FundingProjectionPublisherContract $projections,
        private FundingRequestWorkflowPublisher $workflows,
    ) {}

    public function handle(Voucher $voucher): VoucherCollection
    {
        $projection = null;
        $workflowRequest = null;
        $collection = DB::transaction(function () use (
            $voucher,
            &$projection,
            &$workflowRequest,
        ): VoucherCollection {
            $request = FundingRequest::query()
                ->with([
                    'voucher.envelope',
                    'transferMatch.providerFundingObservation',
                ])
                ->where('voucher_id', $voucher->getKey())
                ->lockForUpdate()
                ->sole();
            $providerTransferMatch = $request->transferMatch;
            $idempotencyKey = $providerTransferMatch instanceof FundingRequestTransferMatch
                ? 'provider-funding-request:'.$request->reference
                : 'reviewed-funding-system-payment:'.$request->reference;

            if ($request->status === FundingRequestStatus::Completed) {
                return VoucherCollection::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->sole();
            }

            if (
                $request->status !== FundingRequestStatus::PayCodeIssued
                || ! $request->voucher instanceof Voucher
                || $request->voucher->envelope === null
            ) {
                throw new RuntimeException(
                    'This Account Funding Pay Code is not ready for system Treasury payment.',
                );
            }

            if (
                $request->voucher->envelope->status !== EnvelopeStatus::LOCKED
                || ! $request->voucher->envelope->isSettleable()
            ) {
                throw new RuntimeException(
                    'The Account Funding Settlement Envelope is not locked and settleable.',
                );
            }

            $system = $this->systemUsers->resolve();

            if (! $system instanceof Model) {
                throw new RuntimeException(
                    'The configured system Treasury principal is unavailable.',
                );
            }

            if ($providerTransferMatch instanceof FundingRequestTransferMatch) {
                $observation = $providerTransferMatch
                    ->providerFundingObservation;

                if (! $observation instanceof ProviderFundingObservation) {
                    throw new RuntimeException(
                        'The approved provider transfer evidence is unavailable.',
                    );
                }

                $collection = $this->collections->handle(
                    $request->voucher,
                    new ConfirmedVoucherCollectionData(
                        amountMinor: (int) $request->approved_value_minor,
                        currency: $request->currency,
                        executionDriver: 'x_change_provider_funding',
                        authority: 'provider_transaction_history_with_checker_approval',
                        authorityReference: $providerTransferMatch->provider_code
                            .':'.$observation->provider_transaction_id,
                        idempotencyKey: $idempotencyKey,
                        provider: $providerTransferMatch->provider_code,
                        providerReference: $observation->provider_operation_id,
                        providerTransactionId: $observation->provider_transaction_id,
                        metadata: [
                            'funding_request_reference' => $request->reference,
                            'provider_funding_observation_id' => $observation->getKey(),
                            'transfer_match_id' => $providerTransferMatch->getKey(),
                            'settlement_envelope_id' => $request->voucher->envelope->getKey(),
                        ],
                    ),
                );
                $providerTransferMatch->forceFill([
                    'status' => 'credited',
                    'credited_at' => now(),
                ])->saveQuietly();
            } else {
                $collection = $this->collections->handle(
                    $request->voucher,
                    new ConfirmedVoucherCollectionData(
                        amountMinor: (int) $request->approved_value_minor,
                        currency: $request->currency,
                        executionDriver: 'x_change_account_funding',
                        authority: 'system_treasury',
                        authorityReference: 'funding-request-approval:'.$request->reference,
                        idempotencyKey: $idempotencyKey,
                        payerName: (string) $system->getAttribute('name'),
                        metadata: [
                            'funding_request_reference' => $request->reference,
                            'evidence_reference' => $request->evidence_reference,
                            'settlement_envelope_id' => $request->voucher->envelope->getKey(),
                        ],
                    ),
                );
            }
            $this->envelopes->settle(
                $request->voucher->envelope->refresh(),
                $system,
            );
            $nextVersion = $request->version + 1;
            $request->forceFill([
                'status' => FundingRequestStatus::Completed,
                'version' => $nextVersion,
                'completed_at' => now(),
            ])->saveQuietly();
            $request->events()->create([
                'sequence' => $nextVersion,
                'event_type' => $providerTransferMatch instanceof FundingRequestTransferMatch
                    ? 'provider_transfer_credited_after_approval'
                    : 'reviewed_funding_pay_code_paid',
                'from_status' => FundingRequestStatus::PayCodeIssued,
                'to_status' => FundingRequestStatus::Completed,
                'actor_type' => $system::class,
                'actor_id' => (string) $system->getKey(),
                'evidence_reference' => 'voucher-collection:'.$collection->getKey(),
                'metadata' => [
                    'pay_code_id' => (int) $request->voucher->getKey(),
                    'voucher_collection_id' => (int) $collection->getKey(),
                    'treasury_operation_reference' => $collection->treasury_operation_reference,
                    'provider_calls' => $providerTransferMatch
                        instanceof FundingRequestTransferMatch,
                    'provider_inventory_changed' => $providerTransferMatch
                        instanceof FundingRequestTransferMatch,
                    'provider_transfer_match_id' => $providerTransferMatch?->getKey(),
                ],
                'occurred_at' => now(),
            ]);
            $projection = [
                'owner_type' => $request->requester_type,
                'owner_id' => $request->requester_id,
                'reference' => 'voucher-collection:'.$collection->getKey(),
                'occurred_at' => (string) $collection->completed_at?->toIso8601String(),
            ];
            $workflowRequest = $request->refresh();

            return $collection;
        }, 5);

        if (is_array($projection)) {
            $this->projections->publish(
                ownerType: $projection['owner_type'],
                ownerId: $projection['owner_id'],
                reference: $projection['reference'],
                occurredAt: $projection['occurred_at'],
            );
        }
        if ($workflowRequest instanceof FundingRequest) {
            $this->workflows->publish($workflowRequest);
        }

        return $collection;
    }
}
