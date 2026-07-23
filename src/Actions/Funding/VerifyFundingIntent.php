<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\MatchFundingPayerIdentity;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;
use Throwable;

class VerifyFundingIntent
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly TransitionFundingIntent $transition,
        private readonly RecordProviderFundingObservation $recordObservation,
        private readonly MatchFundingPayerIdentity $matchPayerIdentity,
        private readonly OpenFundingSuspenseCase $openSuspenseCase,
        private readonly ReverseSettledFundingIntent $reverseSettlement,
    ) {}

    public function handle(
        FundingIntent $intent,
        FundingIntentVerificationData $verification,
    ): FundingIntent {
        return Cache::lock(
            'x-change:funding-intent-verification:'.$intent->getKey(),
            (int) config('x-change.funding.verification_lock_seconds', 120),
        )->block(
            (int) config('x-change.funding.verification_lock_wait_seconds', 5),
            fn (): FundingIntent => $this->verify(
                FundingIntent::query()->findOrFail($intent->getKey()),
                $verification,
            ),
        );
    }

    private function verify(
        FundingIntent $intent,
        FundingIntentVerificationData $verification,
    ): FundingIntent {
        $receipt = $this->receipt($intent, $verification);

        if ($intent->status === FundingIntentStatus::Settled) {
            return $this->verifySettledIntent($intent, $verification, $receipt);
        }

        if (! in_array($intent->status, [
            FundingIntentStatus::AwaitingFunds,
            FundingIntentStatus::EvidenceReceived,
            FundingIntentStatus::Verifying,
            FundingIntentStatus::Verified,
        ], true)) {
            return $intent;
        }

        if ($intent->status === FundingIntentStatus::Verified) {
            return $intent;
        }

        if ($intent->status === FundingIntentStatus::AwaitingFunds) {
            $intent = $verification->trigger === FundingVerificationTrigger::Webhook
                ? $this->transition->handle($intent, $this->transitionData(
                    status: FundingIntentStatus::EvidenceReceived,
                    eventType: 'provider_evidence_received',
                    verification: $verification,
                    intent: $intent,
                    receipt: $receipt,
                ))
                : $this->transition->handle($intent, $this->transitionData(
                    status: FundingIntentStatus::Verifying,
                    eventType: 'provider_verification_started',
                    verification: $verification,
                    intent: $intent,
                    receipt: $receipt,
                ));
        }

        if ($intent->status === FundingIntentStatus::EvidenceReceived) {
            $intent = $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::Verifying,
                eventType: 'provider_verification_started',
                verification: $verification,
                intent: $intent,
                receipt: $receipt,
            ));
        }

        try {
            $observationData = $this->providers
                ->for($intent->provider_code)
                ->verifyFunding($this->providerVerification($intent, $receipt));
        } catch (ProviderFundingNotObserved) {
            return $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::AwaitingFunds,
                eventType: 'provider_funds_not_observed',
                verification: $verification,
                intent: $intent,
                receipt: $receipt,
            ));
        } catch (ProviderFundingVerificationIndeterminate) {
            return $this->moveToSuspense(
                intent: $intent,
                verification: $verification,
                reasonCode: 'provider_verification_indeterminate',
                receipt: $receipt,
            );
        } catch (Throwable $exception) {
            return $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::AwaitingFunds,
                eventType: 'provider_verification_unavailable',
                verification: $verification,
                intent: $intent,
                receipt: $receipt,
                metadata: [
                    'failure_type' => class_basename($exception),
                ],
            ));
        }

        $observation = $this->recordObservation->handle(
            $this->matchPayerIdentity->handle($intent, $observationData),
        );
        $targetStatus = $this->targetStatus($intent, $observation);

        if ($targetStatus === FundingIntentStatus::Suspense) {
            return $this->moveToSuspense(
                intent: $intent,
                verification: $verification,
                reasonCode: 'provider_evidence_mismatch',
                receipt: $receipt,
                observation: $observation,
                details: [
                    'provider_status' => $observation->provider_status,
                    'gross_amount_minor' => $observation->gross_amount_minor,
                    'net_amount_minor' => $observation->net_amount_minor,
                    'currency' => $observation->currency,
                    'destination_verified' => data_get($observation->metadata, 'destination_verified') === true,
                ],
            );
        }

        return $this->transition->handle($intent, $this->transitionData(
            status: $targetStatus,
            eventType: $targetStatus === FundingIntentStatus::Verified
                ? 'provider_settlement_verified'
                : 'provider_settlement_pending',
            verification: $verification,
            intent: $intent,
            receipt: $receipt,
            observation: $observation,
        ));
    }

    private function verifySettledIntent(
        FundingIntent $intent,
        FundingIntentVerificationData $verification,
        ?WebhookReceipt $receipt,
    ): FundingIntent {
        try {
            $observationData = $this->providers
                ->for($intent->provider_code)
                ->verifyFunding($this->providerVerification($intent, $receipt));
        } catch (ProviderFundingNotObserved) {
            return $intent;
        } catch (ProviderFundingVerificationIndeterminate) {
            $this->openSuspenseCase->handle(
                provider: $intent->provider_code,
                reasonCode: 'post_settlement_verification_indeterminate',
                intent: $intent,
                receipt: $receipt,
                details: $this->receiptDetails($receipt),
            );

            return $intent;
        } catch (Throwable) {
            return $intent;
        }

        $observation = $this->recordObservation->handle(
            $this->matchPayerIdentity->handle($intent, $observationData),
        );

        if ($observation->provider_status === 'settled') {
            return $intent;
        }

        if (in_array($observation->provider_status, ['reversed', 'refunded', 'charged_back'], true)) {
            try {
                $this->reverseSettlement->handle($intent, $observation);

                return $intent->refresh();
            } catch (FundingSettlementDenied) {
                $this->openSuspenseCase->handle(
                    provider: $intent->provider_code,
                    reasonCode: 'provider_reversal_mismatch',
                    intent: $intent,
                    receipt: $receipt,
                    observation: $observation,
                    details: [
                        ...$this->receiptDetails($receipt),
                        'provider_status' => $observation->provider_status,
                        'gross_amount_minor' => $observation->gross_amount_minor,
                        'net_amount_minor' => $observation->net_amount_minor,
                        'currency' => $observation->currency,
                    ],
                );

                return $intent;
            }
        }

        $this->openSuspenseCase->handle(
            provider: $intent->provider_code,
            reasonCode: 'post_settlement_status_changed',
            intent: $intent,
            receipt: $receipt,
            observation: $observation,
            details: [
                ...$this->receiptDetails($receipt),
                'provider_status' => $observation->provider_status,
                'trigger' => $verification->trigger->value,
            ],
        );

        return $intent;
    }

    /**
     * @param  array<string, bool|int|string|null>  $details
     */
    private function moveToSuspense(
        FundingIntent $intent,
        FundingIntentVerificationData $verification,
        string $reasonCode,
        ?WebhookReceipt $receipt = null,
        ?ProviderFundingObservation $observation = null,
        array $details = [],
    ): FundingIntent {
        return DB::transaction(function () use (
            $intent,
            $verification,
            $reasonCode,
            $receipt,
            $observation,
            $details,
        ): FundingIntent {
            $suspended = $this->transition->handle($intent, $this->transitionData(
                status: FundingIntentStatus::Suspense,
                eventType: $reasonCode,
                verification: $verification,
                intent: $intent,
                receipt: $receipt,
                observation: $observation,
            ));

            $this->openSuspenseCase->handle(
                provider: $intent->provider_code,
                reasonCode: $reasonCode,
                intent: $suspended,
                receipt: $receipt,
                observation: $observation,
                details: [
                    ...$this->receiptDetails($receipt),
                    ...$details,
                ],
            );

            return $suspended;
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
            && data_get($observation->metadata, 'destination_verified') === true
            && (
                data_get($observation->metadata, 'payer_identity_required') !== true
                || data_get($observation->metadata, 'payer_identity_matched') === true
            );

        return $matches
            ? FundingIntentStatus::Verified
            : FundingIntentStatus::Suspense;
    }

    private function transitionData(
        FundingIntentStatus $status,
        string $eventType,
        FundingIntentVerificationData $verification,
        FundingIntent $intent,
        ?WebhookReceipt $receipt = null,
        ?ProviderFundingObservation $observation = null,
        array $metadata = [],
    ): FundingIntentTransitionData {
        return new FundingIntentTransitionData(
            status: $status,
            eventType: $eventType,
            actorType: match ($verification->trigger) {
                FundingVerificationTrigger::Webhook => 'provider_webhook',
                FundingVerificationTrigger::Operator => 'operator',
                FundingVerificationTrigger::Schedule => 'system_scheduler',
            },
            actorId: $verification->actorId,
            expectedVersion: $intent->version,
            evidenceReference: match (true) {
                $observation !== null => 'provider-observation:'.$observation->getKey(),
                $receipt !== null => 'webhook-receipt:'.$receipt->getKey(),
                default => 'provider-query:'.$verification->trigger->value.':'.$intent->reference,
            },
            providerObservationId: $observation?->getKey(),
            providerTransactionId: $observation?->provider_transaction_id,
            metadata: [
                'trigger' => $verification->trigger->value,
                'provider' => $intent->provider_code,
                ...$this->receiptDetails($receipt),
                ...$metadata,
            ],
        );
    }

    private function providerVerification(
        FundingIntent $intent,
        ?WebhookReceipt $receipt,
    ): FundingVerificationData {
        return new FundingVerificationData(
            provider: $intent->provider_code,
            fundingIntentReference: $intent->reference,
            expectedAmountMinor: $intent->expected_amount_minor,
            currency: $intent->currency,
            providerRequestId: $intent->provider_request_id,
            fundingAddress: $intent->funding_address_ciphertext,
            webhookReceiptId: $receipt?->getKey(),
            destination: $this->destination($intent),
        );
    }

    private function receipt(
        FundingIntent $intent,
        FundingIntentVerificationData $verification,
    ): ?WebhookReceipt {
        if ($verification->trigger !== FundingVerificationTrigger::Webhook) {
            return null;
        }

        if ($verification->webhookReceiptId === null) {
            throw new LogicException('Webhook-triggered funding verification requires a receipt.');
        }

        $receipt = WebhookReceipt::query()->findOrFail($verification->webhookReceiptId);

        if (! $receipt->signature_verified
            || $receipt->authentication_status !== 'authenticated'
            || $receipt->provider_code !== $intent->provider_code) {
            throw new LogicException('Webhook-triggered funding verification requires authenticated provider evidence.');
        }

        return $receipt;
    }

    /**
     * @return array<string, int>
     */
    private function receiptDetails(?WebhookReceipt $receipt): array
    {
        return $receipt === null
            ? []
            : ['webhook_receipt_id' => (int) $receipt->getKey()];
    }

    private function destination(FundingIntent $intent): ?FundingDestinationData
    {
        $snapshot = $intent->destination_snapshot_ciphertext;

        return is_array($snapshot)
            ? FundingDestinationSnapshot::toData($snapshot)
            : null;
    }
}
