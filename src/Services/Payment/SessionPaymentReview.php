<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;

final readonly class SessionPaymentReview
{
    public function __construct(private PaymentAttemptSessionGuard $sessions) {}

    public function exists(Voucher $voucher, string $browserKey): bool
    {
        if (trim($browserKey) === '') {
            return false;
        }

        return DB::table('x_change_payment_transaction_reviews as r')
            ->join('x_change_observed_payment_transactions as t', 't.id', '=', 'r.observed_payment_transaction_id')
            ->join('x_change_payment_attempts as a', 'a.id', '=', 't.payment_attempt_id')
            ->where(function ($query) use ($browserKey, $voucher): void {
                $hash = $this->sessions->hash($browserKey);
                $query->where(fn ($own) => $own->where('a.voucher_id', $voucher->getKey())->where('a.session_key_hash', $hash))
                    ->orWhereExists(function ($related) use ($hash, $voucher): void {
                        $related->selectRaw('1')->from('x_change_payment_review_attempts as ra')
                            ->join('x_change_payment_attempts as other', 'other.id', '=', 'ra.payment_attempt_id')
                            ->whereColumn('ra.review_id', 'r.id')->where('other.voucher_id', $voucher->getKey())
                            ->where('other.session_key_hash', $hash);
                    });
            })
            ->where('r.status', 'open')->exists();
    }
}
