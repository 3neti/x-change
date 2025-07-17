<?php

namespace App\Validators;

use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\{Hash, Log};
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\MessageBag;
use Throwable;

class VoucherRedemptionValidator
{
    public function __construct(
        protected Voucher $voucher,
        protected MessageBag $errors = new MessageBag()
    ) {}

    public function validateMobile(?string $mobile): bool
    {
        $expected = $this->voucher->instructions->cash->validation->mobile ?? null;

        if (empty($expected)) {
            Log::info('[VoucherRedemptionValidator] No mobile validation required.', [
                'voucher_code' => $this->voucher->code,
            ]);
            return true;
        }

        try {
            $expectedPhone = new PhoneNumber($expected, 'PH');
            $actualPhone   = new PhoneNumber($mobile, 'PH');

            if (! $expectedPhone->equals($actualPhone)) {
                $this->errors->add('mobile', 'Invalid recipient mobile number.');
                Log::warning('[VoucherRedemptionValidator] Mobile number mismatch.', [
                    'voucher_code' => $this->voucher->code,
                    'expected' => (string) $expectedPhone,
                    'actual'   => (string) $actualPhone,
                ]);
                return false;
            }

            Log::info('[VoucherRedemptionValidator] Mobile number matched.', [
                'voucher_code' => $this->voucher->code,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->errors->add('mobile', 'Error validating mobile number.');
            Log::error('[VoucherRedemptionValidator] Exception during mobile validation.', [
                'voucher_code' => $this->voucher->code,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function validateSecret(?string $secret): bool
    {
        $storedHash = $this->voucher->cash?->secret;

        if (empty($storedHash)) {
            Log::info('[VoucherRedemptionValidator] No secret configured; skipping validation.', [
                'voucher_code' => $this->voucher->code,
            ]);
            return true;
        }

        if (empty($secret) || !Hash::check($secret, $storedHash)) {
            $this->errors->add('secret', 'Invalid secret provided.');
            Log::warning('[VoucherRedemptionValidator] Secret validation failed.', [
                'voucher_code' => $this->voucher->code,
            ]);
            return false;
        }

        Log::info('[VoucherRedemptionValidator] Secret validated successfully.', [
            'voucher_code' => $this->voucher->code,
        ]);

        return true;
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }
}
