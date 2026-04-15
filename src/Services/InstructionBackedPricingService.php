<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;

class InstructionBackedPricingService implements PricingServiceContract
{
    private const DEBUG = true;

    protected array $excludedFields = [
        'count',
        'mask',
        'ttl',
        'starts_at',
        'expires_at',
        'cash.slice_fee', // handled separately
    ];

    public function estimate(VoucherInstructionsData $instructions): array
    {
        $charges = $this->evaluate($instructions);

        $componentsMinor = [
            'cash' => 0,
            'kyc' => 0,
            'otp' => 0,
            'selfie' => 0,
            'signature' => 0,
            'location' => 0,
            'webhook' => 0,
            'email_feedback' => 0,
            'sms_feedback' => 0,
            'rider' => 0,
            'validation' => 0,
            'input_fields' => 0,
            'base' => 0,
        ];

        $baseFeeMinor = 0;

        foreach ($charges as $charge) {
            $index = (string) ($charge['index'] ?? '');
            $priceMinor = (int) ($charge['price_minor'] ?? 0);

            if ($index === '') {
                continue;
            }

            if ($index === 'base_fee') {
                $baseFeeMinor += $priceMinor;

                continue;
            }

            $component = $this->mapInstructionIndexToEstimateComponent($index);

            if ($component !== null && array_key_exists($component, $componentsMinor)) {
                $componentsMinor[$component] += $priceMinor;
            }
        }

        $currency = $this->resolveCurrency($charges);

        return [
            'currency' => $currency,
            'base_fee_minor' => $baseFeeMinor,
            'base_fee' => $this->minorToMajorFloat($baseFeeMinor, $currency),
            'components_minor' => $componentsMinor,
            'components' => collect($componentsMinor)
                ->map(fn (int $minor) => $this->minorToMajorFloat($minor, $currency))
                ->all(),
            'total_minor' => $baseFeeMinor + array_sum($componentsMinor),
            'total' => $this->minorToMajorFloat($baseFeeMinor + array_sum($componentsMinor), $currency),
            'charges' => $charges->values()->all(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function evaluate(VoucherInstructionsData $source): Collection
    {
        $charges = collect();
        $items = $this->instructionItems();
        $count = max(1, (int) ($source->count ?? 1));
        $cashAmount = $source->cash?->amount ?? 0;

        if (self::DEBUG) {
            Log::debug('[InstructionBackedPricingService] Starting evaluation', [
                'instruction_items_count' => $items->count(),
                'count' => $count,
                'cash_amount' => $cashAmount,
                'source_data' => $source->toArray(),
            ]);
        }

        foreach ($items as $item) {
            $index = (string) $item->index;

            if (in_array($index, $this->excludedFields, true)) {
                continue;
            }

            $value = $this->resolveInstructionValue($source, $index);
            $unitPrice = $this->moneyFromStoredMinor($item->price, $item->currency ?? 'PHP');

            if (str_starts_with($index, 'validation.')) {
                $shouldCharge = $this->shouldChargeValidation($value, $unitPrice);

                if (self::DEBUG) {
                    Log::debug("[InstructionBackedPricingService] Validation item: {$index}", [
                        'value' => $value,
                        'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
                        'should_charge' => $shouldCharge,
                    ]);
                }
            } else {
                $shouldCharge = $this->shouldChargeValue($value, $unitPrice);
            }

            if (self::DEBUG) {
                Log::debug("[InstructionBackedPricingService] Evaluating: {$index}", [
                    'value' => $value,
                    'type' => gettype($value),
                    'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
                    'unit_price' => $this->moneyToMajorFloat($unitPrice),
                    'should_charge' => $shouldCharge,
                ]);
            }

            if (! $shouldCharge) {
                continue;
            }

            $meta = $this->normalizeMeta($item->meta ?? null);
            $label = $meta['label'] ?? $item->name ?? $index;
            $payCount = 1;
            $totalPrice = $unitPrice->multipliedBy($count, RoundingMode::HALF_UP);

            if (self::DEBUG) {
                Log::info('[InstructionBackedPricingService] ✅ Chargeable instruction', [
                    'index' => $index,
                    'label' => $label,
                    'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
                    'unit_price' => $this->moneyToMajorFloat($unitPrice),
                    'quantity' => $count,
                    'total_price_minor' => $this->moneyToMinorInt($totalPrice),
                    'total_price' => $this->moneyToMajorFloat($totalPrice),
                ]);
            }

            $charges->push([
                'index' => $index,
                'value' => $value,
                'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
                'unit_price' => $this->moneyToMajorFloat($unitPrice),
                'quantity' => $count,
                'price_minor' => $this->moneyToMinorInt($totalPrice),
                'price' => $this->moneyToMajorFloat($totalPrice),
                'currency' => $unitPrice->getCurrency()->getCurrencyCode(),
                'label' => $label,
                'pay_count' => $payCount,
                'type' => $item->type ?? null,
                'meta' => $meta,
            ]);
        }

        $charges = $charges->merge(
            $this->evaluateSliceFee($source, $count, $items)
        );

        if (self::DEBUG) {
            Log::info('[InstructionBackedPricingService] Evaluation complete', [
                'total_items_charged' => $charges->count(),
                'total_amount_minor' => $charges->sum('price_minor'),
                'total_amount' => $this->minorToMajorFloat(
                    (int) $charges->sum('price_minor'),
                    $this->resolveCurrency($charges)
                ),
            ]);
        }

        return $charges->values();
    }

    protected function resolveInstructionValue(VoucherInstructionsData $source, string $index): mixed
    {
        if (str_starts_with($index, 'inputs.fields.')) {
            $fieldName = str_replace('inputs.fields.', '', $index);
            $selectedFieldsRaw = data_get($source, 'inputs.fields', []);

            $selectedFields = collect($selectedFieldsRaw)
                ->map(function ($field) {
                    if (is_array($field) || is_object($field)) {
                        return collect((array) $field)->values()->first();
                    }

                    return $field;
                })
                ->filter()
                ->map(fn ($field) => strtoupper((string) $field))
                ->values()
                ->all();

            $isSelected = in_array(strtoupper($fieldName), $selectedFields, true);

            if (self::DEBUG) {
                Log::debug("[InstructionBackedPricingService] Checking input field: {$fieldName}", [
                    'selectedFieldsRaw' => $selectedFieldsRaw,
                    'selectedFieldsExtracted' => $selectedFields,
                    'isSelected' => $isSelected,
                ]);
            }

            return $isSelected ? $fieldName : null;
        }

        if (str_starts_with($index, 'cash.validation.')) {
            $fieldName = str_replace('cash.validation.', '', $index);
            $value = data_get($source, "cash.validation.{$fieldName}");

            if (self::DEBUG) {
                Log::debug("[InstructionBackedPricingService] Checking cash.validation field: {$fieldName}", [
                    'value' => $value,
                ]);
            }

            return $value;
        }

        return data_get($source, $index);
    }

    protected function shouldChargeValidation(mixed $value, Money $unitPrice): bool
    {
        if ($unitPrice->isZero()) {
            return false;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            $valueArray = $value->toArray();
        } elseif (is_array($value)) {
            $valueArray = $value;
        } else {
            $valueArray = [];
        }

        if (isset($valueArray['required'])) {
            return $valueArray['required'] === true;
        }

        if (isset($valueArray['window']) || isset($valueArray['limit_minutes'])) {
            return ! empty($valueArray['window']) || ! empty($valueArray['limit_minutes']);
        }

        return false;
    }

    protected function shouldChargeValue(mixed $value, Money $unitPrice): bool
    {
        if ($unitPrice->isZero()) {
            return false;
        }

        $isTruthyString = is_string($value) && trim($value) !== '';
        $isTruthyBoolean = is_bool($value) && $value === true;
        $isTruthyInteger = is_int($value) && $value > 0;
        $isTruthyFloat = is_float($value) && $value > 0.0;
        $isTruthyObject = (is_array($value) || is_object($value)) && ! empty((array) $value);

        return $isTruthyString || $isTruthyBoolean || $isTruthyInteger || $isTruthyFloat || $isTruthyObject;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function evaluateSliceFee(VoucherInstructionsData $source, int $count, Collection $items): Collection
    {
        $charges = collect();

        if ($source->cash?->slice_mode === null) {
            return $charges;
        }

        $additionalSlices = match ($source->cash->slice_mode) {
            'fixed' => max(0, ($source->cash->slices ?? 1) - 1),
            'open' => max(0, ($source->cash->max_slices ?? 1) - 1),
            default => 0,
        };

        if ($additionalSlices <= 0) {
            return $charges;
        }

        $sliceFeeItem = $items->firstWhere('index', 'cash.slice_fee');

        if (! $sliceFeeItem) {
            return $charges;
        }

        $unitPrice = $this->moneyFromStoredMinor($sliceFeeItem->price, $sliceFeeItem->currency ?? 'PHP');

        if ($unitPrice->isZero()) {
            return $charges;
        }

        $meta = $this->normalizeMeta($sliceFeeItem->meta ?? null);
        $label = $meta['label'] ?? $sliceFeeItem->name ?? 'Slice Fee';
        $lineQuantity = $count * $additionalSlices;
        $totalPrice = $unitPrice->multipliedBy($lineQuantity, RoundingMode::HALF_UP);

        if (self::DEBUG) {
            Log::info('[InstructionBackedPricingService] ✅ Slice fee charge', [
                'index' => 'cash.slice_fee',
                'label' => $label,
                'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
                'unit_price' => $this->moneyToMajorFloat($unitPrice),
                'additional_slices' => $additionalSlices,
                'quantity' => $count,
                'line_quantity' => $lineQuantity,
                'total_price_minor' => $this->moneyToMinorInt($totalPrice),
                'total_price' => $this->moneyToMajorFloat($totalPrice),
            ]);
        }

        $charges->push([
            'index' => 'cash.slice_fee',
            'value' => $additionalSlices,
            'unit_price_minor' => $this->moneyToMinorInt($unitPrice),
            'unit_price' => $this->moneyToMajorFloat($unitPrice),
            'quantity' => $count,
            'price_minor' => $this->moneyToMinorInt($totalPrice),
            'price' => $this->moneyToMajorFloat($totalPrice),
            'currency' => $unitPrice->getCurrency()->getCurrencyCode(),
            'label' => $label,
            'pay_count' => $additionalSlices,
            'slice_count' => $additionalSlices,
            'type' => $sliceFeeItem->type ?? null,
            'meta' => $meta,
        ]);

        return $charges;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function instructionItems(): Collection
    {
        return DB::table('instruction_items')
            ->orderBy('index')
            ->get();
    }

    protected function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function mapInstructionIndexToEstimateComponent(string $index): ?string
    {
        return match (true) {
            $index === 'cash.amount',
                $index === 'cash.slice_fee',
            str_starts_with($index, 'voucher_type.') => 'cash',

            $index === 'inputs.fields.kyc' => 'kyc',
            $index === 'inputs.fields.otp' => 'otp',
            $index === 'inputs.fields.selfie' => 'selfie',
            $index === 'inputs.fields.signature' => 'signature',
            $index === 'inputs.fields.location' => 'location',

            $index === 'feedback.webhook' => 'webhook',
            $index === 'feedback.email' => 'email_feedback',
            $index === 'feedback.mobile' => 'sms_feedback',

            str_starts_with($index, 'rider.') => 'rider',
            str_starts_with($index, 'validation.') => 'validation',
            str_starts_with($index, 'cash.validation.') => 'validation',
            str_starts_with($index, 'inputs.fields.') => 'input_fields',

            default => null,
        };
    }

    protected function moneyFromStoredMinor(mixed $minor, string $currency): Money
    {
        return Money::ofMinor((int) $minor, $currency);
    }

    protected function moneyToMinorInt(Money $money): int
    {
        return $money->getMinorAmount()->toInt();
    }

    protected function moneyToMajorFloat(Money $money): float
    {
        return $money->getAmount()->toFloat();
    }

    protected function minorToMajorFloat(int $minor, string $currency): float
    {
        return Money::ofMinor($minor, $currency)->getAmount()->toFloat();
    }

    protected function resolveCurrency(Collection $charges): string
    {
        $currency = (string) ($charges->first()['currency'] ?? 'PHP');

        return $currency !== '' ? $currency : 'PHP';
    }
}
