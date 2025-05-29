<?php

namespace LBHurtado\Voucher\Pipelines;

use LBHurtado\Voucher\Notifications\VouchersGeneratedNotification;
use Illuminate\Support\Facades\Notification;
use Closure;

class NotifyCreatorOfVoucherBatch
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
