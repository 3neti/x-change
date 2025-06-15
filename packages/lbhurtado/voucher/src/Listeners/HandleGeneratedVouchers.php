<?php

namespace LBHurtado\Voucher\Listeners;

use LBHurtado\Voucher\Events\VouchersGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Pipeline\Pipeline;

class HandleGeneratedVouchers implements ShouldQueue
{
    public function handle(VouchersGenerated $event): void
    {
        Log::info('[HandleGeneratedVouchers] Event received', [
            'voucher_count' => $event->getVouchers()->count(),
        ]);

        try {
            DB::transaction(function () use ($event) {
                $all = $event->getVouchers();
                Log::debug('[HandleGeneratedVouchers] Total vouchers in event', ['total' => $all->count()]);

                // Process only unprocessed vouchers in the pipeline
                $unprocessed = $all->filter(fn($voucher) => !$voucher->processed);
                Log::debug('[HandleGeneratedVouchers] Unprocessed vouchers', ['count' => $unprocessed->count()]);

                $post_generation_pipeline_array = config('voucher-pipeline.post-generation');

                app(Pipeline::class)
                    ->send($unprocessed)
                    ->through($post_generation_pipeline_array)
                    ->thenReturn();

                Log::info('[HandleGeneratedVouchers] Pipeline completed', ['processed' => $unprocessed->count()]);
            });
        } catch (\Throwable $e) {
            Log::error('[HandleGeneratedVouchers] Failed to process vouchers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('[HandleGeneratedVouchers] Handler finished successfully');
    }
}
