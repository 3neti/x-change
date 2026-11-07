<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\XChange\Services\ClaimPayoutLocalityScope;
use Throwable;

final class DeferredLegacyDisbursement
{
    public function handle(Voucher $voucher, Closure $next): mixed
    {
        if (! config('x-change.payout_locality.submission_guard_enabled', false)) {
            return app(DisburseCash::class)->handle($voucher, $next);
        }

        $enqueue = function () use ($voucher): void {
            $outboxId = app(DurableTreasuryPayout::class)->enqueue($voucher, app(ClaimPayoutLocalityScope::class)->get(), 'legacy');
            DB::afterCommit(function () use ($outboxId): void {
                try {
                    app(DurableTreasuryPayout::class)->deliver($outboxId);
                } catch (Throwable $exception) {
                    Log::warning('Payout delivery will be retried from the outbox.', [
                        'outbox_id' => $outboxId,
                        'exception' => $exception::class,
                    ]);
                }
            });
        };

        if (DB::transactionLevel() > 0) {
            $enqueue();
        } else {
            DB::transaction($enqueue);
        }

        return $voucher;
    }
}
