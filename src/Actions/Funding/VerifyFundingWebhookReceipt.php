<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use Throwable;

class VerifyFundingWebhookReceipt
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly TransitionFundingIntent $transition,
        private readonly RecordProviderFundingObservation $recordObservation,
        private readonly OpenFundingSuspenseCase $openSuspenseCase,
    ) {}

    public function handle(WebhookReceipt $receipt): int
    {
        return Cache::lock(
            'x-change:funding-webhook-verification:'.$receipt->getKey(),
            (int) config('x-change.funding.verification_lock_seconds', 120),
        )->block(
            (int) config('x-change.funding.verification_lock_wait_seconds', 5),
            fn (): int => $this->verify($receipt),
        );
    }

    private function verify(WebhookReceipt $receipt): int
    {
        $receipt = WebhookReceipt::query()->findOrFail($receipt->getKey());

        if (! $receipt->signature_verified || $receipt->authentication_status !== 'authenticated') {
            return 0;
        }

        if ($receipt->processing_status === 'processed') {
            return 0;
        }

        $receipt->forceFill([
            'processing_status' => 'verifying',
            'error_message' => null,
        ])->save();

        try {
            $intents = $this->candidateIntents($receipt);

            if ($intents->isEmpty()) {
                $this->openSuspenseCase->handle(
                    provider: $receipt->provider_code,
                    reasonCode: 'authenticated_evidence_unmatched',
                    receipt: $receipt,
                    details: [
                        'webhook_receipt_id' => $receipt->getKey(),
                    ],
                );
                $receipt->forceFill([
                    'processing_status' => 'unmatched',
                    'processed_at' => now(),
                    'error_message' => 'No active Funding Intent matched the provider evidence.',
                ])->save();

                return 0;
            }

            $processed = 0;

            foreach ($intents as $intent) {
                $this->verifyIntent($intent, $receipt);
                $processed++;
            }

            $receipt->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return $processed;
        } catch (Throwable $exception) {
            $receipt->forceFill([
                'processing_status' => 'failed',
                'error_message' => 'Funding verification failed: '.class_basename($exception),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return Collection<int, FundingIntent>
     */
    private function candidateIntents(WebhookReceipt $receipt): Collection
    {
        return FundingIntent::query()
            ->where('provider_code', $receipt->provider_code)
            ->whereIn('status', [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
            ])
            ->when(
                $receipt->request_id !== null,
                fn ($query) => $query->where('provider_request_id', $receipt->request_id),
            )
            ->oldest('id')
            ->limit((int) config('x-change.funding.verification_candidate_limit', 100))
            ->get();
    }

    private function verifyIntent(FundingIntent $intent, WebhookReceipt $receipt): void
    {
        if ($intent->status === FundingIntentStatus::AwaitingFunds) {
            $intent = $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::EvidenceReceived,
                eventType: 'provider_evidence_received',
                receipt: $receipt,
                intent: $intent,
            ));
        }

        if ($intent->status === FundingIntentStatus::EvidenceReceived) {
            $intent = $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::Verifying,
                eventType: 'provider_verification_started',
                receipt: $receipt,
                intent: $intent,
            ));
        }

        try {
            $observationData = $this->providers
                ->for($intent->provider_code)
                ->verifyFunding(new FundingVerificationData(
                    provider: $intent->provider_code,
                    fundingIntentReference: $intent->reference,
                    expectedAmountMinor: $intent->expected_amount_minor,
                    currency: $intent->currency,
                    providerRequestId: $intent->provider_request_id,
                    fundingAddress: $intent->funding_address_ciphertext,
                    webhookReceiptId: $receipt->getKey(),
                ));
        } catch (ProviderFundingNotObserved) {
            $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::AwaitingFunds,
                eventType: 'provider_funds_not_observed',
                receipt: $receipt,
                intent: $intent,
            ));

            return;
        } catch (ProviderFundingVerificationIndeterminate) {
            $this->moveToSuspense(
                intent: $intent,
                receipt: $receipt,
                reasonCode: 'provider_verification_indeterminate',
            );

            return;
        }

        $observation = $this->recordObservation->handle($observationData);
        $targetStatus = $this->targetStatus($intent, $observation);
        $eventType = match ($targetStatus) {
            FundingIntentStatus::Verified => 'provider_settlement_verified',
            FundingIntentStatus::AwaitingFunds => 'provider_settlement_pending',
            FundingIntentStatus::Suspense => 'provider_evidence_mismatch',
            default => throw new \LogicException('Unsupported Funding Intent verification result.'),
        };

        if ($targetStatus === FundingIntentStatus::Suspense) {
            $this->moveToSuspense(
                intent: $intent,
                receipt: $receipt,
                reasonCode: 'provider_evidence_mismatch',
                observation: $observation,
                details: [
                    'provider_status' => $observation->provider_status,
                    'gross_amount_minor' => $observation->gross_amount_minor,
                    'net_amount_minor' => $observation->net_amount_minor,
                    'currency' => $observation->currency,
                    'destination_verified' => data_get($observation->metadata, 'destination_verified') === true,
                ],
            );

            return;
        }

        $this->transition->handle($intent, $this->transitionData(
            status: $targetStatus,
            eventType: $eventType,
            receipt: $receipt,
            intent: $intent,
            observation: $observation,
        ));
    }

    /**
     * @param  array<string, bool|int|string|null>  $details
     */
    private function moveToSuspense(
        FundingIntent $intent,
        WebhookReceipt $receipt,
        string $reasonCode,
        ?ProviderFundingObservation $observation = null,
        array $details = [],
    ): void {
        DB::transaction(function () use ($intent, $receipt, $reasonCode, $observation, $details): void {
            $suspended = $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::Suspense,
                eventType: $reasonCode,
                receipt: $receipt,
                intent: $intent,
                observation: $observation,
            ));

            $this->openSuspenseCase->handle(
                provider: $receipt->provider_code,
                reasonCode: $reasonCode,
                intent: $suspended,
                receipt: $receipt,
                observation: $observation,
                details: [
                    'webhook_receipt_id' => $receipt->getKey(),
                    ...$details,
                ],
            );
        }, attempts: 3);
    }

    private function targetStatus(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
    ): FundingIntentStatus {
        if ($observation->provider_status !== 'settled') {
            return in_array($observation->provider_status, ['pending', 'processing'], true)
                ? FundingIntentStatus::AwaitingFunds
                : FundingIntentStatus::Suspense;
        }

        $matches = $observation->gross_amount_minor === $intent->expected_amount_minor
            && $observation->currency === $intent->currency
            && $observation->settled_at !== null
            && data_get($observation->metadata, 'destination_verified') === true;

        return $matches
            ? FundingIntentStatus::Verified
            : FundingIntentStatus::Suspense;
    }

    private function transitionData(
        FundingIntentStatus $status,
        string $eventType,
        WebhookReceipt $receipt,
        FundingIntent $intent,
        ?ProviderFundingObservation $observation = null,
    ): FundingIntentTransitionData {
        return new FundingIntentTransitionData(
            status: $status,
            eventType: $eventType,
            actorType: 'provider_webhook',
            actorId: $receipt->provider_code,
            expectedVersion: $intent->version,
            evidenceReference: $observation === null
                ? 'webhook-receipt:'.$receipt->getKey()
                : 'provider-observation:'.$observation->getKey(),
            providerObservationId: $observation?->getKey(),
            providerTransactionId: $observation?->provider_transaction_id,
            metadata: [
                'webhook_receipt_id' => $receipt->getKey(),
                'provider' => $receipt->provider_code,
            ],
        );
    }
}
