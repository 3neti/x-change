<?php

namespace LBHurtado\Voucher\Listeners;

use LBHurtado\Voucher\Pipelines\MarkVoucherAsProcessed;
use LBHurtado\Voucher\Pipelines\CreateCashEntity;
use LBHurtado\Voucher\Events\VouchersGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Pipeline\Pipeline;

class HandleGeneratedVouchers implements ShouldQueue
{
    public function handle(VouchersGenerated $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                // Process only unprocessed vouchers in the pipeline
                $unprocessedVouchers = $event->getVouchers()->filter(function ($voucher) {
                    $voucher->refresh(); // Ensure the latest state from DB
                    return !$voucher->processed; // Skip already processed vouchers
                });

                app(Pipeline::class)
                    ->send($unprocessedVouchers)
                    ->through([
                        CreateCashEntity::class,
                        MarkVoucherAsProcessed::class, // Ensure processed flag is set at the end
                    ])
                    ->thenReturn();
            });
        } catch (\Throwable $e) {
            Log::error("Failed to process vouchers. Error: {$e->getMessage()}");
            throw $e;
        }
    }
}
