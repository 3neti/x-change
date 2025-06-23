<?php

namespace LBHurtado\Voucher\Handlers;

use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\Voucher\Events\DisbursementRequested;
use LBHurtado\Wallet\Events\DisbursementFailed;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Pipeline\Pipeline;

class HandleRedeemedVoucher
{
    use AsAction;

    /**
     * Process a newly-redeemed voucher:
     *  1. Validate that it has both a cash entity and a contact
     *  2. Attempt to disburse funds
     *  3. Fire DisbursementRequested on success
     *  4. Fire DisbursementFailed (and rethrow) on any error
     *
     * @param  Voucher  $voucher
     * @return void
     *
     * @throws \Throwable  Allows the exception to bubble after firing DisbursementFailed
     */
    public function handle(Voucher $voucher): void
    {
        Log::info('[HandleRedeemedVoucher] Starting pipeline for redeemed voucher.', [
            'voucher' => $voucher->code,
            'id'      => $voucher->getKey(),
        ]);

        try {
            app(Pipeline::class)
                ->send($voucher)
                ->through([
                    ValidateRedeemerAndCash::class,
                    DisburseCash::class,
                ])
                ->then(function (Voucher $voucher) {
                    Log::info('[HandleRedeemedVoucher] Pipeline completed successfully; dispatching DisbursementRequested.', [
                        'voucher' => $voucher->code,
                    ]);

                    event(new DisbursementRequested($voucher));

                    return $voucher;
                });
        } catch (\Throwable $e) {
            Log::error('[HandleRedeemedVoucher] Pipeline failed; dispatching DisbursementFailed.', [
                'voucher'   => $voucher->code,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            event(new DisbursementFailed($voucher, $e));

            // rethrow so callers can handle it (or crash if unhandled)
            throw $e;
        }
    }
}
