<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\XChange\Actions\Payment\CompleteVoucherCollection;
use LBHurtado\XChange\Data\Funding\FundingTransferCheckResultData;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingRequestWorkflowPublisher;
use LBHurtado\XChange\Services\Funding\StandingFundingRecognitionPolicy;
use RuntimeException;

final readonly class CheckFundingRequestTransfer
{
    public function __construct(
        private StandingFundingRecognitionPolicy $recognitionPolicy,
        private CompleteVoucherCollection $completeCollection,
        private FundingRequestWorkflowPublisher $workflow,
    ) {}

    public function handle(FundingRequest $fundingRequest): FundingTransferCheckResultData
    {
        $request = $fundingRequest->fresh('voucher');

        if ($request->funding_type !== FundingRequestType::BankTransfer) {
            throw new RuntimeException(
                'Only bank-transfer Funding Requests may be checked against provider evidence.',
            );
        }

        if ($request->status === FundingRequestStatus::Completed) {
            $collection = VoucherCollection::query()
                ->where('voucher_id', $request->voucher_id)
                ->where('execution_driver', 'x_change_provider_funding')
                ->first();

            return new FundingTransferCheckResultData(
                status: 'already_credited',
                message: 'This transfer was already credited exactly once.',
                credited: false,
                providerTransactionId: $collection?->provider_transaction_id,
            );
        }

        if (! in_array($request->status, [
            FundingRequestStatus::Submitted,
            FundingRequestStatus::UnderReview,
        ], true)) {
            throw new RuntimeException(
                'This Funding Request is not eligible for provider verification.',
            );
        }

        $reference = trim((string) $request->external_reference_ciphertext);

        if ($reference === '') {
            throw new RuntimeException(
                'A transfer reference is required before checking the provider.',
            );
        }

        $provider = mb_strtolower((string) config(
            'x-change.funding.requests.bank_transfer.provider',
            'netbank',
        ));
        $connectionReference = (string) config(
            'x-change.funding.requests.bank_transfer.connection_reference',
            'netbank-primary',
        );
        $observations = ProviderFundingObservation::query()
            ->where('provider_code', $provider)
            ->where(function ($query) use ($reference): void {
                $query->where('provider_transaction_id', $reference)
                    ->orWhere('provider_operation_id', $reference)
                    ->orWhere('request_id', $reference);
            })
            ->get()
            ->filter(fn (ProviderFundingObservation $observation): bool => $this->recognitionPolicy
                ->accepts($observation))
            ->filter(fn (ProviderFundingObservation $observation): bool => $observation
                ->net_amount_minor === $request->requested_value_minor
                && $observation->currency === $request->currency
                && data_get($observation->metadata, 'destination_verified') === true
                && data_get($observation->metadata, 'connection_reference')
                    === $connectionReference)
            ->values();

        if ($observations->count() !== 1) {
            $this->recordCheckEvent(
                $request,
                $observations->isEmpty()
                    ? 'provider_check_awaiting_evidence'
                    : 'provider_check_ambiguous',
                [
                    'provider' => $provider,
                    'connection_reference' => $connectionReference,
                    'match_count' => $observations->count(),
                    'balance_changed' => false,
                ],
            );
            $this->workflow->publish($request->refresh());

            return new FundingTransferCheckResultData(
                status: $observations->isEmpty()
                    ? 'awaiting_provider_evidence'
                    : 'review_required',
                message: $observations->isEmpty()
                    ? 'No exact receiver-side provider record is available yet.'
                    : 'Provider evidence is ambiguous and requires controlled review.',
                credited: false,
            );
        }

        $observation = $observations->sole();
        $prepared = DB::transaction(function () use (
            $request,
            $provider,
            $connectionReference,
            $observation,
        ): FundingRequest {
            $locked = FundingRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($locked->status === FundingRequestStatus::Completed) {
                return $locked;
            }

            $locked->forceFill([
                'status' => FundingRequestStatus::PayCodeIssued,
                'approved_value_minor' => $locked->requested_value_minor,
                'connection_reference' => $connectionReference,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'version' => $locked->version + 1,
            ])->saveQuietly();
            $locked->voucher->forceFill([
                'state' => VoucherState::ACTIVE,
            ])->saveQuietly();
            $this->recordCheckEvent(
                $locked,
                'provider_transfer_verified',
                [
                    'provider' => $provider,
                    'connection_reference' => $connectionReference,
                    'provider_funding_observation_id' => $observation->getKey(),
                    'balance_changed' => false,
                ],
                FundingRequestStatus::Submitted,
                FundingRequestStatus::PayCodeIssued,
            );

            return $locked->refresh('voucher');
        }, 3);

        if ($prepared->status === FundingRequestStatus::Completed) {
            return new FundingTransferCheckResultData(
                status: 'already_credited',
                message: 'This transfer was already credited exactly once.',
                credited: false,
                providerTransactionId: $observation->provider_transaction_id,
            );
        }

        $this->completeCollection->handle(
            $prepared->voucher,
            new ConfirmedVoucherCollectionData(
                amountMinor: $prepared->requested_value_minor,
                currency: $prepared->currency,
                executionDriver: 'x_change_provider_funding',
                authority: 'provider_transaction_history',
                authorityReference: $provider.':'.$observation->provider_transaction_id,
                idempotencyKey: 'provider-funding-request:'.$prepared->reference,
                provider: $provider,
                providerReference: $observation->provider_operation_id,
                providerTransactionId: $observation->provider_transaction_id,
                metadata: [
                    'provider_funding_observation_id' => $observation->getKey(),
                    'funding_request_reference' => $prepared->reference,
                ],
            ),
        );
        $completed = DB::transaction(function () use ($prepared): FundingRequest {
            $locked = FundingRequest::query()
                ->lockForUpdate()
                ->findOrFail($prepared->getKey());

            if ($locked->status !== FundingRequestStatus::Completed) {
                $from = $locked->status;
                $locked->forceFill([
                    'status' => FundingRequestStatus::Completed,
                    'completed_at' => now(),
                    'version' => $locked->version + 1,
                ])->saveQuietly();
                $this->recordCheckEvent(
                    $locked,
                    'provider_transfer_credited',
                    ['balance_changed' => true],
                    $from,
                    FundingRequestStatus::Completed,
                );
            }

            return $locked->refresh();
        }, 3);
        $this->workflow->publish($completed);

        return new FundingTransferCheckResultData(
            status: 'credited',
            message: 'NetBank transfer verified and credited exactly once.',
            credited: true,
            providerTransactionId: $observation->provider_transaction_id,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordCheckEvent(
        FundingRequest $request,
        string $eventType,
        array $metadata,
        ?FundingRequestStatus $from = null,
        ?FundingRequestStatus $to = null,
    ): void {
        $request->events()->create([
            'sequence' => ((int) $request->events()->max('sequence')) + 1,
            'event_type' => $eventType,
            'from_status' => $from ?? $request->status,
            'to_status' => $to ?? $request->status,
            'actor_type' => 'system',
            'actor_id' => 'provider-verification',
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
