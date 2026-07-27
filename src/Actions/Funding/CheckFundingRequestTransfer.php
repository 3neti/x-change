<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\XChange\Actions\Payment\CompleteVoucherCollection;
use LBHurtado\XChange\Data\Funding\FundingTransferCheckResultData;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferWindow;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingRequestTransferMatch;
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
        $request = $fundingRequest->fresh([
            'voucher',
            'transferMatch.providerFundingObservation',
        ]);

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

        if (
            $request->status === FundingRequestStatus::AwaitingApproval
            && $request->transferMatch instanceof FundingRequestTransferMatch
        ) {
            return new FundingTransferCheckResultData(
                status: 'approval_required',
                message: 'Transfer found. Its age requires system approval before crediting.',
                credited: false,
                providerTransactionId: $request->transferMatch
                    ->providerFundingObservation
                    ?->provider_transaction_id,
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

        $provider = mb_strtolower((string) config(
            'x-change.funding.requests.bank_transfer.provider',
            'netbank',
        ));
        $connectionReference = (string) config(
            'x-change.funding.requests.bank_transfer.connection_reference',
            'netbank-primary',
        );
        $automaticCreditWindowMinutes = max(1, (int) config(
            'x-change.funding.requests.bank_transfer.automatic_credit_window_minutes',
            10,
        ));
        $clockSkewSeconds = max(0, (int) config(
            'x-change.funding.requests.bank_transfer.clock_skew_seconds',
            120,
        ));
        $now = CarbonImmutable::instance(now());
        $window = FundingTransferWindow::tryFrom((string) data_get(
            $request->metadata,
            'transfer_window',
            FundingTransferWindow::Recent->value,
        )) ?? FundingTransferWindow::Recent;
        $searchStartedAt = $window->startsAt(
            $now,
            $automaticCreditWindowMinutes,
        );
        $searchEndedAt = $now->addSeconds($clockSkewSeconds);
        $observations = ProviderFundingObservation::query()
            ->where('provider_code', $provider)
            ->where('net_amount_minor', $request->requested_value_minor)
            ->where('currency', $request->currency)
            ->whereBetween('occurred_at', [$searchStartedAt, $searchEndedAt])
            ->whereNotIn(
                'id',
                FundingRequestTransferMatch::query()
                    ->select('provider_funding_observation_id'),
            )
            ->whereNotIn(
                'provider_transaction_id',
                VoucherCollection::query()
                    ->where('provider', $provider)
                    ->whereNotNull('provider_transaction_id')
                    ->select('provider_transaction_id'),
            )
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->filter(fn (ProviderFundingObservation $observation): bool => $this->recognitionPolicy
                ->accepts($observation))
            ->filter(fn (ProviderFundingObservation $observation): bool => data_get(
                $observation->metadata,
                'destination_verified',
            ) === true
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
                    'transfer_window' => $window->value,
                    'search_started_at' => $searchStartedAt->toIso8601String(),
                    'search_ended_at' => $searchEndedAt->toIso8601String(),
                    'sender_reference_used_as_authority' => false,
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
                    : 'More than one provider transfer matches. System review is required.',
                credited: false,
            );
        }

        $observation = $observations->sole();
        $match = $this->reserveMatch(
            $request,
            $observation,
            $connectionReference,
            $window,
            $searchStartedAt,
            $searchEndedAt,
        );

        if (! $match instanceof FundingRequestTransferMatch) {
            return new FundingTransferCheckResultData(
                status: 'review_required',
                message: 'This provider transfer is already reserved for another request.',
                credited: false,
            );
        }

        $occurredAt = $observation->occurredAtInstant();
        $automatic = (string) config(
            'x-change.funding.requests.bank_transfer.verification_mode',
            'provider_verified_auto',
        ) === 'provider_verified_auto'
            && $occurredAt !== null
            && $occurredAt->greaterThanOrEqualTo(
                $now->subMinutes($automaticCreditWindowMinutes),
            )
            && $occurredAt->lessThanOrEqualTo($searchEndedAt);

        if (! $automatic) {
            return $this->stageForApproval(
                $request,
                $match,
                $observation,
                $provider,
                $connectionReference,
                $automaticCreditWindowMinutes,
            );
        }

        return $this->creditAutomatically(
            $request,
            $match,
            $observation,
            $provider,
            $connectionReference,
        );
    }

    private function reserveMatch(
        FundingRequest $request,
        ProviderFundingObservation $observation,
        string $connectionReference,
        FundingTransferWindow $window,
        CarbonImmutable $searchStartedAt,
        CarbonImmutable $searchEndedAt,
    ): ?FundingRequestTransferMatch {
        try {
            return DB::transaction(
                fn (): FundingRequestTransferMatch => FundingRequestTransferMatch::query()
                    ->create([
                        'funding_request_id' => $request->getKey(),
                        'provider_funding_observation_id' => $observation->getKey(),
                        'provider_code' => $observation->provider_code,
                        'connection_reference' => $connectionReference,
                        'amount_minor' => $observation->net_amount_minor,
                        'currency' => $observation->currency,
                        'status' => 'matched',
                        'matched_at' => now(),
                        'metadata' => [
                            'transfer_window' => $window->value,
                            'search_started_at' => $searchStartedAt->toIso8601String(),
                            'search_ended_at' => $searchEndedAt->toIso8601String(),
                            'sender_reference_used_as_authority' => false,
                        ],
                    ]),
                3,
            );
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function stageForApproval(
        FundingRequest $request,
        FundingRequestTransferMatch $match,
        ProviderFundingObservation $observation,
        string $provider,
        string $connectionReference,
        int $automaticCreditWindowMinutes,
    ): FundingTransferCheckResultData {
        $prepared = DB::transaction(function () use (
            $request,
            $match,
            $observation,
            $provider,
            $connectionReference,
            $automaticCreditWindowMinutes,
        ): FundingRequest {
            $locked = FundingRequest::query()
                ->with('voucher')
                ->lockForUpdate()
                ->findOrFail($request->getKey());
            $from = $locked->status;
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            data_set($metadata, 'provider_transfer_match', [
                'id' => $match->getKey(),
                'provider_funding_observation_id' => $observation->getKey(),
                'automatic_credit_eligible' => false,
                'automatic_credit_window_minutes' => $automaticCreditWindowMinutes,
            ]);
            $locked->forceFill([
                'status' => FundingRequestStatus::AwaitingApproval,
                'approved_value_minor' => $locked->requested_value_minor,
                'connection_reference' => $connectionReference,
                'evidence_reference' => $provider.':'.$observation->provider_transaction_id,
                'reviewed_by_type' => 'system',
                'reviewed_by_id' => 'provider-verification',
                'reviewed_at' => now(),
                'metadata' => $metadata,
                'version' => $locked->version + 1,
            ])->saveQuietly();
            $locked->voucher->forceFill([
                'state' => VoucherState::LOCKED,
            ])->saveQuietly();
            $match->forceFill([
                'status' => 'awaiting_approval',
            ])->saveQuietly();
            $this->recordCheckEvent(
                $locked,
                'provider_transfer_matched_for_approval',
                [
                    'provider' => $provider,
                    'connection_reference' => $connectionReference,
                    'provider_funding_observation_id' => $observation->getKey(),
                    'automatic_credit_window_minutes' => $automaticCreditWindowMinutes,
                    'balance_changed' => false,
                ],
                $from,
                FundingRequestStatus::AwaitingApproval,
            );
            $locked->notices()->create([
                'recipient_type' => $locked->requester_type,
                'recipient_id' => $locked->requester_id,
                'notice_type' => 'provider_transfer_approval_required',
                'title' => 'Transfer found',
                'message' => 'The transfer is reserved and awaiting system approval.',
                'action' => [
                    'type' => 'view_reviewed_funding_pay_code',
                    'funding_request_reference' => $locked->reference,
                ],
            ]);

            return $locked->refresh();
        }, 3);
        $this->workflow->publish($prepared);

        return new FundingTransferCheckResultData(
            status: 'approval_required',
            message: 'Transfer found. Its age requires system approval before crediting.',
            credited: false,
            providerTransactionId: $observation->provider_transaction_id,
        );
    }

    private function creditAutomatically(
        FundingRequest $request,
        FundingRequestTransferMatch $match,
        ProviderFundingObservation $observation,
        string $provider,
        string $connectionReference,
    ): FundingTransferCheckResultData {
        $prepared = DB::transaction(function () use (
            $request,
            $match,
            $observation,
            $provider,
            $connectionReference,
        ): FundingRequest {
            $locked = FundingRequest::query()
                ->with('voucher')
                ->lockForUpdate()
                ->findOrFail($request->getKey());
            $from = $locked->status;
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
                    'transfer_match_id' => $match->getKey(),
                    'automatic_credit_eligible' => true,
                    'balance_changed' => false,
                ],
                $from,
                FundingRequestStatus::PayCodeIssued,
            );

            return $locked->refresh('voucher');
        }, 3);

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
                    'transfer_match_id' => $match->getKey(),
                ],
            ),
        );
        $completed = DB::transaction(function () use (
            $prepared,
            $match,
        ): FundingRequest {
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
                $match->forceFill([
                    'status' => 'credited',
                    'credited_at' => now(),
                ])->saveQuietly();
                $this->recordCheckEvent(
                    $locked,
                    'provider_transfer_credited',
                    [
                        'transfer_match_id' => $match->getKey(),
                        'balance_changed' => true,
                    ],
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
