<?php

namespace LBHurtado\Cash\Data;

use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use LBHurtado\Cash\Data\Transformers\MoneyToStringTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use LBHurtado\Cash\Models\Cash as CashModel;
use LBHurtado\Cash\Data\Casts\MoneyCast;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Brick\Money\Money;

class CashData extends Data
{
    public function __construct(
        #[WithTransformer(MoneyToStringTransformer::class)]
        #[WithCast(MoneyCast::class)]
        public Money $amount,
        public string $currency,
        public ArrayObject $meta,
        public ?string $secret = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $expires_on,
        public bool $expired,
        public string $status,
        public array $tags,
    ) {}

    public static function fromModel(CashModel $cash): CashData
    {
        return new static(
            amount: $cash->amount,
            currency: $cash->currency,
            meta: $cash->meta,
            secret: $cash->secret,
            expires_on: $cash->expires_on,
            expired: $cash->expired,
            status: $cash->status,
            tags: $cash->tags->toArray()
        );
    }
}
