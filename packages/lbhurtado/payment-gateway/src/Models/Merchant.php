<?php

namespace LBHurtado\PaymentGateway\Models;

use LBHurtado\PaymentGateway\Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Merchant.
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name
 * @property string      $city
 *
 * @method int getKey()
 */
class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
    ];

    public static function newFactory(): MerchantFactory
    {
        return MerchantFactory::new();
    }
}
