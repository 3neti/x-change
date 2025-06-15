<?php

namespace LBHurtado\Contact\Models;

use LBHurtado\Contact\Database\Factories\CashFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Contact\Traits\HasMobile;

/**
 * Class Contact.
 *
 * @property int         $id
 * @property string      $mobile
 * @property string      $country
 *
 * @method int getKey()
 */
class Contact extends Model
{
    use HasFactory;
    use HasMobile;

    protected $fillable = [
        'mobile',
        'country'
    ];

    public static function booted(): void
    {
        static::creating(function (Cash $contact) {
            $contact->country = empty($contact->country) ? self::DEFAULT_COUNTRY : $contact->country;
        });
    }

    public static function newFactory(): CashFactory
    {
        return CashFactory::new();
    }
}
