<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Adapters;

use LBHurtado\Cash\Contracts\WithdrawableInstrumentContract;
use LBHurtado\Voucher\Data\VoucherSlicePlanData;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherSliceExecution;

class VoucherWithdrawableInstrumentAdapter implements WithdrawableInstrumentContract
{
    public function __construct(
        protected Voucher $voucher,
        protected array $payload = [],
    ) {}

    public function isWithdrawable(): bool
    {
        /**
         * TODO (refactor): Open-slice compatibility shim
         *
         * Voucher::canWithdraw() does NOT consider divisible open-slice vouchers
         * as withdrawable after initial claim. However, in x-change lifecycle,
         * open-slice vouchers must still allow subsequent withdrawals as long as:
         * - slice mode is 'open'
         * - remaining balance / slices exist
         * - validation rules pass
         *
         * This adapter overrides that behavior to preserve legacy semantics.
         *
         * Future plan:
         * - Move this logic into a first-class domain concept in cash (e.g. WithdrawalMode)
         * - Or update voucher::canWithdraw() to correctly handle open-slice semantics
         * - Then REMOVE this shim
         */
        if ($this->isDivisible() && $this->getSliceMode() === 'open') {
            return true;
        }

        return method_exists($this->voucher, 'canWithdraw')
            && $this->voucher->canWithdraw();
    }

    public function isDivisible(): bool
    {
        if ($this->plan() !== null) {
            return true;
        }

        return method_exists($this->voucher, 'isDivisible')
            ? (bool) $this->voucher->isDivisible()
            : false;
    }

    public function getSliceMode(): ?string
    {
        $plan = $this->plan();

        if ($plan !== null) {
            return $plan->mode === VoucherSlicePlanMode::Equal ? 'fixed' : 'open';
        }

        return method_exists($this->voucher, 'getSliceMode')
            ? $this->voucher->getSliceMode()
            : null;
    }

    public function getSliceAmount(): ?float
    {
        $execution = $this->execution();

        if ($execution instanceof VoucherSliceExecution) {
            return $execution->amount_minor / 100;
        }

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
        $plan = $this->plan();

        if ($plan?->min_amount_minor !== null) {
            return $plan->min_amount_minor / 100;
        }

        if (! method_exists($this->voucher, 'getMinWithdrawal')) {
            return null;
        }

        $value = $this->voucher->getMinWithdrawal();

        return $value !== null ? (float) $value : null;
    }

    public function getMaxSlices(): ?int
    {
        $plan = $this->plan();

        if ($plan !== null) {
            return $plan->max_slices ?? $plan->slices->count();
        }

        if (! method_exists($this->voucher, 'getMaxSlices')) {
            return null;
        }

        $value = $this->voucher->getMaxSlices();

        return $value !== null ? (int) $value : null;
    }

    public function getConsumedSlices(): int
    {
        if ($this->plan() !== null) {
            return VoucherSliceExecution::query()
                ->where('voucher_id', $this->voucher->getKey())
                ->where('status', 'succeeded')
                ->count();
        }

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

    private function plan(): ?VoucherSlicePlanData
    {
        $value = data_get($this->voucher, 'instructions.slice_plan')
            ?? data_get($this->voucher->metadata, 'instructions.slice_plan');

        if ($value instanceof VoucherSlicePlanData) {
            return $value;
        }

        return is_array($value) ? VoucherSlicePlanData::from($value) : null;
    }

    private function execution(): ?VoucherSliceExecution
    {
        $reference = data_get($this->payload, '_slice_execution.reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return VoucherSliceExecution::query()
            ->where('voucher_id', $this->voucher->getKey())
            ->where('reference', $reference)
            ->first();
    }
}
