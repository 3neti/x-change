<?php

namespace LBHurtado\Contact\Models;

use LBHurtado\Contact\Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Contact\Traits\HasBankAccount;
use LBHurtado\Contact\Contracts\Bankable;
use LBHurtado\Contact\Traits\HasMobile;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contact.
 *
 * @property int         $id
 * @property string      $mobile
 * @property string      $country
 * @property string      $bank_account
 * @property string      $bank_code
 * @property string      $account_number
 *
 * @method int getKey()
 */
class Contact extends Model implements Bankable
{
    use HasBankAccount;
    use HasFactory;
    use HasMobile;

    protected $fillable = [
        'mobile',
        'country',
        'bank_account',
    ];

    public static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->country = $contact->country
                ?: config('contact.default.country');
            // ensure there's always a bank_account like "BANK_CODE:ACCOUNT_NUMBER"
            $contact->bank_account = ($contact->bank_account
                    ?: config('contact.default.bank_code'))
                . ':' . $contact->mobile;
        });
    }

    public static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    public function getBankCodeAttribute(): string
    {
        return $this->getBankCode();
    }

    public function getAccountNumberAttribute(): string
    {
        return $this->getAccountNumber();
    }
}
