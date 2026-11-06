<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use RuntimeException;

final readonly class CreditReviewedExcess
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
        private RecordProviderFundingObservation $observations,
        private WalletAccessContract $wallets,
        private VoucherCollectionProgressService $progress,
        private SettleVerifiedFundingIntent $settle,
    ) {}

    public function handle(int $reviewId, Model $operator, string $reason): FundingIntent
    {
        if (mb_strlen(trim($reason)) < 8) {
            throw new RuntimeException('A reason is required for an excess payment credit.');
        }
        $review = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->first();
        if ($review === null) {
            throw new RuntimeException('Payment review is unavailable.');
        }
        if ($review->status === 'resolved' && $review->resolution_code === 'credited_client_funds') {
            return FundingIntent::query()->findOrFail($review->resolution_funding_intent_id);
        }
        $transaction = ObservedPaymentTransaction::query()->with('paymentAttempt')->findOrFail($review->observed_payment_transaction_id);
        $attempt = $transaction->paymentAttempt;
        $provider = $this->providers->for($attempt->provider_code);
        if (! $provider instanceof SpecificIncomingPaymentVerifier || $attempt->instructions_created_at === null || $attempt->expires_at === null) {
            throw new RuntimeException('The payment cannot be reverified for an excess credit.');
        }
        $evidence = $provider->verifyIncomingPayment(new FundingVerificationData(
            provider: $attempt->provider_code, fundingIntentReference: $attempt->reference,
            expectedAmountMinor: $transaction->amount_minor, currency: $attempt->currency,
            providerRequestId: $attempt->provider_request_id_ciphertext, fundingAddress: $attempt->funding_address_ciphertext,
            destination: FundingDestinationSnapshot::toData($attempt->destination_snapshot_ciphertext),
            observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(), observedBefore: now()->toDateTimeImmutable(),
        ), $transaction->provider_transaction_id_ciphertext);
        $observation = $this->observations->handle($evidence);

        return DB::transaction(function () use ($reviewId, $operator, $reason, $transaction, $attempt, $observation): FundingIntent {
            $case = DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->lockForUpdate()->first();
            if ($case->status === 'resolved' && $case->resolution_code === 'credited_client_funds') {
                return FundingIntent::query()->findOrFail($case->resolution_funding_intent_id);
            }
            if ($case->status !== 'open' || (int) $case->observed_payment_transaction_id !== (int) $transaction->getKey()) {
                throw new RuntimeException('Payment review is no longer open.');
            }
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($lockedAttempt->voucher_id);
            $otherCandidates = DB::table('x_change_payment_review_attempts')->where('review_id', $reviewId)
                ->where('payment_attempt_id', '!=', $lockedAttempt->getKey())->exists();
            $occurred = $observation->occurred_at;
            $competingAttempt = $occurred !== null && PaymentAttempt::query()->whereKeyNot($lockedAttempt->getKey())
                ->where('provider_code', $lockedAttempt->provider_code)->where('currency', $lockedAttempt->currency)
                ->where('expected_amount_minor', $observation->gross_amount_minor)->where('instructions_created_at', '<=', $occurred)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $occurred))->exists();
            $competingFunding = $occurred !== null && FundingIntent::query()->where('provider_code', $lockedAttempt->provider_code)
                ->where('currency', $lockedAttempt->currency)->where('expected_amount_minor', $observation->gross_amount_minor)
                ->where('instructions_created_at', '<=', $occurred)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $occurred))->exists();
            if ($otherCandidates || $competingAttempt || $competingFunding || $this->progress->compute($voucher)->remaining_to_collect_minor !== 0
                || $observation->provider_code !== $lockedAttempt->provider_code
                || $transaction->provider_code !== $lockedAttempt->provider_code
                || $transaction->currency !== $lockedAttempt->currency
                || $observation->provider_transaction_id !== $transaction->provider_transaction_id_ciphertext
                || $observation->provider_status !== 'settled' || $observation->settled_at === null
                || $observation->gross_amount_minor !== $transaction->amount_minor
                || $observation->net_amount_minor !== $transaction->amount_minor || $observation->net_amount_minor <= 0
                || $observation->currency !== $lockedAttempt->currency
                || data_get($observation->metadata, 'destination_verified') !== true
                || $lockedAttempt->funding_address_ciphertext === null
                || $observation->funding_address !== 'sha256:'.hash('sha256', $lockedAttempt->funding_address_ciphertext)
                || $observation->occurred_at === null || $lockedAttempt->instructions_created_at === null || $lockedAttempt->expires_at === null
                || $observation->occurred_at->lessThan($lockedAttempt->instructions_created_at)
                || $observation->occurred_at->greaterThan($lockedAttempt->expires_at)
                || $lockedAttempt->expires_at->copy()->addDays(max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7)))->isPast()) {
                throw new RuntimeException('The payment is not eligible for an excess credit.');
            }
            $owner = $voucher->owner;
            if (! $owner instanceof Model) {
                throw new RuntimeException('The issuer account is unavailable.');
            }
            $wallet = $this->wallets->resolveForUser($owner);
            if (! is_string($wallet->uuid ?? null) || $wallet->uuid === '') {
                throw new RuntimeException('The issuer account is unavailable.');
            }
            $key = hash('sha256', 'reviewed-excess:'.$reviewId);
            $intent = FundingIntent::query()->create([
                'account_reference' => 'wallet:'.$wallet->uuid, 'provider_code' => $lockedAttempt->provider_code,
                'purpose' => FundingIntentPurpose::ReviewedExcess, 'expected_amount_minor' => $observation->gross_amount_minor,
                'currency' => $observation->currency, 'status' => FundingIntentStatus::Verified, 'version' => 1,
                'idempotency_key_hash' => $key, 'idempotency_fingerprint' => hash('sha256', $key.'|'.$observation->getKey()),
                'created_by_type' => $operator->getMorphClass(), 'created_by_id' => (string) $operator->getKey(),
                'matched_observation_id' => $observation->getKey(), 'provider_transaction_id' => $observation->provider_transaction_id,
                'instructions_created_at' => $lockedAttempt->instructions_created_at, 'expires_at' => $lockedAttempt->expires_at,
                'evidence_received_at' => now(), 'verified_at' => now(), 'metadata' => ['payment_review_id' => $reviewId],
            ]);
            $this->settle->handle($intent);
            DB::table('x_change_payment_transaction_reviews')->where('id', $reviewId)->update([
                'status' => 'resolved', 'resolution_code' => 'credited_client_funds',
                'resolution_funding_intent_id' => $intent->getKey(), 'provider_funding_observation_id' => $observation->getKey(),
                'resolved_by_type' => $operator->getMorphClass(), 'resolved_by_id' => (string) $operator->getKey(),
                'resolution_note_ciphertext' => Crypt::encryptString($reason), 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return $intent->fresh();
        }, 5);
    }
}
