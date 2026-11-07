<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Disbursement\ReviewTreasuryPayoutOutbox;
use LBHurtado\XChange\Actions\Payment\ApplyReviewedPayment;
use LBHurtado\XChange\Actions\Payment\ApplyReviewedSplitPayment;
use LBHurtado\XChange\Actions\Payment\CreditReviewedExcess;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PaymentRecoveryController extends Controller
{
    public function investigateTreasuryPayout(Request $request, int $outbox, ReviewTreasuryPayoutOutbox $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $action->recordInvestigation($actor, $outbox, $data['reason']);

        return back()->with('status', 'Investigation recorded. The payout remains in review.');
    }

    public function reviewTreasuryPayout(Request $request, int $outbox, ReviewTreasuryPayoutOutbox $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $provider = DB::table('x_change_treasury_payout_outbox as o')
            ->join('disbursement_reconciliations as d', 'd.voucher_id', '=', 'o.voucher_id')
            ->where('o.id', $outbox)->where('o.status', 'review')->where('d.claim_type', 'withdraw')
            ->orderByDesc('d.id')->value('d.provider');
        if (is_string($provider) && trim($provider) !== '') {
            $this->limitProviderStatusCheck($provider);
        }
        try {
            $action->handle($actor, $outbox, $data['reason']);
        } catch (Throwable $exception) {
            Log::warning('Treasury payout review failed.', ['exception' => $exception::class]);

            return back()->withErrors(['recovery' => 'The provider status could not be confirmed. The payout remains in review.']);
        }

        return back()->with('status', 'Provider status checked. Any uncertain outcome remains in review.');
    }

    public function index(Request $request): Response
    {
        $transactionReviews = DB::table('x_change_payment_transaction_reviews as r')
            ->join('x_change_observed_payment_transactions as t', 't.id', '=', 'r.observed_payment_transaction_id')
            ->join('x_change_payment_attempts as a', 'a.id', '=', 't.payment_attempt_id')
            ->join('vouchers as v', 'v.id', '=', 'a.voucher_id')
            ->where('r.status', 'open')->orderByDesc('r.id')->limit(50)
            ->get(['r.id', 'r.reason_code', 'r.created_at', 'r.observed_payment_transaction_id', 'v.code', 't.amount_minor', 't.currency', 'a.last_checked_at']);
        $observations = ObservedPaymentTransaction::query()->with('statuses')
            ->whereIn('id', $transactionReviews->pluck('observed_payment_transaction_id'))->get()->keyBy('id');
        $candidates = DB::table('x_change_payment_review_attempts as c')
            ->join('x_change_payment_attempts as a', 'a.id', '=', 'c.payment_attempt_id')
            ->join('vouchers as v', 'v.id', '=', 'a.voucher_id')
            ->whereIn('c.review_id', $transactionReviews->pluck('id'))
            ->get(['c.review_id', 'a.id', 'a.reference', 'a.expected_amount_minor', 'a.currency', 'v.code', 'v.id as voucher_id']);
        $vouchers = Voucher::query()->whereIn('id', $candidates->pluck('voucher_id')->unique())->get()->keyBy('id');
        $candidates = $candidates->map(static function (object $candidate) use ($vouchers, $transactionReviews): array {
            $voucher = $vouchers->get($candidate->voucher_id);
            $review = $transactionReviews->firstWhere('id', $candidate->review_id);
            $collection = null;
            $excess = null;
            try {
                if ($voucher !== null && $review !== null) {
                    $principal = app(VoucherCollectionProgressService::class)->compute($voucher)->remaining_to_collect_minor;
                    $amount = $principal + app(PayCodeCommercialSaleService::class)->outstandingCollectionFee($voucher);
                    if ($principal > 0 && $review->amount_minor > $amount) {
                        $collection = $amount;
                        $excess = $review->amount_minor - $amount;
                    }
                }
            } catch (Throwable) {
                $collection = $excess = null;
            }

            return ['review_id' => $candidate->review_id, 'id' => $candidate->id, 'reference' => $candidate->reference,
                'code' => $candidate->code, 'expected_amount_minor' => $candidate->expected_amount_minor, 'currency' => $candidate->currency,
                'split_collection_minor' => $collection, 'split_excess_minor' => $excess];
        })->groupBy('review_id');
        $transactionReviews = $transactionReviews->map(static function (object $review) use ($observations, $candidates): array {
            $observed = $observations->get($review->observed_payment_transaction_id);

            return [
                'id' => $review->id, 'code' => $review->code, 'reason_code' => $review->reason_code,
                'created_at' => $review->created_at, 'amount_minor' => $review->amount_minor, 'currency' => $review->currency,
                'last_checked_at' => $review->last_checked_at,
                'provider_transaction_reference' => $observed?->provider_transaction_id_ciphertext,
                'provider_status' => $observed?->statuses->last()?->provider_status,
                'occurred_at' => $observed?->occurred_at?->toIso8601String(),
                'resolution_url' => route('x-change.operations.recovery.payment.accounted', ['review' => $review->id], false),
                'credit_url' => route('x-change.operations.recovery.payment.credit-excess', ['review' => $review->id], false),
                'apply_url' => route('x-change.operations.recovery.payment.apply', ['review' => $review->id], false),
                'split_url' => route('x-change.operations.recovery.payment.split', ['review' => $review->id], false),
                'candidates' => $candidates->get($review->id, collect())->values(),
            ];
        });
        $funding = DB::table('x_change_funding_suspense_cases')->where('status', 'open')
            ->orderByDesc('id')->limit(50)->get(['reference', 'reason_code', 'provider_code', 'opened_at']);
        $reports = DB::table('x_change_payment_recovery_reports as r')->join('vouchers as v', 'v.id', '=', 'r.voucher_id')
            ->where('r.status', 'open')->orderByDesc('r.id')->limit(50)->get(['r.reference', 'r.created_at', 'r.evidence_ciphertext', 'v.code'])
            ->map(static fn (object $report): array => [
                'reference' => $report->reference, 'created_at' => $report->created_at, 'code' => $report->code,
                'evidence' => json_decode(Crypt::decryptString($report->evidence_ciphertext), true, flags: JSON_THROW_ON_ERROR),
                'review_url' => route('x-change.operations.recovery.report.review', ['reference' => $report->reference], false),
            ]);
        $failures = DB::table('x_change_external_job_failures')->orderByDesc('id')->limit(50)
            ->get(['reference', 'job_type', 'subject_type', 'subject_id', 'failure_type', 'failed_at']);
        $payouts = DisbursementReconciliation::query()->where('claim_type', 'value_flow')
            ->where(fn ($query) => $query->where('needs_review', true)->orWhereIn('status', ['unknown', 'failed']))
            ->where(fn ($query) => $query->whereNull('internal_status')->orWhere('internal_status', '!=', 'recovery_retried'))
            ->orderByDesc('id')->limit(50)->get()
            ->map(static fn (DisbursementReconciliation $payout): array => [
                'id' => $payout->id, 'code' => $payout->voucher_code, 'amount' => $payout->amount,
                'currency' => $payout->currency, 'destination' => $payout->account_number_masked,
                'status' => $payout->status, 'last_checked_at' => $payout->last_checked_at?->toIso8601String(),
                'retry_url' => $payout->status === 'failed' && $payout->internal_status === 'recovery_opened'
                    && ! $payout->needs_review && filled($payout->provider_transaction_id)
                    ? route('x-change.operations.recovery.payout.retry', ['payout' => $payout->id], false) : null,
            ]);
        $treasuryPayoutReviews = DB::table('x_change_treasury_payout_outbox as o')
            ->join('vouchers as v', 'v.id', '=', 'o.voucher_id')
            ->where('o.status', 'review')->orderByDesc('o.id')->limit(50)
            ->get(['o.id', 'o.voucher_id', 'v.code', 'o.created_at', 'o.updated_at']);
        $reviewPayouts = DisbursementReconciliation::query()->whereIn('voucher_id', $treasuryPayoutReviews->pluck('voucher_id'))
            ->where('claim_type', 'withdraw')->latest('id')->get()->unique('voucher_id')->keyBy('voucher_id');
        $treasuryPayoutReviews = $treasuryPayoutReviews->map(static fn (object $row): array => [
            'id' => $row->id, 'code' => $row->code, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            'can_check' => filled($reviewPayouts->get($row->voucher_id)?->provider_transaction_id),
        ]);

        return Inertia::render('x-change/operations/PaymentRecovery', [
            'transactionReviews' => $transactionReviews, 'fundingCases' => $funding,
            'reports' => $reports, 'failures' => $failures, 'payouts' => $payouts,
            'treasuryPayoutReviews' => $treasuryPayoutReviews,
        ])->toResponse($request)->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function creditExcess(Request $request, int $review, CreditReviewedExcess $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000'], 'confirmed' => ['required', 'accepted']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $this->limitProviderCheck($review);
        try {
            $action->handle($review, $actor, $data['reason']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recovery' => 'The excess cannot be credited. Check the payment evidence and eligibility.']);
        }

        return back()->with('status', 'The excess payment was credited to the issuer’s Client Funds.');
    }

    public function applyPayment(Request $request, int $review, ApplyReviewedPayment $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000'], 'attempt_id' => ['required', 'integer', 'min:1'], 'confirmed' => ['required', 'accepted']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $this->limitProviderCheck($review);
        try {
            $action->handle($review, (int) $data['attempt_id'], $actor, $data['reason']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recovery' => 'The payment could not be applied. Check the provider evidence and selected attempt.']);
        }

        return back()->with('status', 'The verified payment was applied.');
    }

    public function splitPayment(Request $request, int $review, ApplyReviewedSplitPayment $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000'], 'attempt_id' => ['required', 'integer', 'min:1'], 'confirmed' => ['required', 'accepted'], 'expected_collection_minor' => ['required', 'integer', 'min:1'], 'expected_excess_minor' => ['required', 'integer', 'min:1']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        $this->limitProviderCheck($review);
        try {
            $action->handle($review, (int) $data['attempt_id'], $actor, $data['reason'], (int) $data['expected_collection_minor'], (int) $data['expected_excess_minor']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recovery' => 'The payment could not be split. Check the evidence, selected attempt, and Pay Code readiness.']);
        }

        return back()->with('status', 'The payment was collected and the excess credited to Client Funds.');
    }

    private function limitProviderCheck(int $review): void
    {
        $provider = DB::table('x_change_payment_transaction_reviews as r')
            ->join('x_change_observed_payment_transactions as t', 't.id', '=', 'r.observed_payment_transaction_id')
            ->where('r.id', $review)->value('t.provider_code');
        abort_unless(is_string($provider), 404);
        $this->limitProviderStatusCheck($provider);
    }

    private function limitProviderStatusCheck(string $provider): void
    {
        $key = md5('x-change-provider-verification'.strtolower(trim($provider)));
        $limit = max(1, min((int) config('x-change.funding.verification_provider_rate_limit_per_minute', 30), (int) config('x-change.payment.attempts.verification_provider_rate_limit_per_minute', 30)));
        abort_if(RateLimiter::tooManyAttempts($key, $limit), 429, 'Provider checks are temporarily busy. Please try again shortly.');
        RateLimiter::hit($key, 60);
    }

    public function markAccounted(Request $request, int $review): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'accounted_elsewhere' => ['required', 'accepted'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        DB::transaction(function () use ($review, $data, $actor): void {
            $case = DB::table('x_change_payment_transaction_reviews')->where('id', $review)->lockForUpdate()->first();
            abort_unless($case !== null && $case->status === 'open', 404);
            DB::table('x_change_payment_transaction_reviews')->where('id', $review)->update([
                'status' => 'resolved', 'resolution_code' => 'accounted_elsewhere',
                'resolved_by_type' => $actor->getMorphClass(), 'resolved_by_id' => (string) $actor->getKey(),
                'resolution_note_ciphertext' => Crypt::encryptString($data['reason']),
                'resolved_at' => now(), 'updated_at' => now(),
            ]);
        }, attempts: 3);

        return back()->with('status', 'The payment review was recorded.');
    }

    public function reviewReport(Request $request, string $reference): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:8', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        DB::transaction(function () use ($reference, $data, $actor): void {
            $report = DB::table('x_change_payment_recovery_reports')->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($report !== null && $report->status === 'open', 404);
            DB::table('x_change_payment_recovery_reports')->where('id', $report->id)->update([
                'status' => 'reviewed', 'reviewed_at' => now(), 'reviewed_by_type' => $actor->getMorphClass(),
                'reviewed_by_id' => (string) $actor->getKey(), 'review_note_ciphertext' => Crypt::encryptString($data['note']),
                'updated_at' => now(),
            ]);
        }, attempts: 3);

        return back()->with('status', 'The report review was recorded.');
    }

    public function retry(Request $request, int $payout, ValueFlowPayoutService $recovery): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500'], 'destination_ready' => ['required', 'accepted']]);
        $record = DisbursementReconciliation::query()->where('claim_type', 'value_flow')->findOrFail($payout);
        $actor = $request->user();
        abort_unless($actor instanceof Model, 403);
        try {
            $recovery->retryRejectedPayout((int) $record->getKey(), $data['reason'], $actor->getMorphClass().':'.$actor->getKey());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recovery' => 'This payout cannot be retried yet. Check the provider evidence.']);
        }

        return back()->with('status', 'The payout retry has been submitted.');
    }
}
