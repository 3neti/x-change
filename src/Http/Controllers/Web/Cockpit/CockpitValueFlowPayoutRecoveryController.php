<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\MonitorPaymentAttemptJob;
use LBHurtado\XChange\Jobs\Payment\ReconcileValueFlowPayoutJob;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use Throwable;

final class CockpitValueFlowPayoutRecoveryController extends Controller
{
    public function index(Request $request, string $code, VoucherAccessContract $vouchers): JsonResponse
    {
        $voucher = $this->ownedVoucher($request, $code, $vouchers);
        $payouts = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())
            ->where('claim_type', 'value_flow')->get();
        $byReference = $payouts->keyBy('provider_reference');
        $byId = $payouts->keyBy('id');
        $payments = VoucherCollection::query()->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['collected', 'succeeded'])->orderBy('collection_number')->get()
            ->map(function (VoucherCollection $collection) use ($voucher, $byReference, $byId): array {
                $payoutReference = data_get($collection->meta, 'posting.payout_reference');
                $payout = $byReference->get($payoutReference);
                $visited = [];
                while ($payout !== null && $payout->internal_status === 'recovery_retried') {
                    if (isset($visited[$payout->id])) {
                        $payout = null;
                        break;
                    }
                    $visited[$payout->id] = true;
                    $next = $byId->get(data_get($payout->meta, 'recovery_retry.payout_id'));
                    $payout = $next !== null && (int) data_get($next->meta, 'recovery.original_payout_id') === (int) $payout->id ? $next : null;
                }
                $eligible = $payout !== null && $payout->status === 'failed'
                    && $payout->internal_status === 'recovery_opened' && ! $payout->needs_review
                    && filled($payout->provider_transaction_id);

                return [
                    'number' => $collection->collection_number,
                    'amount_minor' => $collection->collected_amount_minor,
                    'currency' => $collection->currency,
                    'received_at' => $collection->completed_at?->toIso8601String(),
                    'delivery_status' => match (true) {
                        blank($payoutReference) => 'not_applicable',
                        $payout === null => 'review',
                        $payout->status === 'succeeded' && $payout->internal_status === 'finalized' => 'delivered',
                        $payout->needs_review || in_array($payout->status, ['failed', 'unknown'], true) => 'review',
                        default => 'pending',
                    },
                    'destination' => $payout?->account_number_masked,
                    'retry_url' => $eligible ? route('x-change.cockpit.pay-codes.value-flow-payouts.retry', ['code' => $voucher->code, 'payout' => $payout->id]) : null,
                ];
            })->all();

        return response()->json(['payments' => $payments,
            'check_url' => route('x-change.cockpit.pay-codes.value-flow-payouts.check', ['code' => $voucher->code]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function check(Request $request, string $code, VoucherAccessContract $vouchers): JsonResponse
    {
        $voucher = $this->ownedVoucher($request, $code, $vouchers);
        $days = max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7));
        PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->whereNotNull('instructions_created_at')
            ->where('expires_at', '>', now()->subDays($days))->latest('id')->limit(20)
            ->each(function (PaymentAttempt $attempt): void {
                MonitorPaymentAttemptJob::dispatch((int) $attempt->getKey(), $attempt->provider_code);
                if (in_array($attempt->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying, PaymentAttemptStatus::Verified, PaymentAttemptStatus::Expired], true)) {
                    VerifyPaymentAttemptJob::dispatch((int) $attempt->getKey(), $attempt->provider_code, PaymentVerificationTrigger::Operator);
                }
            });
        DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->where('claim_type', 'value_flow')
            ->whereIn('status', ['pending', 'unknown', 'succeeded'])->whereNotNull('provider_transaction_id')
            ->where(fn ($query) => $query->whereNull('internal_status')->orWhere('internal_status', '!=', 'finalized'))
            ->whereNotNull('provider')->latest('id')->limit(20)->each(fn (DisbursementReconciliation $payout) => ReconcileValueFlowPayoutJob::dispatch((int) $payout->getKey(), (string) $payout->provider));

        return response()->json(['message' => 'A status check was requested.'])->header('Cache-Control', 'no-store, private');
    }

    public function __invoke(Request $request, string $code, int $payout, VoucherAccessContract $vouchers, ValueFlowPayoutService $recovery): JsonResponse
    {
        $voucher = $this->ownedVoucher($request, $code, $vouchers);
        $owner = $request->user();

        $record = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())
            ->where('claim_type', 'value_flow')->whereKey($payout)->firstOrFail();
        $request->validate(['destination_ready' => ['required', 'accepted']]);

        try {
            $retry = $recovery->retryRejectedPayout(
                (int) $record->getKey(),
                'owner-confirmed-original-destination-ready',
                $owner->getMorphClass().':'.$owner->getKey(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'This payout cannot be retried yet. Check its status or contact support.'], 409)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['message' => 'The payout retry has been submitted for processing.', 'payout_id' => $retry->getKey()], 202)
            ->header('Cache-Control', 'no-store, private');
    }

    private function ownedVoucher(Request $request, string $code, VoucherAccessContract $vouchers): Model
    {
        $voucher = $vouchers->findByCode($code);
        $owner = $request->user();
        abort_unless($voucher instanceof Model && $owner instanceof Model
            && $voucher->owner_type === $owner->getMorphClass()
            && (string) $voucher->owner_id === (string) $owner->getKey(), 404);

        return $voucher;
    }
}
