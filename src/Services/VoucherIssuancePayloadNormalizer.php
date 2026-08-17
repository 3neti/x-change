<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use BackedEnum;
use Illuminate\Support\Arr;
use UnitEnum;

class VoucherIssuancePayloadNormalizer
{
    public function __construct(
        private readonly NamedVoucherSliceService $namedSlices,
        private readonly OnboardingVoucherInstructionPolicy $onboarding,
        private readonly RiderStampDesignRegistry $stampDesigns,
    ) {}

    public function normalize(array $input): array
    {
        $input = $this->normalizeEnumValues($input);
        $input = $this->namedSlices->normalizeIssuancePayload($input);
        $input = $this->onboarding->normalize($input);
        $cashValidation = Arr::get($input, 'cash.validation');

        if (! is_array($cashValidation)) {
            Arr::set($input, 'cash.validation', []);
        }

        $input = $this->stampDesigns->materialize($input);
        $input = $this->stampDesigns->normalizeForInstalledVoucherContract($input);

        $flowType = Arr::get($input, 'metadata.flow_type');

        if ($flowType !== 'collectible') {
            return $input;
        }

        $targetAmount = Arr::get($input, 'target_amount')
            ?? Arr::get($input, 'cash.target_amount')
            ?? Arr::get($input, 'cash.amount')
            ?? Arr::get($input, 'amount');

        if ($targetAmount !== null) {
            Arr::set($input, 'target_amount', $targetAmount);
        }

        Arr::set($input, 'cash.amount', 0);

        return $input;
    }

    protected function normalizeEnumValues(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(
            fn (mixed $item): mixed => $this->normalizeEnumValues($item),
            $value,
        );
    }
}
