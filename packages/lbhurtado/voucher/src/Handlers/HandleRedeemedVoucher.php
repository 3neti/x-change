<?php

namespace LBHurtado\Voucher\Handlers;

use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Pipeline\Pipeline;

class HandleRedeemedVoucher
{
    use AsAction;

    public function handle(Voucher $voucher): void
    {
        app(Pipeline::class)
            ->send($voucher)
            ->through([
                DisburseCash::class,
            ])
            ->thenReturn();
        //disburse
        //feedback
        //notify
    }
}
