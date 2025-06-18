<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

use LBHurtado\Voucher\Actions\RedeemVoucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Contact\Models\Contact;

class EncashCheck
{
    use AsAction;

    public function handle(Voucher $voucher, PhoneNumber $phoneNumber, array $meta = []): bool
    {

       $contact = Contact::fromPhoneNumber($phoneNumber);

       return RedeemVoucher::run($contact, $voucher->code, $meta);
    }

}
