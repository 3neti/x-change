<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Collection;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherCollection;

final class PaymentReceiptReadModel
{
    /**
     * @return array{
     *     pay_code: string,
     *     amount_paid_minor: int,
     *     currency: string,
     *     completed_at: ?string,
     *     payments: list<array{
     *         collection_number: int,
     *         amount_paid_minor: int,
     *         provider: string,
     *         receipt_reference: string,
     *         completed_at: ?string
     *     }>
     * }|null
     */
    public function forVoucher(Voucher $voucher, string $currency): ?array
    {
        $collections = $this->collectionsForVoucher($voucher);

        if ($collections->isEmpty()) {
            return null;
        }

        $latest = $collections
            ->sortByDesc(fn (VoucherCollection $collection): int => $collection->completed_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'pay_code' => (string) $voucher->code,
            'amount_paid_minor' => (int) $collections->sum('collected_amount_minor'),
            'currency' => $currency,
            'completed_at' => $latest?->completed_at?->toIso8601String(),
            'payments' => $collections
                ->map(fn (VoucherCollection $collection): array => [
                    'collection_number' => (int) $collection->collection_number,
                    'amount_paid_minor' => (int) $collection->collected_amount_minor,
                    'provider' => (string) ($collection->provider ?: 'Recorded payment'),
                    'receipt_reference' => sprintf(
                        'PAY-%s-%02d',
                        (string) $voucher->code,
                        $collection->collection_number,
                    ),
                    'completed_at' => $collection->completed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{amount_paid_minor: int, currency: string, completed_at: ?string}|null
     */
    public function summaryForVoucher(Voucher $voucher, string $currency): ?array
    {
        $receipt = $this->forVoucher($voucher, $currency);

        if ($receipt === null) {
            return null;
        }

        return [
            'amount_paid_minor' => $receipt['amount_paid_minor'],
            'currency' => $receipt['currency'],
            'completed_at' => $receipt['completed_at'],
        ];
    }

    /**
     * @return Collection<int, VoucherCollection>
     */
    private function collectionsForVoucher(Voucher $voucher): Collection
    {
        return VoucherCollection::query()
            ->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['collected', 'succeeded'])
            ->orderBy('collection_number')
            ->get([
                'collection_number',
                'collected_amount_minor',
                'provider',
                'completed_at',
            ]);
    }
}
