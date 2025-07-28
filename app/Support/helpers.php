<?php

use LBHurtado\Voucher\Enums\VoucherInputField;
use Illuminate\Support\{Arr, Carbon};
use Illuminate\Support\Collection;
use Brick\Money\Money;


if (!function_exists('traverse')) {
    /**
     * Traverse nested arrays/objects using dot notation.
     * Special case: if the node is an array of VoucherInputField enums,
     * and the next segment matches an enum case name, return a boolean
     * indicating presence.
     *
     * @param mixed $model
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function traverse($model, $key, $default = null)
    {
        if (is_null($key)) {
            return $model;
        }

        // Handle top-level array access
        if (is_array($model)) {
            return Arr::get($model, $key, $default);
        }

        // Handle ArrayAccess (like Collection or DotData)
        if ($model instanceof \ArrayAccess && isset($model[$key])) {
            return $model[$key];
        }

        // Traverse dot-notation path
        foreach (explode('.', $key) as $segment) {
            if (is_array($model)) {
                // 🎯 Special handling for VoucherInputField enums in arrays
                if (!empty($model) && $model[0] instanceof VoucherInputField) {
                    $enum = VoucherInputField::tryFrom($segment);
                    $model = in_array($enum, $model, true);
                } else {
                    $model = $model[$segment] ?? null;
                }
            } elseif (is_object($model)) {
                try {
                    $model = $model->{$segment} ?? null;
                } catch (\Throwable $e) {
                    return value($default);
                }
            } else {
                return value($default);
            }
        }

        return $model ?? value($default);
    }
}

if (!function_exists('voucher_totals')) {
    /**
     * Compute total outstanding cash amounts by currency for a collection of vouchers.
     *
     * @param Collection $vouchers
     * @return Collection<string, array{ total: Money, count: int, latest_created_at: string|null }>
     * @todo make this into a DTO
     */
    function voucher_totals(Collection $vouchers): Collection
    {
        // 1. Pair voucher and its cash if cash exists
        $entries = $vouchers
            ->map(fn ($voucher) => $voucher->cash ? [
                'cash' => $voucher->cash,
                'created_at' => $voucher->created_at,
            ] : null)
            ->filter();

        // 2. Group by currency
        $grouped = $entries->groupBy(fn ($entry) =>
        $entry['cash']->amount->getCurrency()->getCurrencyCode()
        );

        // 3. Compute totals per currency group
        return $grouped->map(function (Collection $group) {
            $total = $group->reduce(
                fn (Money $carry, $entry) => $carry->plus($entry['cash']->amount),
                Money::zero($group->first()['cash']->amount->getCurrency())
            );

            $count = $group->count();

            $latest = $group
                ->pluck('created_at')
                ->map(fn ($dt) => Carbon::parse($dt))
                ->sortDesc()
                ->first()?->toDateTimeString();

            return [
                'total' => $total,
                'count' => $count,
                'latest_created_at' => $latest,
            ];
        });
    }
}
