<?php

namespace LBHurtado\Voucher\Listeners;

use LBHurtado\Voucher\Pipelines\TriggerPostGenerationWorkflows;
use LBHurtado\Voucher\Pipelines\CheckFundsAvailability;
use LBHurtado\Voucher\Pipelines\NotifyBatchCreator;
use LBHurtado\Voucher\Pipelines\CreateCashEntities;
use LBHurtado\Voucher\Pipelines\NormalizeMetadata;
use LBHurtado\Voucher\Pipelines\ValidateStructure;
use LBHurtado\Voucher\Pipelines\ApplyUsageLimits;
use LBHurtado\Voucher\Pipelines\MarkAsProcessed;
use LBHurtado\Voucher\Pipelines\RunFraudChecks;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Pipelines\LogAuditTrail;
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
                    return !$voucher->processed; // Skip already processed vouchers
                });

                app(Pipeline::class)
                    ->send($unprocessedVouchers)
                    ->through([
                        ValidateStructure::class,
                        NormalizeMetadata::class,
                        CheckFundsAvailability::class,
                        RunFraudChecks::class,
                        ApplyUsageLimits::class,
                        CreateCashEntities::class,
                        NotifyBatchCreator::class,
                        LogAuditTrail::class,
                        MarkAsProcessed::class,
                        TriggerPostGenerationWorkflows::class,
                    ])
                    ->thenReturn();
            });
        } catch (\Throwable $e) {
            Log::error("Failed to process vouchers. Error: {$e->getMessage()}");
            throw $e;
        }
    }
}
