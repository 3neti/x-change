<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Voucher\Pipelines\Voucher\CheckBalance;
use LBHurtado\Voucher\Pipelines\Voucher\EscrowAction;
use LBHurtado\Voucher\Pipelines\Voucher\PersistCash;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\{DB, Log};
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Pipeline\Pipeline;
use LBHurtado\Cash\Models\Cash;

class MintCash
{
    use AsAction;

    public function handle(Voucher $voucher): Cash
    {
        try {
            $callback = fn () => app(Pipeline::class)
                ->send($voucher)
                ->through([
                    CheckBalance::class,
                    EscrowAction::class,
                    PersistCash::class,
                ])
                ->thenReturn();

            if (DB::transactionLevel() > 0) {
                $callback(); // Already inside a transaction
            } else {
                DB::transaction($callback); // Run with transaction safety
            }

            return $voucher->getEntities(Cash::class)->first();
        } catch (\Throwable $th) {
            Log::error("Failed to mint cash for voucher ID {$voucher->id}. Error: {$th->getMessage()}");
            throw $th;
        }
    }
}
