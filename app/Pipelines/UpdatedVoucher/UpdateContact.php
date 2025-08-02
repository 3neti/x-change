<?php

namespace App\Pipelines\UpdatedVoucher;

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Closure;
class UpdateContact
{
    protected array $inputToContactMap = [
        'name'                  => Contact::NAME_FIELD,
        'email'                 => Contact::EMAIL_FIELD,
        'birth_date'            => Contact::BIRTH_DATE,
        'address'               => Contact::ADDRESS_FIELD,
        'gross_monthly_income'  => Contact::GROSS_MONTHLY_INCOME_FIELD
    ];

    public function handle($voucher, Closure $next)
    {
        if (! $voucher->instructions->inputs->contains(VoucherInputField::NAME)) {
            Log::warning('[UpdateContact] No input name instruction found for voucher', [
                'voucher_code' => $voucher->code,
            ]);
            return $next($voucher);
        }

        $contact = $voucher->contact;
        if (! $contact) {
            Log::warning('[UpdateContact] No contact found for voucher', [
                'voucher_code' => $voucher->code,
            ]);

            return $next($voucher);
        }

        $changes = [];

        foreach ($this->inputToContactMap as $inputKey => $contactField) {
            $inputValue = Arr::get($voucher->redeemers->first()->metadata,'redemption.inputs.' . $inputKey);
            $currentValue = $contact->{$contactField};

            Log::debug("[UpdateContact] Iterating through input contact map. Field: {$contactField}", [
                'inputKey' => $inputKey,
                'contactField' => $contactField,
                'old' => $currentValue,
                'new' => $inputValue,
            ]);

            if ($inputValue && $inputValue !== $currentValue) {
                Log::debug("[UpdateContact] Preparing to update contact. Field: {$contactField}", [
                    'old' => $currentValue,
                    'new' => $inputValue,
                ]);
                $contact->{$contactField} = $inputValue;
                $changes[$contactField] = $inputValue;
            }
        }

        if (! empty($changes)) {
            $contact->save();

            Log::info('[UpdateContact] Contact fields updated', [
                'contact_id' => $contact->id,
                'changes'    => $changes,
            ]);
        } else {
            Log::debug('[UpdateContact] No changes detected in contact fields.');
        }

        return $next($voucher);
    }
//    public function handle($voucher, Closure $next)
//    {
//        Log::debug('[UpdateContact] Voucher updated', [
//            'voucher_code' => $voucher->code,
//            'name' => $voucher->name,
//        ]);
//
//        if ($voucher->name) {
//            Log::debug('[UpdateContact] Syncing contact name', [
//                'old_contact_name' => $voucher->contact?->name,
//                'new_name' => $voucher->name,
//            ]);
//
//            $voucher->contact->name = $voucher->name;
//            $voucher->contact->save();
//
//            Log::info('[UpdateContact] Contact name updated', [
//                'contact_id' => $voucher->contact->id,
//                'updated_name' => $voucher->contact->name,
//            ]);
//        } else {
//            Log::debug('[UpdateContact] No name provided; skipping contact sync.');
//        }
//
//        return $next($voucher);
//    }
}
