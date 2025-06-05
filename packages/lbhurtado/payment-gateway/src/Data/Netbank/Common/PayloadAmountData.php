<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Common;

use Spatie\LaravelData\Attributes\{MapInputName, WithCast, WithTransformer};
use LBHurtado\PaymentGateway\Data\Transformers\MoneyToStringTransformer;
use LBHurtado\PaymentGateway\Data\Casts\MoneyCast;
use Spatie\LaravelData\Data;
use Brick\Money\Money;

class PayloadAmountData extends Data
{
    public function __construct(
        public ?string $cur,
        #[WithTransformer(MoneyToStringTransformer::class)]
        #[WithCast(MoneyCast::class)]
        #[MapInputName('amount')]
        public Money $num
    ){
        if (is_null($cur)) {
            $this->cur = $num->getCurrency()->getCurrencyCode();
        }
    }

    public static function fromMoney(Money $money): self
    {
        return new self(
            cur: $money->getCurrency()->getCurrencyCode(),
            num: $money
        );
    }
}
