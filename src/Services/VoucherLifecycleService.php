<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Brick\Money\Money;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

class VoucherLifecycleService implements VoucherLifecycleServiceContract
{
    public function __construct(
        protected VoucherAccessContract $vouchers,
    ) {}

    public function list(array $filters = []): array
    {
        $items = $this->vouchers->list($filters);

        return collect($items)
            ->map(fn (Voucher $voucher) => $this->toSummaryArray($voucher))
            ->values()
            ->all();
    }

    public function show(string $voucher): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        return $this->toDetailArray($model);
    }

    public function showByCode(string $code): mixed
    {
        $model = $this->vouchers->findByCodeOrFail(strtoupper(trim($code)));

        return $this->toDetailArray($model);
    }

    public function status(string $voucher): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        return $this->toStatusArray($model);
    }

    public function cancel(string $voucher, array $payload = []): mixed
    {
        $model = $this->vouchers->findOrFail($voucher);

        // First-pass lifecycle mutation.
        // Replace later with a dedicated action if cancellation rules become richer.
        $model->state = VoucherState::CLOSED;
        $model->closed_at = now();
        $model->save();

        return [
            'voucher_id' => $model->id,
            'code' => $model->code,
            'status' => 'cancelled',
            'cancelled' => true,
            'reason' => $payload['reason'] ?? null,
            'messages' => ['Voucher cancelled successfully.'],
        ];
    }

    protected function toSummaryArray(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'amount' => $this->amount($voucher),
            'currency' => $this->currency($voucher),
            'status' => $this->statusLabel($voucher),
            'issuer_id' => $this->issuerId($voucher),
        ];
    }

    protected function toDetailArray(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'amount' => $this->amount($voucher),
            'currency' => $this->currency($voucher),
            'status' => $this->statusLabel($voucher),
            'issuer_id' => $this->issuerId($voucher),
            'claimed' => $voucher->redeemed_at !== null,
            'fully_claimed' => $voucher->redeemed_at !== null,
        ];
    }

    protected function toStatusArray(Voucher $voucher): array
    {
        $claimed = $voucher->redeemed_at !== null;
        $amount = $this->amount($voucher);

        return [
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'status' => $this->statusLabel($voucher),
            'claimed' => $claimed,
            'fully_claimed' => $claimed,
            'remaining_balance' => $claimed ? 0.0 : $amount,
            'currency' => $this->currency($voucher),
        ];
    }

    protected function amount(Voucher $voucher): float
    {
        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getAmount()->toFloat();
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        return 0.0;
    }

    protected function currency(Voucher $voucher): string
    {
        $currency = data_get($voucher, 'cash.currency');

        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getCurrency()->getCurrencyCode();
        }

        return 'PHP';
    }

    protected function issuerId(Voucher $voucher): ?int
    {
        $ownerKey = $voucher->owner?->getKey();

        return $ownerKey !== null ? (int) $ownerKey : null;
    }

    protected function statusLabel(Voucher $voucher): string
    {
        if ($voucher->isClosed()) {
            return 'cancelled';
        }

        if ($voucher->isExpired()) {
            return 'expired';
        }

        if ($voucher->redeemed_at !== null) {
            return 'redeemed';
        }

        return strtolower((string) $voucher->state->value);
    }
}
