<?php

namespace LBHurtado\Wallet\Data;

use LBHurtado\Wallet\Data\Transformers\MoneyToStringTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Bavix\Wallet\Models\Transaction as TransactionModel;
use LBHurtado\Wallet\Data\Casts\MoneyCast;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;
use Brick\Money\Money;

class TransactionData extends Data
{
    public function __construct(
        #[WithTransformer(MoneyToStringTransformer::class)]
        #[WithCast(MoneyCast::class)]
        public Money $amount,
        public bool  $confirmed,
        public array $payload
    ){}

    public static function fromModel(TransactionModel $model): TransactionData
    {
        return new static(
            amount: Money::ofMinor($model->amount, 'PHP'),
            confirmed: $model->confirmed,
            payload: Arr::get($model->meta, 'payload', [])
        );
    }
}
