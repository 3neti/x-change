<?php

namespace App\Actions;

use Illuminate\Notifications\AnonymousNotifiable;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Notifications\SendOTP;
use OTPHP\TOTP;

class VerifyMobile
{
    use AsAction;

    public function handle(string $mobile)
    {
        $period = config('x-change.otp.period');

        return tap(TOTP::create(secret:null, period: $period, digest:'sha1', digits: config('x-change.otp.digits')), function ($totp) use ($mobile) {
            $totp->setLabel(config('x-change.otp.label'));
            $pin = $totp->now();
            logger('pin = ' . $pin);
            (new AnonymousNotifiable)->notify(new SendOTP(mobile: $mobile, otp: $pin));
        })->getProvisioningUri();
    }
}
