<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;

final class SpecificPaymentAttribution
{
    public function matches(PaymentAttempt $attempt, ObservedPaymentTransaction $transaction, ProviderFundingObservation $observation, ?int $reviewId = null, bool $allowReviewedOverpayment = false): bool
    {
        $reviewedCandidate = $reviewId !== null && DB::table('x_change_payment_transaction_reviews as r')
            ->join('x_change_payment_review_attempts as a', 'a.review_id', '=', 'r.id')
            ->where('r.id', $reviewId)->where('r.status', 'open')
            ->where('r.observed_payment_transaction_id', $transaction->getKey())
            ->where('a.payment_attempt_id', $attempt->getKey())->exists();

        if (($allowReviewedOverpayment && ! $reviewedCandidate)
            || (! $reviewedCandidate && (int) $transaction->payment_attempt_id !== (int) $attempt->getKey())
            || $transaction->provider_code !== $attempt->provider_code
            || $observation->provider_code !== $attempt->provider_code
            || $transaction->provider_transaction_id_ciphertext !== $observation->provider_transaction_id
            || $transaction->amount_minor !== $observation->gross_amount_minor
            || $transaction->currency !== $observation->currency
            || $observation->provider_status !== 'settled'
            || (! $allowReviewedOverpayment && $observation->gross_amount_minor !== $attempt->expected_amount_minor)
            || $observation->net_amount_minor !== $observation->gross_amount_minor
            || $observation->currency !== $attempt->currency
            || $observation->settled_at === null
            || data_get($observation->metadata, 'destination_verified') !== true
            || $attempt->funding_address_ciphertext === null
            || $observation->funding_address !== 'sha256:'.hash('sha256', $attempt->funding_address_ciphertext)
            || $attempt->instructions_created_at === null || $attempt->expires_at === null
            || $observation->occurred_at === null
            || $observation->occurred_at->lessThan($attempt->instructions_created_at)
            || $observation->occurred_at->greaterThan($attempt->expires_at)
            || $attempt->expires_at->addDays(max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7)))->isPast()) {
            return false;
        }

        $at = $observation->occurred_at;

        if ($reviewedCandidate) {
            return true;
        }

        return ! PaymentAttempt::query()->whereKeyNot($attempt->getKey())
            ->where('provider_code', $attempt->provider_code)->where('currency', $attempt->currency)
            ->where('expected_amount_minor', $attempt->expected_amount_minor)
            ->where('instructions_created_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $at))->exists()
            && ! FundingIntent::query()->where('provider_code', $attempt->provider_code)
                ->where('currency', $attempt->currency)->where('expected_amount_minor', $attempt->expected_amount_minor)
                ->where('instructions_created_at', '<=', $at)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $at))->exists();
    }
}
