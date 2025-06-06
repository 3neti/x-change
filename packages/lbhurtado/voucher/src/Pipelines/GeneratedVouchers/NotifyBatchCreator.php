<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use LBHurtado\Voucher\Notifications\VouchersGeneratedNotification;

class NotifyBatchCreator
{
    public function handle($vouchers, Closure $next)
    {
        $first = $vouchers->first();
        $instructions = $first->metadata['instructions'] ?? [];
        $feedback = $instructions['feedback'] ?? [];

//        if (!empty($feedback['mobile'])) {
//            Notification::route('nexmo', $feedback['mobile'])
//                ->notify(new VouchersGeneratedNotification($vouchers));
//        }
//
//        if (!empty($feedback['email'])) {
//            Notification::route('mail', $feedback['email'])
//                ->notify(new VouchersGeneratedNotification($vouchers));
//        }

        return $next($vouchers);
    }
}
