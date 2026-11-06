<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Payment\ProviderEvidenceLock;
use LBHurtado\XChange\Services\Payment\SpecificPaymentAttribution;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use RuntimeException;

final readonly class ApplyReviewedSplitPayment
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
        private RecordProviderFundingObservation $observations,
        private SpecificPaymentAttribution $attribution,
        private ProviderEvidenceLock $evidenceLock,
        private DocumentPaymentGate $documents,
        private VoucherCollectionProgressService $progress,
        private PayCodeCommercialSaleService $sales,
        private TransitionPaymentAttempt $transition,
        private V2EvidenceEventJournal $events,
    ) {}

    public function handle(int $reviewId, int $attemptId, Model $operator, string $reason, int $expectedCollectionMinor, int $expectedExcessMinor): VoucherCollection
    {
        if (mb_strlen(trim($reason)) < 8) {
            throw new RuntimeException('A reason is required for a split payment.');
        }
        $review = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->first();
        if ($review === null) {
            throw new RuntimeException('Payment review is unavailable.');
        }
        if ($review->status === 'resolved' && $review->resolution_code === 'split_payment'
            && (int) $review->resolution_payment_attempt_id === $attemptId) {
            $this->assertReplayAmounts($reviewId, $expectedCollectionMinor, $expectedExcessMinor);

            return VoucherCollection::query()->findOrFail($review->resolution_voucher_collection_id);
        }
        if ($review->status !== 'open' || ! DB::table('x_change_payment_review_attempts')
            ->where('review_id', $reviewId)->where('payment_attempt_id', $attemptId)->exists()) {
            throw new RuntimeException('The selected payment attempt is not an open review candidate.');
        }
        $transaction = ObservedPaymentTransaction::query()->findOrFail($review->observed_payment_transaction_id);
        $attempt = PaymentAttempt::query()->findOrFail($attemptId);
        $provider = $this->providers->for($attempt->provider_code);
        if (! $provider instanceof SpecificIncomingPaymentVerifier || $attempt->instructions_created_at === null || $attempt->expires_at === null) {
            throw new RuntimeException('The payment cannot be reverified for the selected attempt.');
        }
        $observation = $this->observations->handle($provider->verifyIncomingPayment(new FundingVerificationData(
            provider: $attempt->provider_code, fundingIntentReference: $attempt->reference,
            expectedAmountMinor: $transaction->amount_minor, currency: $attempt->currency,
            providerRequestId: $attempt->provider_request_id_ciphertext, fundingAddress: $attempt->funding_address_ciphertext,
            destination: FundingDestinationSnapshot::toData($attempt->destination_snapshot_ciphertext),
            observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(), observedBefore: now()->toDateTimeImmutable(),
        ), $transaction->provider_transaction_id_ciphertext));

        return DB::transaction(function () use ($reviewId, $attemptId, $operator, $reason, $transaction, $observation, $expectedCollectionMinor, $expectedExcessMinor): VoucherCollection {
            $case = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            if ($case !== null && $case->status === 'resolved' && $case->resolution_code === 'split_payment'
                && (int) $case->resolution_payment_attempt_id === $attemptId) {
                $this->assertReplayAmounts($reviewId, $expectedCollectionMinor, $expectedExcessMinor);

                return VoucherCollection::query()->findOrFail($case->resolution_voucher_collection_id);
            }
            if ($case === null || $case->status !== 'open' || (int) $case->observed_payment_transaction_id !== (int) $transaction->id) {
                throw new RuntimeException('Payment review is no longer available.');
            }
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if (! $this->attribution->matches($attempt, $transaction, $observation, $reviewId, allowReviewedOverpayment: true)) {
                throw new RuntimeException('The provider evidence does not match the selected attempt.');
            }
            $allowed = [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying, PaymentAttemptStatus::Expired, PaymentAttemptStatus::Settled];
            $providerAmbiguity = $attempt->status === PaymentAttemptStatus::Suspense
                && $attempt->events()->reorder()->latest('sequence')->value('event_type') === 'provider_verification_indeterminate';
            if (! $providerAmbiguity && ! in_array($attempt->status, $allowed, true)) {
                throw new RuntimeException('The selected payment attempt is not eligible for a split.');
            }
            $this->evidenceLock->acquire($observation->provider_code, $observation->provider_transaction_id);
            $evidenceHash = hash('sha256', implode('|', [$observation->provider_code, $observation->provider_transaction_id]));
            if (FundingEvidenceClaim::query()->where('provider_code', $observation->provider_code)->where('provider_transaction_hash', $evidenceHash)->exists()
                || VoucherCollection::query()->where('provider', $observation->provider_code)->where('provider_transaction_id', $observation->provider_transaction_id)->exists()) {
                throw new RuntimeException('The provider payment has already been applied.');
            }
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($attempt->voucher_id);
            $this->documents->ensureReady($voucher, lockEnvelope: true);
            if ($voucher->isExpired() || $voucher->isClosed() || $voucher->isCancelled()
                || (in_array($voucher->voucher_type, [VoucherType::PAYABLE, VoucherType::SETTLEMENT], true) && ! $voucher->canAcceptPayment())
                || data_get($voucher->metadata, 'instructions.metadata.custom.lead_campaign.campaign_reference') !== null) {
                throw new RuntimeException('The Pay Code cannot accept this payment.');
            }
            $principal = $this->progress->compute($voucher)->remaining_to_collect_minor;
            $fee = $this->sales->outstandingCollectionFee($voucher);
            $collectionAmount = $principal + $fee;
            $excess = $observation->gross_amount_minor - $collectionAmount;
            if ($principal <= 0 || $excess <= 0 || $collectionAmount !== $expectedCollectionMinor || $excess !== $expectedExcessMinor) {
                throw new RuntimeException('This payment does not require a collection and excess split.');
            }
            $wasSettled = $attempt->status === PaymentAttemptStatus::Settled;
            if (! $wasSettled) {
                $attempt = $this->transition->handle($attempt, PaymentAttemptStatus::Verified, 'provider_payment_verified', PaymentVerificationTrigger::Operator,
                    ['matched_observation_id' => $observation->id, 'provider_transaction_id' => $observation->provider_transaction_id,
                        'verified_at' => now(), 'last_checked_at' => now()],
                    ['review_id' => $reviewId, 'provider_status' => $observation->provider_status]);
            }
            $splitId = DB::table('x_change_reviewed_payment_splits')->insertGetId([
                'review_id' => $reviewId, 'voucher_id' => $voucher->id, 'provider_observation_id' => $observation->id,
                'provider_code' => $observation->provider_code, 'provider_transaction_hash' => hash('sha256', $observation->provider_transaction_id),
                'gross_amount_minor' => $observation->gross_amount_minor, 'collection_amount_minor' => $collectionAmount,
                'excess_amount_minor' => $excess, 'currency' => $observation->currency, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $key = $wasSettled
                ? 'reviewed-split:'.$reviewId : 'payment-attempt:'.$attempt->reference;
            $execution = app(ExecutionEngine::class)->execute(new ExecutionContextData(
                contact: null, voucherCode: (string) $voucher->code,
                meta: ['operation' => 'collect', 'amount_minor' => $collectionAmount, 'currency' => $observation->currency,
                    'provider' => $observation->provider_code, 'provider_reference' => $key,
                    'provider_transaction_id' => $observation->provider_transaction_id, 'provider_observation_id' => $observation->id,
                    'verification_source' => $observation->verification_source, 'idempotency_key' => $key, 'reviewed_split_id' => $splitId],
                voucher: $voucher, instruction: ExecutionInstructionData::from(['driver' => 'payable_collection']),
                correlation: ['execution_id' => hash('sha256', $key)],
            ));
            if (! $execution->successful || (int) ($execution->metadata['voucher_collection_id'] ?? 0) <= 0) {
                throw new RuntimeException('The split collection could not be completed.');
            }
            $collection = VoucherCollection::query()->whereKey($execution->metadata['voucher_collection_id'])
                ->where('voucher_id', $voucher->id)->where('idempotency_key', $key)->sole();
            $this->events->paymentCollected($voucher->refresh(), $collection);
            if (! $wasSettled) {
                $this->transition->handle($attempt, PaymentAttemptStatus::Settled, 'reviewed_split_payment_settled', PaymentVerificationTrigger::Operator,
                    ['voucher_collection_id' => $collection->id, 'settled_at' => now()],
                    ['review_id' => $reviewId, 'reviewed_split_id' => $splitId]);
            }
            DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->update([
                'status' => 'resolved', 'resolution_code' => 'split_payment', 'resolution_payment_attempt_id' => $attemptId,
                'resolution_voucher_collection_id' => $collection->id, 'provider_funding_observation_id' => $observation->id,
                'resolved_by_type' => $operator->getMorphClass(), 'resolved_by_id' => (string) $operator->getKey(),
                'resolution_note_ciphertext' => Crypt::encryptString($reason), 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return $collection;
        }, 5);
    }

    private function assertReplayAmounts(int $reviewId, int $collection, int $excess): void
    {
        $split = DB::table('x_change_reviewed_payment_splits')->where('review_id', $reviewId)->first();
        if ($split === null || (int) $split->collection_amount_minor !== $collection || (int) $split->excess_amount_minor !== $excess) {
            throw new RuntimeException('The approved split does not match the earlier decision.');
        }
    }
}
