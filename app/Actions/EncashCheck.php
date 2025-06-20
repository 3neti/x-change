<?php

namespace App\Actions;

use LBHurtado\Voucher\Actions\RedeemVoucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Support\Facades\Log;

class EncashCheck
{
    use AsAction;

    /**
     * @param  Voucher      $voucher
     * @param  PhoneNumber  $phoneNumber
     * @param  array        $meta
     * @return bool
     */
    public function handle(Voucher $voucher, PhoneNumber $phoneNumber, array $meta = []): bool
    {
        // Log the incoming meta so you can inspect it:
        Log::debug('[EncashCheck] Running with meta:', ['voucher' => $voucher->code, 'meta' => $meta]);

        $contact = Contact::fromPhoneNumber($phoneNumber);

        // Also log the resolved contact for extra clarity:
        Log::debug('[EncashCheck] Resolved contact:', [
            'contact_id'     => $contact->getKey(),
            'contact_mobile' => $contact->mobile,
        ]);

        $result = RedeemVoucher::run($contact, $voucher->code, $meta);

        // Log the result of the redemption attempt:
        Log::info('[EncashCheck] RedeemVoucher result:', [
            'voucher' => $voucher->code,
            'success' => $result,
        ]);

        return $result;
    }
}
