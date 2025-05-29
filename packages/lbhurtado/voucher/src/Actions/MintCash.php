<?php

namespace LBHurtado\Voucher\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Models\Cash;

class MintCash
{
    use AsAction;

    public function handle(Voucher $voucher): Cash
    {
        $instructions = $voucher->instructions;
        $cash = Cash::create([
            'amount' => $instructions->cash->amount,
            'currency' => $instructions->cash->currency,
            'meta' => ['dispatched_by' => 'VouchersGenerated'],
        ]);

        $entities = ['cash' => $cash];
        $voucher->addEntities(...$entities);

        return $cash;
    }
}
