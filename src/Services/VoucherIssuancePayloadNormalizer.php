<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VoucherIssuancePayloadNormalizer
{
    public function __construct(
        private readonly NamedVoucherSliceService $namedSlices,
        private readonly OnboardingVoucherInstructionPolicy $onboarding,
        private readonly RiderStampDesignRegistry $stampDesigns,
        private readonly StoredValueIssuancePolicy $storedValue,
        private readonly ValueFlowIssuancePolicy $valueFlows,
    ) {}

    public function normalize(array $input): array
    {
        $input = $this->normalizeEnumValues($input);
        $input = app(SettlementRepaymentPolicy::class)->normalize($input);
        if (isset($input['fee_bearer'])) {
            if (! in_array($input['fee_bearer'], ['issuer', 'payer'], true)
                || ! in_array($input['voucher_type'] ?? null, ['payable', 'settlement'], true)) {
                throw ValidationException::withMessages([
                    'fee_bearer' => 'Fee bearer must be issuer or payer on a payable or settlement voucher.',
                ]);
            }
        }
        $input = $this->storedValue->normalize($input);
        $input = $this->valueFlows->normalize($input);
        $input = $this->namedSlices->normalizeIssuancePayload($input);
        $input = $this->onboarding->normalize($input);
        $cashValidation = Arr::get($input, 'cash.validation');

        if (! is_array($cashValidation)) {
            Arr::set($input, 'cash.validation', []);
        }

        $input = $this->stampDesigns->materialize($input);
        $input = $this->stampDesigns->normalizeForInstalledVoucherContract($input);

        $voucherType = Arr::get($input, 'voucher_type');
        $flowType = Arr::get($input, 'metadata.flow_type');

        if ($voucherType !== 'payable' && $flowType !== 'collectible') {
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
