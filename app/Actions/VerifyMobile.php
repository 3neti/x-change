<?php

namespace App\Actions;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Events\SessionMobileStored;
use App\Notifications\SendOTP;
use OTPHP\TOTP;

class VerifyMobile
{
    use AsAction;

    protected function verifyMobile(Voucher $voucher, string $mobile): void
    {
        $voucherCode = $voucher->code;
        $uri = $this->handle($mobile);
        cache()->put("otp.uri.{$voucherCode}", $uri, now()->addMinutes(10));
    }

    public function handle(string $mobile)
    {
        $period = config('x-change.otp.period');

        return tap(TOTP::create(secret:null, period: $period, digest:'sha1', digits: config('x-change.otp.digits')), function ($totp) use ($mobile) {
            $totp->setLabel(config('x-change.otp.label'));
            $pin = $totp->now();
            (new AnonymousNotifiable)->notify(new SendOTP(mobile: $mobile, otp: $pin));
        })->getProvisioningUri();
    }

    public function asListener(SessionMobileStored $event): void
    {
        $voucher = $event->getVoucher();
        if ($voucher->instructions->inputs->contains(VoucherInputField::OTP))
            $this->verifyMobile($event->getVoucher(), $event->getMobile());
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
        ];
    }

    public function asController(ActionRequest $request, Voucher $voucher): \Illuminate\Http\RedirectResponse
    {
        $this->verifyMobile($voucher, $request->validated('mobile'));

        return redirect()->back();
    }
}
