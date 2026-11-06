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
use LBHurtado\XChange\Actions\Payment\SettleAdditionalQrPayment;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Exceptions\LatePaymentRequiresReview;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use LBHurtado\XChange\Queue\Concerns\ProviderVerificationRetryPolicy;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
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
            if ($attempt->status !== PaymentAttemptStatus::Settled) {
                $attempt = $verify->handleSpecificObservation($attempt, $transaction, $observation);
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
