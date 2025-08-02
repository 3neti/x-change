<?php

namespace LBHurtado\Voucher\Handlers;

use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Pipeline\Pipeline;

class HandleUpdatedVoucher
{
    public function handle(Voucher $voucher): void
    {
        Log::info('[HandleUpdatedVoucher] Starting pipeline for updated voucher.', [
            'voucher' => $voucher->code,
            'id'      => $voucher->getKey(),
        ]);

        $updated_pipeline = config('voucher-pipeline.updated');

        Log::info('[HandleUpdatedVoucher] Pipeline arranged for updated voucher.', [
            'pipeline' => $updated_pipeline,
        ]);

        try {
            app(Pipeline::class)
                ->send($voucher)
                ->through($updated_pipeline)
                ->then(function (Voucher $voucher) {
                    Log::info('[HandleUpdatedVoucher] Pipeline completed successfully.', [
                        'voucher' => $voucher->code,
                    ]);

                    return $voucher;
                });
        } catch (\Throwable $e) {
            Log::error('[HandleUpdatedVoucher] Pipeline failed.', [
                'voucher'   => $voucher->code,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
