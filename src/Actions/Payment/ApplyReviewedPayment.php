<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Payment\SpecificPaymentAttribution;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use RuntimeException;

final readonly class ApplyReviewedPayment
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
        private RecordProviderFundingObservation $observations,
        private VerifyPaymentAttempt $verify,
        private SettleAdditionalQrPayment $settle,
        private SpecificPaymentAttribution $attribution,
    ) {}

    public function handle(int $reviewId, int $attemptId, Model $operator, string $reason): VoucherCollection
    {
        if (mb_strlen(trim($reason)) < 8) {
            throw new RuntimeException('A reason is required for a payment review.');
        }
        $review = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->first();
        if ($review === null) {
            throw new RuntimeException('Payment review is unavailable.');
        }
        if ($review->status === 'resolved' && $review->resolution_code === 'applied_to_attempt') {
            $collection = VoucherCollection::query()->findOrFail($review->resolution_voucher_collection_id);
            if ((int) $review->resolution_payment_attempt_id !== $attemptId) {
                throw new RuntimeException('Payment review was resolved for a different attempt.');
            }

            return $collection;
        }
        $candidate = DB::table('x_change_payment_review_attempts')->where('review_id', $reviewId)->where('payment_attempt_id', $attemptId)->exists();
        if ($review->status !== 'open' || ! $candidate) {
            throw new RuntimeException('The selected payment attempt is not a review candidate.');
        }
        $transaction = ObservedPaymentTransaction::query()->findOrFail($review->observed_payment_transaction_id);
        $attempt = PaymentAttempt::query()->findOrFail($attemptId);
        $provider = $this->providers->for($attempt->provider_code);
        if (! $provider instanceof SpecificIncomingPaymentVerifier || $attempt->instructions_created_at === null || $attempt->expires_at === null) {
            throw new RuntimeException('The payment cannot be reverified for the selected attempt.');
        }
        $evidence = $provider->verifyIncomingPayment(new FundingVerificationData(
            provider: $attempt->provider_code, fundingIntentReference: $attempt->reference,
            expectedAmountMinor: $transaction->amount_minor, currency: $attempt->currency,
            providerRequestId: $attempt->provider_request_id_ciphertext, fundingAddress: $attempt->funding_address_ciphertext,
            destination: FundingDestinationSnapshot::toData($attempt->destination_snapshot_ciphertext),
            observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(), observedBefore: now()->toDateTimeImmutable(),
        ), $transaction->provider_transaction_id_ciphertext);
        $observation = $this->observations->handle($evidence);

        return DB::transaction(function () use ($reviewId, $attemptId, $operator, $reason, $transaction, $observation): VoucherCollection {
            $case = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            if ($case !== null && $case->status === 'resolved' && $case->resolution_code === 'applied_to_attempt'
                && (int) $case->resolution_payment_attempt_id === $attemptId) {
                return VoucherCollection::query()->findOrFail($case->resolution_voucher_collection_id);
            }
            if ($case === null || $case->status !== 'open' || (int) $case->observed_payment_transaction_id !== (int) $transaction->getKey()
                || ! DB::table('x_change_payment_review_attempts')->where('review_id', $reviewId)->where('payment_attempt_id', $attemptId)->exists()) {
                throw new RuntimeException('Payment review is no longer available.');
            }
            $attempt = PaymentAttempt::query()->findOrFail($attemptId);
            if (! $this->attribution->matches($attempt, $transaction, $observation, $reviewId)) {
                throw new RuntimeException('The provider evidence does not match the selected attempt.');
            }
            if ($attempt->status !== PaymentAttemptStatus::Settled) {
                $attempt = $this->verify->handleSpecificObservation($attempt, $transaction, $observation, $reviewId);
            }
            $collection = VoucherCollection::query()->where('provider', $observation->provider_code)
                ->where('provider_transaction_id', $observation->provider_transaction_id)->first();
            if ($collection === null) {
                $collection = $this->settle->handle($transaction, $observation, $reviewId, $attemptId);
            }
            if ((int) $collection->voucher_id !== (int) $attempt->voucher_id) {
                throw new RuntimeException('The payment has already been applied elsewhere.');
            }
            DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->update([
                'status' => 'resolved', 'resolution_code' => 'applied_to_attempt',
                'resolution_payment_attempt_id' => $attemptId,
                'resolution_voucher_collection_id' => $collection->getKey(), 'provider_funding_observation_id' => $observation->getKey(),
                'resolved_by_type' => $operator->getMorphClass(), 'resolved_by_id' => (string) $operator->getKey(),
                'resolution_note_ciphertext' => Crypt::encryptString($reason), 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return $collection;
        }, 5);
    }
}
