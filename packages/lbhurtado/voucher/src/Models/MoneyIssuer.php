<?php

namespace LBHurtado\Voucher\Models;

use LBHurtado\Voucher\Database\Factories\MoneyIssuerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MoneyIssuer.
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name
 *
 * @method int getKey()
 */
class MoneyIssuer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name'
    ];

    public static function newFactory(): MoneyIssuerFactory
    {
        return MoneyIssuerFactory::new();
    }
}
