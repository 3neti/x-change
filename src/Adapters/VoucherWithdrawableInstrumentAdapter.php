<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Adapters;

use LBHurtado\Cash\Contracts\WithdrawableInstrumentContract;
use LBHurtado\Voucher\Models\Voucher;

class VoucherWithdrawableInstrumentAdapter implements WithdrawableInstrumentContract
{
    public function __construct(
        protected Voucher $voucher,
    ) {}

    public function isWithdrawable(): bool
    {
        return method_exists($this->voucher, 'canWithdraw') && (bool) $this->voucher->canWithdraw();
    }

    public function isDivisible(): bool
    {
        return method_exists($this->voucher, 'isDivisible')
            ? (bool) $this->voucher->isDivisible()
            : false;
    }

    public function getSliceMode(): ?string
    {
        return method_exists($this->voucher, 'getSliceMode')
            ? $this->voucher->getSliceMode()
            : null;
    }

    public function getSliceAmount(): ?float
    {
        if (! method_exists($this->voucher, 'getSliceAmount')) {
            return null;
        }

        $value = $this->voucher->getSliceAmount();

        return $value !== null ? (float) $value : null;
    }

    public function getRemainingBalance(): float
    {
        return method_exists($this->voucher, 'getRemainingBalance')
            ? (float) $this->voucher->getRemainingBalance()
            : 0.0;
    }

    public function getMinWithdrawal(): ?float
    {
        if (! method_exists($this->voucher, 'getMinWithdrawal')) {
            return null;
        }

        $value = $this->voucher->getMinWithdrawal();

        return $value !== null ? (float) $value : null;
    }

    public function getMaxSlices(): ?int
    {
        if (! method_exists($this->voucher, 'getMaxSlices')) {
            return null;
        }

        $value = $this->voucher->getMaxSlices();

        return $value !== null ? (int) $value : null;
    }

    public function getConsumedSlices(): int
    {
        return method_exists($this->voucher, 'getConsumedSlices')
            ? (int) $this->voucher->getConsumedSlices()
            : 0;
    }

    public function isExpired(): bool
    {
        return method_exists($this->voucher, 'isExpired')
            ? (bool) $this->voucher->isExpired()
            : false;
    }

    public function getInstrumentState(): string
    {
        $state = $this->voucher->state ?? null;

        if ($state instanceof \BackedEnum) {
            return (string) $state->value;
        }

        return (string) $state;
    }

    public function getInstrumentId(): string|int|null
    {
        return method_exists($this->voucher, 'getKey')
            ? $this->voucher->getKey()
            : null;
    }

    public function getOriginalClaimantId(): string|int|null
    {
        $value = data_get($this->voucher->redeemer, 'id');

        if ($value !== null) {
            return $value;
        }

        $value = data_get($this->voucher->meta, 'redeemer.id');

        return $value !== null ? (string) $value : null;
    }
}
