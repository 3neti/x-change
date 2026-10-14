<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\VoucherClaim;

final class PublicBatchAnalyticsReadModel
{
    private const AllowedPeriods = ['today', '7d', '30d', 'all'];

    /** @return array<string, mixed> */
    public function present(?string $period = null): array
    {
        $period = in_array($period, self::AllowedPeriods, true) ? $period : '30d';
        $cacheKey = 'x-change.public-batch-analytics.'.$period;

        return Cache::remember($cacheKey, now()->addSeconds(60), fn (): array => $this->build($period));
    }

    /** @return array<string, mixed> */
    private function build(string $period): array
    {
        $since = match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            default => null,
        };

        $batches = IssuanceBatch::query()
            ->where('source', 'public.auto-generate.batch')
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->with('items:id,batch_id,status,amount_minor,pay_code,voucher_id,issued_at')
            ->get();

        $items = $batches->flatMap(fn (IssuanceBatch $batch) => $batch->items)->reject(fn ($item): bool => $item->status === 'invalid')->values();
        $voucherIds = $items->pluck('voucher_id')->filter()->unique()->values();
        $vouchers = $voucherIds->isEmpty()
            ? collect()
            : Voucher::query()->whereKey($voucherIds->all())->get(['id', 'redeemed_at', 'closed_at', 'state'])->keyBy('id');
        $claims = $voucherIds->isEmpty()
            ? collect()
            : VoucherClaim::query()->whereIn('voucher_id', $voucherIds->all())->latest('id')->get()->unique('voucher_id')->keyBy('voucher_id');

        $itemFacts = $items->map(function ($item) use ($vouchers, $claims): array {
            $voucher = $vouchers->get($item->voucher_id);
            $claim = $claims->get($item->voucher_id);
            $claimStatus = strtolower((string) ($claim?->status ?? ''));
            $claimSucceeded = in_array($claimStatus, ['succeeded', 'paid', 'withdrawn'], true);
            $claimedAt = $voucher?->redeemed_at ?? ($claimSucceeded ? ($claim?->completed_at ?? $claim?->attempted_at) : null);
            $issued = $item->pay_code !== null || $item->issued_at !== null;
            $claimed = $voucher?->redeemed_at !== null || $voucher?->closed_at !== null || $claimSucceeded;

            return [
                'amount_minor' => (int) $item->amount_minor,
                'issued' => $issued,
                'claimed' => $claimed,
                'issued_at' => $item->issued_at,
                'claimed_at' => $claimedAt,
            ];
        });

        $issued = $itemFacts->where('issued', true);
        $claimed = $itemFacts->where('claimed', true);
        $fundingOrderIds = $batches->pluck('funding_order_id')->filter()->values();
        $methods = $fundingOrderIds->isEmpty()
            ? collect()
            : Checkout::query()->whereIn('funding_order_id', $fundingOrderIds)->pluck('selected_method', 'funding_order_id');

        return [
            'period' => $period,
            'available_periods' => [
                ['key' => 'today', 'label' => 'Today'],
                ['key' => '7d', 'label' => 'Last 7 days'],
                ['key' => '30d', 'label' => 'Last 30 days'],
                ['key' => 'all', 'label' => 'All time'],
            ],
            'totals' => [
                'batches' => $batches->count(),
                'recipients' => $items->count(),
                'issued' => $issued->count(),
                'issued_value_minor' => $issued->sum('amount_minor'),
                'claimed' => $claimed->count(),
                'claimed_value_minor' => $claimed->sum('amount_minor'),
                'claim_rate' => $issued->count() === 0 ? 0 : round(($claimed->count() / $issued->count()) * 100, 1),
            ],
            'activity' => $this->activity($itemFacts, $since),
            'lifecycle' => [
                ['key' => 'issued', 'label' => 'Issued', 'value' => $issued->count()],
                ['key' => 'claimed', 'label' => 'Claimed', 'value' => $claimed->count()],
                ['key' => 'pending', 'label' => 'Pending', 'value' => $itemFacts->filter(fn (array $item): bool => ! $item['issued'])->count()],
            ],
            'funding_methods' => $this->fundingMethods($batches, $methods),
            'batch_sizes' => $this->batchSizes($batches),
            'batch_completion' => $this->completion($batches),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function activity(Collection $items, ?CarbonInterface $since): array
    {
        $firstIssuedAt = $items->pluck('issued_at')->filter()->sort()->first();
        $start = $since?->copy()->startOfDay()
            ?? ($firstIssuedAt === null ? now()->startOfDay() : Carbon::parse($firstIssuedAt)->startOfDay());
        $end = now()->startOfDay();
        $days = [];
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days[$key] = ['date' => $key, 'issued' => 0, 'claimed' => 0];
        }

        foreach ($items as $item) {
            if ($item['issued_at'] !== null) {
                $key = Carbon::parse($item['issued_at'])->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['issued']++;
                }
            }
            if ($item['claimed_at'] !== null) {
                $key = Carbon::parse($item['claimed_at'])->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['claimed']++;
                }
            }
        }

        return array_values($days);
    }

    private function fundingMethods(Collection $batches, Collection $methods): array
    {
        return $batches->groupBy(fn (IssuanceBatch $batch): string => (string) ($methods->get($batch->funding_order_id) ?: 'unknown'))
            ->map(fn ($group, string $key): array => ['key' => $key, 'label' => str($key)->replace('_', ' ')->title()->toString(), 'value' => $group->count()])
            ->values()->all();
    }

    private function batchSizes(Collection $batches): array
    {
        $ranges = ['1–2' => 0, '3–10' => 0, '11–50' => 0, '51+' => 0];
        foreach ($batches as $batch) {
            $count = (int) $batch->valid_item_count;
            $key = $count <= 2 ? '1–2' : ($count <= 10 ? '3–10' : ($count <= 50 ? '11–50' : '51+'));
            $ranges[$key]++;
        }

        return collect($ranges)->map(fn (int $value, string $key): array => ['label' => $key, 'value' => $value])->values()->all();
    }

    private function completion(Collection $batches): array
    {
        $ranges = ['Under 1 min' => 0, '1–5 min' => 0, '5–15 min' => 0, '15+ min' => 0];
        foreach ($batches as $batch) {
            if ($batch->issued_at === null) {
                continue;
            }
            $seconds = $batch->created_at->diffInSeconds($batch->issued_at);
            $key = $seconds < 60 ? 'Under 1 min' : ($seconds < 300 ? '1–5 min' : ($seconds < 900 ? '5–15 min' : '15+ min'));
            $ranges[$key]++;
        }

        return collect($ranges)->map(fn (int $value, string $key): array => ['label' => $key, 'value' => $value])->values()->all();
    }
}
