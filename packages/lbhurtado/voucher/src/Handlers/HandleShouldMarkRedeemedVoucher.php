<?php

namespace LBHurtado\Voucher\Handlers;

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;

class HandleShouldMarkRedeemedVoucher
{
    use AsAction;

    public function handle(Voucher $voucher): void {/**  */}
}
