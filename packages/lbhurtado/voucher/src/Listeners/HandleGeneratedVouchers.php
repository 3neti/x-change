<?php

namespace LBHurtado\Voucher\Listeners;

use LBHurtado\Voucher\Pipelines\TriggerPostGenerationWorkflows;
use LBHurtado\Voucher\Pipelines\NotifyCreatorOfVoucherBatch;
use LBHurtado\Voucher\Pipelines\NormalizeVoucherMetadata;
use LBHurtado\Voucher\Pipelines\ValidateVoucherStructure;
use LBHurtado\Voucher\Pipelines\CheckFundsAvailability;
use LBHurtado\Voucher\Pipelines\MarkVouchersAsProcessed;
use LBHurtado\Voucher\Pipelines\LogVoucherAuditTrail;
use LBHurtado\Voucher\Pipelines\ApplyUsageLimits;
use LBHurtado\Voucher\Pipelines\CreateCashEntity;
use LBHurtado\Voucher\Pipelines\RunFraudChecks;
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
                        ValidateVoucherStructure::class,
                        NormalizeVoucherMetadata::class,
                        CheckFundsAvailability::class,
                        RunFraudChecks::class,
                        ApplyUsageLimits::class,
                        CreateCashEntity::class,
                        NotifyCreatorOfVoucherBatch::class,
                        LogVoucherAuditTrail::class,
                        MarkVouchersAsProcessed::class,
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
