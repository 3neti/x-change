<?php

use OTPHP\Factory;
use OTPHP\TOTP;

it('OTP works', function () {
    $period = config('x-change.otp.period');
    $mobile = '09173011987';
    $pin = '';
    $uri = tap(TOTP::create(secret:null, period: $period, digest:'sha1', digits: config('x-change.otp.digits')), function ($totp) use (&$pin, $mobile) {
        $totp->setLabel(config('x-change.otp.label'));
        $pin = $totp->now();
//        dd($pin);
//        logger('pin = ' . $pin);
//        (new AnonymousNotifiable)
//            ->notify(new VerifyMobileNotification(mobile: $mobile, otp: $pin));
    })->getProvisioningUri();


    $verifier = Factory::loadFromProvisioningUri($uri);
    expect($verifier->verify($pin))->toBeTrue();
});
