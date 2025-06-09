<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;
use Brick\Money\Money;

class BalanceAccessLog extends Model
{
    protected $fillable = [
        'wallet',
        'balance',
        'requestor',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function wallet(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestor(): MorphTo
    {
        return $this->morphTo();
    }

    public function setWalletAttribute(Model $wallet): void
    {
        $this->wallet()->associate($wallet);
    }

    public function setRequestorAttribute(Model $requestor): void
    {
        $this->requestor()->associate($requestor);
    }

    public function setBalanceAttribute(Money $amount): void
    {
        $this->setAttribute('amount', (string) $amount->getAmount()); // precise string
        $this->setAttribute('currency', $amount->getCurrency()->getCurrencyCode());
    }

    public function getBalanceAttribute(): ?Money
    {
        $amount = $this->getAttribute('amount');
        $currency = $this->getAttribute('currency');

        if (is_null($amount) || is_null($currency)) {
            return null;
        }

        return Money::of((string) $amount, $currency);
    }
}
