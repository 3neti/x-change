<?php

namespace LBHurtado\Voucher\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Voucher\Database\Factories\CashFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Brick\Money\Money;

/**
 * Class Cash.
 *
 * @property int         $id
 * @property Money       $amount
 * @property string      $currency
 * @property Model       $reference
 * @property ArrayObject $meta
 *
 * @method int getKey()
 */
class Cash extends Model
{
    use HasFactory;

    protected $table = 'cash';

    protected $fillable = [
        'amount',
        'currency',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
    ];

    public static function newFactory(): CashFactory
    {
        return CashFactory::new();
    }

    public static function booted(): void
    {
        static::creating(function (Cash $cash) {
            $cash->currency = $cash->currency ?: Number::defaultCurrency();
        });
    }

    public function reference()
    {
        return $this->morphTo();
    }

    protected function Amount(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? Number::defaultCurrency();

                return Money::ofMinor($value, $currency);
            },
            set: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? Number::defaultCurrency();

                return $value instanceof Money
                    ? $value->getMinorAmount()->toInt()  // Extract minor units if already Money
                    : Money::of($value, $currency)->getMinorAmount()->toInt(); // Convert before storing
            }
        );
    }
}
