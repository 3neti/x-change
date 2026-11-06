<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Operations\RecordExternalJobFailure;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Exceptions\LatePaymentRequiresReview;
use LBHurtado\XChange\Exceptions\ProviderPaymentAlreadyApplied;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Payment\LatePaymentEligibility;
use LBHurtado\XChange\Services\Payment\SpecificPaymentAttribution;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use Throwable;

class VerifyPaymentAttempt
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly RecordProviderFundingObservation $recordObservation,
        private readonly TransitionPaymentAttempt $transition,
        private readonly SettleVerifiedPaymentAttempt $settle,
        private readonly RecordExternalJobFailure $failures,
        private readonly LatePaymentEligibility $lateEligibility,
        private readonly SpecificPaymentAttribution $specificAttribution,
    ) {}

    public function handleSpecificObservation(PaymentAttempt $attempt, ObservedPaymentTransaction $transaction, ProviderFundingObservation $observation, ?int $reviewId = null): PaymentAttempt
    {
        return Cache::lock(
            'x-change:payment-verification:'.$attempt->getKey(),
            (int) config('x-change.payment.attempts.verification_lock_seconds', 120),
        )->block((int) config('x-change.payment.attempts.verification_lock_wait_seconds', 5), function () use ($attempt, $transaction, $observation, $reviewId): PaymentAttempt {
            return DB::transaction(function () use ($attempt, $transaction, $observation, $reviewId): PaymentAttempt {
                $locked = PaymentAttempt::query()->with('voucher')->lockForUpdate()->findOrFail($attempt->getKey());
                if ($locked->status === PaymentAttemptStatus::Settled) {
                    return $locked;
                }
                if ($locked->status === PaymentAttemptStatus::Verified) {
                    if ($reviewId !== null && $locked->provider_transaction_id !== $observation->provider_transaction_id) {
                        throw new LatePaymentRequiresReview('The attempt has a different verified payment.');
                    }

                    return $this->settleVerified($locked, PaymentVerificationTrigger::Schedule, false);
                }
                $ambiguous = $locked->status === PaymentAttemptStatus::Suspense
                    && $locked->events()->reorder()->latest('sequence')->value('event_type') === 'provider_verification_indeterminate';
                if (! $ambiguous && ! in_array($locked->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying, PaymentAttemptStatus::Expired], true)) {
                    throw new LatePaymentRequiresReview('The payment attempt requires review.');
                }
                if (! $this->specificAttribution->matches($locked, $transaction, $observation, $reviewId)
                    || ! $this->lateEligibility->canApply($locked, $locked->voucher, $observation)) {
                    throw new LatePaymentRequiresReview('The payment cannot be uniquely attributed or is no longer eligible.');
                }
                $verified = $this->transition->handle($locked, PaymentAttemptStatus::Verified, 'provider_payment_verified', PaymentVerificationTrigger::Schedule, [
                    'matched_observation_id' => $observation->getKey(),
                    'provider_transaction_id' => $observation->provider_transaction_id,
                    'verified_at' => now(), 'last_checked_at' => now(),
                ], ['provider_status' => $observation->provider_status]);

                return $this->settleVerified($verified, PaymentVerificationTrigger::Schedule, true);
            }, 5);
        });
    }

    public function handle(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
    ): PaymentAttempt {
        return Cache::lock(
            'x-change:payment-verification:'.$attempt->getKey(),
            (int) config('x-change.payment.attempts.verification_lock_seconds', 120),
        )->block(
            (int) config('x-change.payment.attempts.verification_lock_wait_seconds', 5),
            fn (): PaymentAttempt => $this->verify(
                PaymentAttempt::query()->with('voucher')->findOrFail($attempt->getKey()),
                $trigger,
            ),
        );
    }

    private function verify(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
    ): PaymentAttempt {
        if ($attempt->status === PaymentAttemptStatus::Settled) {
            return $attempt->load('events');
        }

        if ($attempt->status === PaymentAttemptStatus::Verified) {
            return $this->settleVerified($attempt, $trigger, false);
        }

        $expiryBoundary = $attempt->expires_at?->addSeconds(
            max(0, (int) config('x-change.payment.attempts.settlement_grace_seconds', 300)),
        );

        $lateReconciliation = $attempt->status === PaymentAttemptStatus::Expired;
        if ($lateReconciliation && ($attempt->expires_at === null
            || $attempt->expires_at->addDays(max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7)))->isPast())) {
            return $attempt->load('events');
        }

        if (! $lateReconciliation && $expiryBoundary?->isPast()
            && in_array($attempt->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying], true)) {
            return $this->transition->handle(
                $attempt,
                PaymentAttemptStatus::Expired,
                'payment_attempt_expired',
                $trigger,
                [
                    'expired_at' => now(),
                    'last_checked_at' => now(),
                ],
            );
        }

        if (! $lateReconciliation && ! in_array($attempt->status, [
            PaymentAttemptStatus::AwaitingPayment,
            PaymentAttemptStatus::Verifying,
        ], true)) {
            return $attempt->load('events');
        }

        if ($attempt->status === PaymentAttemptStatus::AwaitingPayment) {
            $attempt = $this->transition->handle(
                $attempt,
                PaymentAttemptStatus::Verifying,
                'provider_verification_started',
                $trigger,
                ['last_checked_at' => now()],
            );
        }

        try {
            $observationData = $this->providers
                ->for($attempt->provider_code)
                ->verifyFunding(new FundingVerificationData(
                    provider: $attempt->provider_code,
                    fundingIntentReference: $attempt->reference,
                    expectedAmountMinor: $attempt->expected_amount_minor,
                    currency: $attempt->currency,
                    providerRequestId: $attempt->provider_request_id_ciphertext,
                    fundingAddress: $attempt->funding_address_ciphertext,
                    destination: FundingDestinationSnapshot::toData(
                        $attempt->destination_snapshot_ciphertext,
                    ),
                ));
        } catch (ProviderFundingNotObserved) {
            return $this->awaitPayment($attempt, $trigger, 'provider_payment_not_observed');
        } catch (ProviderFundingVerificationIndeterminate) {
            return $this->suspense($attempt, $trigger, 'provider_verification_indeterminate');
        } catch (Throwable $exception) {
            return $this->transition->handle(
                $attempt,
                $lateReconciliation ? PaymentAttemptStatus::Expired : PaymentAttemptStatus::AwaitingPayment,
                'provider_verification_unavailable',
                $trigger,
                ['last_checked_at' => now()],
                ['failure_type' => class_basename($exception)],
            );
        }

        $observation = $this->recordObservation->handle($observationData);

        if (in_array($observation->provider_status, ['pending', 'processing'], true)) {
            return $this->awaitPayment($attempt, $trigger, 'provider_payment_pending', $observation);
        }

        if (! $this->matches($attempt, $observation)) {
            return $this->suspense(
                $attempt,
                $trigger,
                'provider_payment_mismatch',
                $observation,
            );
        }

        $discoveredAfterExpiry = $attempt->expires_at?->isPast() ?? false;
        if ($discoveredAfterExpiry && (! $this->onTime($attempt, $observation)
            || ! $this->lateEligibility->canApply($attempt, $attempt->voucher, $observation))) {
            return $this->suspense($attempt, $trigger, 'late_payment_requires_review', $observation);
        }

        $verified = $this->transition->handle(
            $attempt,
            PaymentAttemptStatus::Verified,
            'provider_payment_verified',
            $trigger,
            [
                'matched_observation_id' => $observation->getKey(),
                'provider_transaction_id' => $observation->provider_transaction_id,
                'verified_at' => now(),
                'last_checked_at' => now(),
            ],
            ['provider_status' => $observation->provider_status],
        );

        return $this->settleVerified($verified, $trigger, true);
    }

    private function settleVerified(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
        bool $providerCalls,
    ): PaymentAttempt {
        try {
            return $this->settle->handle($attempt, $trigger);
        } catch (LatePaymentRequiresReview) {
            return $this->suspense($attempt, $trigger, 'late_payment_requires_review', ProviderFundingObservation::query()->find($attempt->matched_observation_id));
        } catch (ProviderPaymentAlreadyApplied) {
            return $this->suspense($attempt, $trigger, 'provider_payment_already_applied', ProviderFundingObservation::query()->find($attempt->matched_observation_id));
        } catch (VoucherRequiresSettlementEnvelope) {
            return $this->suspense($attempt, $trigger, 'payment_documents_require_review', ProviderFundingObservation::query()->find($attempt->matched_observation_id));
        } catch (Throwable $exception) {
            $providerTransactionId = trim((string) $attempt->provider_transaction_id);

            try {
                $this->failures->handle(
                    jobType: 'VerifyPaymentAttemptSettlement',
                    subjectType: 'payment_attempt',
                    subjectId: $attempt->getKey(),
                    failure: $exception,
                    providerCode: $attempt->provider_code,
                    trigger: $trigger->value,
                    metadata: [
                        'payment_attempt_reference' => (string) $attempt->reference,
                        'voucher_reference' => 'voucher:'.$attempt->voucher_id,
                        'provider' => $attempt->provider_code,
                        'provider_observation_id' => $attempt->matched_observation_id,
                        'provider_transaction_hash' => $providerTransactionId === ''
                            ? null
                            : hash('sha256', $providerTransactionId),
                        'failure_type' => class_basename($exception),
                        'state' => PaymentAttemptStatus::Verified->value,
                        'retry_eligible' => true,
                        'provider_calls' => $providerCalls,
                        'provider_inventory_changed' => false,
                        'treasury_position_changed' => false,
                        'voucher_collection_changed' => false,
                        'compatibility_wallet_changed' => false,
                    ],
                );
            } catch (Throwable $recordingFailure) {
                report($recordingFailure);
            }

            throw $exception;
        }
    }

    private function matches(
        PaymentAttempt $attempt,
        ProviderFundingObservation $observation,
    ): bool {
        return $observation->provider_status === 'settled'
            && $observation->gross_amount_minor === $attempt->expected_amount_minor
            && $observation->net_amount_minor === $attempt->expected_amount_minor
            && $observation->currency === $attempt->currency
            && $observation->settled_at !== null
            && data_get($observation->metadata, 'destination_verified') === true;
    }

    private function onTime(PaymentAttempt $attempt, ProviderFundingObservation $observation): bool
    {
        return $attempt->instructions_created_at !== null
            && $attempt->expires_at !== null
            && $observation->occurred_at !== null
            && $observation->occurred_at->greaterThanOrEqualTo($attempt->instructions_created_at)
            && $observation->occurred_at->lessThanOrEqualTo($attempt->expires_at);
    }

    private function awaitPayment(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
        string $eventType,
        ?ProviderFundingObservation $observation = null,
    ): PaymentAttempt {
        return $this->transition->handle(
            $attempt,
            $attempt->status === PaymentAttemptStatus::Expired ? PaymentAttemptStatus::Expired : PaymentAttemptStatus::AwaitingPayment,
            $eventType,
            $trigger,
            ['last_checked_at' => now()],
            $observation === null ? [] : [
                'provider_status' => $observation->provider_status,
            ],
        );
    }

    private function suspense(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
        string $eventType,
        ?ProviderFundingObservation $observation = null,
    ): PaymentAttempt {
        return $this->transition->handle(
            $attempt,
            PaymentAttemptStatus::Suspense,
            $eventType,
            $trigger,
            [
                'matched_observation_id' => $observation?->getKey(),
                'provider_transaction_id' => $observation?->provider_transaction_id,
                'last_checked_at' => now(),
            ],
            $observation === null ? [] : [
                'provider_status' => $observation->provider_status,
                'amount_matches' => $observation->gross_amount_minor === $attempt->expected_amount_minor,
                'currency_matches' => $observation->currency === $attempt->currency,
                'destination_verified' => data_get($observation->metadata, 'destination_verified') === true,
            ],
        );
    }
}
