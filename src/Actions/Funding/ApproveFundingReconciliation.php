<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class ApproveFundingReconciliation
{
    public function __construct(
        private readonly TransitionFundingIntent $transition,
        private readonly SettleVerifiedFundingIntent $settle,
    ) {}

    public function handle(
        FundingReconciliationRequest $request,
        string $actorType,
        string $actorId,
    ): FundingReconciliationRequest {
        $actorType = trim($actorType);
        $actorId = trim($actorId);

        if ($actorType === '' || $actorId === '') {
            throw new InvalidArgumentException('A reconciliation approver identity is required.');
        }

        return DB::transaction(function () use ($request, $actorType, $actorId): FundingReconciliationRequest {
            $locked = FundingReconciliationRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status === 'executed') {
                return $locked;
            }

            if ($locked->status !== 'pending_approval') {
                throw new InvalidArgumentException('The reconciliation request is not pending approval.');
            }

            if ($locked->requested_by_type === $actorType && $locked->requested_by_id === $actorId) {
                throw new InvalidArgumentException('The reconciliation requester cannot approve their own request.');
            }

            $case = FundingSuspenseCase::query()
                ->lockForUpdate()
                ->findOrFail($locked->funding_suspense_case_id);
            $intent = $case->funding_intent_id === null
                ? null
                : FundingIntent::query()->lockForUpdate()->findOrFail($case->funding_intent_id);
            $result = match ($locked->action) {
                FundingReconciliationAction::RetryVerification => $this->retryVerification(
                    $case,
                    $intent,
                    $actorType,
                    $actorId,
                ),
                FundingReconciliationAction::MatchVerifiedObservation => $this->matchObservation(
                    $case,
                    $intent,
                    (int) data_get($locked->payload, 'provider_observation_id'),
                    $actorType,
                    $actorId,
                ),
                FundingReconciliationAction::CompensateVerifiedPosting => $this->compensatePosting(
                    $case,
                    $intent,
                    $actorType,
                    $actorId,
                ),
            };
            $now = now();

            $locked->forceFill([
                'status' => 'executed',
                'approved_by_type' => $actorType,
                'approved_by_id' => $actorId,
                'approved_at' => $now,
                'executed_at' => $now,
                'result' => $result,
            ])->saveQuietly();

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * @return array<string, int|string>
     */
    private function retryVerification(
        FundingSuspenseCase $case,
        ?FundingIntent $intent,
        string $actorType,
        string $actorId,
    ): array {
        if ($intent === null || $intent->status !== FundingIntentStatus::Suspense) {
            throw new InvalidArgumentException('Retry verification requires a suspended Funding Intent.');
        }

        if ($case->webhook_receipt_id === null) {
            throw new InvalidArgumentException('Retry verification requires preserved webhook evidence.');
        }

        $receipt = WebhookReceipt::query()->lockForUpdate()->findOrFail($case->webhook_receipt_id);
        $intent = $this->transition->handle($intent, new FundingIntentTransitionData(
            status: FundingIntentStatus::Verifying,
            eventType: 'reconciliation_verification_retry_approved',
            actorType: $actorType,
            actorId: $actorId,
            expectedVersion: $intent->version,
            evidenceReference: 'funding-reconciliation-case:'.$case->reference,
            metadata: [
                'webhook_receipt_id' => $receipt->getKey(),
            ],
        ));
        $receipt->forceFill([
            'processing_status' => 'received',
            'processed_at' => null,
            'error_message' => null,
        ])->save();
        $case->forceFill([
            'status' => 'monitoring',
            'resolution_code' => 'verification_retry_queued',
            'resolution' => [
                'funding_intent_version' => $intent->version,
                'webhook_receipt_id' => $receipt->getKey(),
            ],
        ])->saveQuietly();

        VerifyFundingWebhookReceiptJob::dispatch($receipt->getKey())->afterCommit();

        return [
            'outcome' => 'verification_retry_queued',
            'webhook_receipt_id' => $receipt->getKey(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function matchObservation(
        FundingSuspenseCase $case,
        ?FundingIntent $intent,
        int $observationId,
        string $actorType,
        string $actorId,
    ): array {
        if ($intent === null || $intent->status !== FundingIntentStatus::Suspense || $observationId <= 0) {
            throw new InvalidArgumentException('Observation matching requires a suspended Funding Intent.');
        }

        $observation = ProviderFundingObservation::query()->lockForUpdate()->findOrFail($observationId);
        $this->assertObservationMatches($intent, $observation);
        $intent = $this->transition->handle($intent, new FundingIntentTransitionData(
            status: FundingIntentStatus::Verifying,
            eventType: 'reconciliation_observation_match_approved',
            actorType: $actorType,
            actorId: $actorId,
            expectedVersion: $intent->version,
            evidenceReference: 'provider-observation:'.$observation->getKey(),
            metadata: [
                'funding_reconciliation_case_id' => $case->getKey(),
            ],
        ));
        $intent = $this->transition->handle($intent, new FundingIntentTransitionData(
            status: FundingIntentStatus::Verified,
            eventType: 'reconciliation_provider_settlement_verified',
            actorType: $actorType,
            actorId: $actorId,
            expectedVersion: $intent->version,
            evidenceReference: 'provider-observation:'.$observation->getKey(),
            providerObservationId: $observation->getKey(),
            providerTransactionId: $observation->provider_transaction_id,
            metadata: [
                'funding_reconciliation_case_id' => $case->getKey(),
            ],
        ));
        $settlement = $this->settle->handle($intent);
        $this->resolveCase($case, 'observation_matched_and_settled', [
            'funding_settlement_id' => $settlement->getKey(),
            'provider_observation_id' => $observation->getKey(),
        ], $actorType, $actorId);

        return [
            'outcome' => 'observation_matched_and_settled',
            'funding_settlement_id' => $settlement->getKey(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function compensatePosting(
        FundingSuspenseCase $case,
        ?FundingIntent $intent,
        string $actorType,
        string $actorId,
    ): array {
        if ($intent === null || $intent->status !== FundingIntentStatus::Verified) {
            throw new InvalidArgumentException('Compensating a posting requires an already verified Funding Intent.');
        }

        $settlement = $this->settle->handle($intent);
        $this->resolveCase($case, 'verified_posting_compensated', [
            'funding_settlement_id' => $settlement->getKey(),
        ], $actorType, $actorId);

        return [
            'outcome' => 'verified_posting_compensated',
            'funding_settlement_id' => $settlement->getKey(),
        ];
    }

    private function assertObservationMatches(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
    ): void {
        $matches = $observation->provider_code === $intent->provider_code
            && $observation->provider_status === 'settled'
            && $observation->gross_amount_minor === $intent->expected_amount_minor
            && $observation->currency === $intent->currency
            && $observation->net_amount_minor > 0
            && $observation->settled_at !== null
            && data_get($observation->metadata, 'destination_verified') === true;

        if (! $matches) {
            throw new InvalidArgumentException('The provider observation does not exactly match the Funding Intent.');
        }
    }

    /**
     * @param  array<string, int|string>  $resolution
     */
    private function resolveCase(
        FundingSuspenseCase $case,
        string $resolutionCode,
        array $resolution,
        string $actorType,
        string $actorId,
    ): void {
        $case->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_type' => $actorType,
            'resolved_by_id' => $actorId,
            'resolution_code' => $resolutionCode,
            'resolution' => $resolution,
        ])->saveQuietly();
    }
}
