<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\XChange\Actions\Operations\RecordExternalJobFailure;
use LBHurtado\XChange\Actions\Payment\CreditAssignedPaymentFallback;
use LBHurtado\XChange\Actions\Payment\SettleAdditionalQrPayment;
use LBHurtado\XChange\Actions\Payment\TransitionPaymentAttempt;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Exceptions\LatePaymentRequiresReview;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use LBHurtado\XChange\Queue\Concerns\ProviderVerificationRetryPolicy;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use Throwable;

final class VerifyAdditionalQrPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasSafeQueueTags;
    use ProviderVerificationRetryPolicy;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $transactionId, public readonly string $providerCode)
    {
        $this->onQueue(VerifyPaymentAttemptJob::Queue);
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'payment-transaction:'.$this->transactionId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(5)->expireAfter($this->uniqueFor)->shared(),
            new RateLimited('x-change-provider-verification')];
    }

    public function handle(FundingProviderAdapterRegistry $providers, RecordProviderFundingObservation $record, SettleAdditionalQrPayment $settle, VerifyPaymentAttempt $verify): void
    {
        $transaction = ObservedPaymentTransaction::query()->with('paymentAttempt')->findOrFail($this->transactionId);
        $attempt = $transaction->paymentAttempt;
        if ($attempt->provider_code !== $this->providerCode) {
            return;
        }
        if (DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $transaction->getKey())->exists()
            || VoucherCollection::query()->where('provider', $transaction->provider_code)
                ->where('provider_transaction_id', $transaction->provider_transaction_id_ciphertext)->exists()) {
            return;
        }
        $provider = $providers->for($attempt->provider_code);
        if (! $provider instanceof SpecificIncomingPaymentVerifier || $attempt->instructions_created_at === null || $attempt->expires_at === null) {
            return;
        }
        try {
            $data = $provider->verifyIncomingPayment(new FundingVerificationData(
                provider: $attempt->provider_code, fundingIntentReference: $attempt->reference,
                expectedAmountMinor: $attempt->expected_amount_minor, currency: $attempt->currency,
                providerRequestId: $attempt->provider_request_id_ciphertext,
                fundingAddress: $attempt->funding_address_ciphertext,
                destination: FundingDestinationSnapshot::toData($attempt->destination_snapshot_ciphertext),
                observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(),
                observedBefore: now()->toDateTimeImmutable(),
            ), $transaction->provider_transaction_id_ciphertext);
        } catch (ProviderFundingNotObserved) {
            return;
        } catch (ProviderFundingVerificationIndeterminate) {
            $this->review($transaction, null, 'provider_evidence_ambiguous');

            return;
        }
        $observation = $record->handle($data);
        if (in_array($observation->provider_status, ['pending', 'processing'], true)) {
            return;
        }
        try {
            $credited = DB::transaction(function () use ($attempt, $transaction, $observation): bool {
                $locked = PaymentAttempt::query()->with('voucher')->lockForUpdate()->findOrFail($attempt->getKey());
                if (data_get($locked->destination_snapshot_ciphertext, 'mode') !== 'dedicated'
                    || $locked->expires_at === null || $locked->expires_at->isPast()
                    || $transaction->provider_code !== $observation->provider_code
                    || $transaction->provider_transaction_id_ciphertext !== $observation->provider_transaction_id
                    || $transaction->amount_minor !== $observation->gross_amount_minor
                    || $transaction->currency !== $observation->currency
                    || $observation->provider_status !== 'settled' || $observation->settled_at === null
                    || $observation->gross_amount_minor <= 0 || $observation->net_amount_minor <= 0
                    || $observation->net_amount_minor > $observation->gross_amount_minor
                    || data_get($observation->metadata, 'destination_verified') !== true
                    || $locked->funding_address_ciphertext === null
                    || ! hash_equals('sha256:'.hash('sha256', $locked->funding_address_ciphertext), (string) $observation->funding_address)
                    || $locked->instructions_created_at === null || $observation->occurred_at === null
                    || $observation->occurred_at->lessThan($locked->instructions_created_at)
                    || $observation->occurred_at->greaterThan($locked->expires_at)) {
                    return false;
                }
                $firstMismatch = in_array($locked->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying], true)
                    && ($observation->gross_amount_minor !== $locked->expected_amount_minor
                        || $observation->net_amount_minor !== $locked->expected_amount_minor);
                $remaining = app(VoucherCollectionProgressService::class)->compute($locked->voucher)->remaining_to_collect_minor;
                $completed = $locked->status === PaymentAttemptStatus::CreditedToClientFunds
                    || ($locked->status === PaymentAttemptStatus::Settled && ($remaining <= 0
                        || $observation->gross_amount_minor !== $locked->expected_amount_minor
                        || $observation->net_amount_minor !== $locked->expected_amount_minor
                        || $observation->gross_amount_minor > $remaining));
                if (! $firstMismatch && ! $completed) {
                    return false;
                }
                $intent = app(CreditAssignedPaymentFallback::class)->handle($locked, $observation);
                if ($firstMismatch) {
                    app(TransitionPaymentAttempt::class)->handle($locked, PaymentAttemptStatus::CreditedToClientFunds,
                        'payment_credited_to_client_funds', PaymentVerificationTrigger::Schedule,
                        ['matched_observation_id' => $observation->getKey(),
                            'provider_transaction_id' => $observation->provider_transaction_id, 'last_checked_at' => now()],
                        ['funding_intent_reference' => $intent->reference]);
                }

                return true;
            }, 5);
            if ($credited) {
                return;
            }
            if ($attempt->status !== PaymentAttemptStatus::Settled) {
                $attempt = $verify->handleSpecificObservation($attempt, $transaction, $observation);
                if ($attempt->status === PaymentAttemptStatus::CreditedToClientFunds
                    && (int) $attempt->matched_observation_id === (int) $observation->getKey()) {
                    return;
                }
                if ($attempt->status !== PaymentAttemptStatus::Settled) {
                    throw new LatePaymentRequiresReview('The first payment requires review.');
                }
                if ((int) $attempt->matched_observation_id === (int) $observation->getKey()) {
                    return;
                }
            }
            $settle->handle($transaction, $observation);
        } catch (LatePaymentRequiresReview) {
            $this->review($transaction, (int) $observation->getKey(), 'payment_attribution_or_eligibility');
        }
    }

    private function review(ObservedPaymentTransaction $transaction, ?int $observationId, string $reason): void
    {
        DB::transaction(function () use ($transaction, $observationId, $reason): void {
            DB::table('x_change_payment_transaction_reviews')->insertOrIgnore([
                'observed_payment_transaction_id' => $transaction->getKey(),
                'provider_funding_observation_id' => $observationId,
                'reason_code' => $reason, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $reviewId = DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $transaction->getKey())->value('id');
            DB::table('x_change_payment_review_attempts')->insertOrIgnore(['review_id' => $reviewId, 'payment_attempt_id' => $transaction->payment_attempt_id]);
        }, 3);
    }

    public function failed(Throwable $exception): void
    {
        app(RecordExternalJobFailure::class)->handle(jobType: class_basename(self::class), subjectType: 'observed_payment_transaction', subjectId: $this->transactionId, failure: $exception, trigger: 'schedule');
    }
}
