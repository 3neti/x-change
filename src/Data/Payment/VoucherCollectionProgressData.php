<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use Brick\Money\Money;
use Spatie\LaravelData\Data;

class VoucherCollectionProgressData extends Data
{
    public function __construct(
        public string $currency,
        public int $target_amount_minor,
        public int $collected_total_minor,
        public int $remaining_to_collect_minor,
        public bool $is_fully_collected,
        public bool $is_overpaid,
        public int $overpaid_amount_minor = 0,
    ) {}

    public function targetMoney(): Money
    {
        return Money::ofMinor($this->target_amount_minor, $this->currency);
    }

    public function collectedMoney(): Money
    {
        return Money::ofMinor($this->collected_total_minor, $this->currency);
    }

    public function remainingMoney(): Money
    {
        return Money::ofMinor($this->remaining_to_collect_minor, $this->currency);
    }

    public function overpaidMoney(): Money
    {
        return Money::ofMinor($this->overpaid_amount_minor, $this->currency);
    }

    public function targetAmount(): float
    {
        return $this->targetMoney()->getAmount()->toFloat();
    }

    public function collectedTotal(): float
    {
        return $this->collectedMoney()->getAmount()->toFloat();
    }

    public function remaining(): float
    {
        return $this->remainingMoney()->getAmount()->toFloat();
    }

    public function overpaidAmount(): float
    {
        return $this->overpaidMoney()->getAmount()->toFloat();
    }
}
