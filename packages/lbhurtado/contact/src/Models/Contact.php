<?php

namespace LBHurtado\Contact\Models;

use LBHurtado\Contact\Traits\{HasAdditionalAttributes, HasMeta};
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
 * @property string      $name
 *
 * @method int getKey()
 */
class Contact extends Model implements Bankable
{
    use HasAdditionalAttributes;
    use HasBankAccount;
    use HasFactory;
    use HasMobile;
    use HasMeta;

    protected $fillable = [
        'mobile',
        'country',
        'bank_account',
    ];

    protected $appends = [
        'name'
    ];

    public static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->country = $contact->country
                ?: config('contact.default.country');
            // ensure there's always a bank_account like "BANK_CODE:ACCOUNT_NUMBER"
            // Only generate a bank_account if one wasn't explicitly set
            if (empty($contact->bank_account)) {
                $defaultCode = config('contact.default.bank_code');
                $contact->bank_account = "{$defaultCode}:{$contact->mobile}";
            }
//            $contact->bank_account = ($contact->bank_account
//                    ?: config('contact.default.bank_code'))
//                . ':' . $contact->mobile;
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
