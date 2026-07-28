<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;

final class PayCodeStampIndicatorResolver
{
    /**
     * @return list<string>
     */
    public function resolve(Voucher $voucher): array
    {
        $instructions = $voucher->instructions->toArray();
        $keys = [$this->outcomeKey($instructions)];

        foreach ($this->fieldNames($voucher) as $field) {
            $keys[] = 'input.'.$field;
        }

        if (filled(data_get($instructions, 'cash.validation.mobile'))) {
            $keys[] = 'validation.mobile';
        }

        if (
            filled(data_get($instructions, 'cash.validation.mobile_verification'))
            || data_get($instructions, 'validation.otp.required') === true
        ) {
            $keys[] = 'validation.otp';
        }

        if (
            data_get($instructions, 'validation.face_match.required') === true
        ) {
            $keys[] = 'validation.identity';
        }

        if (
            data_get($instructions, 'validation.selfie.required') === true
        ) {
            $keys[] = 'validation.selfie';
        }

        if (data_get($instructions, 'validation.signature.required') === true) {
            $keys[] = 'validation.signature';
        }

        if (
            data_get($instructions, 'validation.location.required') === true
            || filled(data_get($instructions, 'cash.validation.location'))
        ) {
            $keys[] = 'validation.location';
        }

        if (data_get($instructions, 'validation.time.required') === true) {
            $keys[] = 'validation.time';
        }

        if (filled(data_get($instructions, 'cash.slice_mode'))) {
            $keys[] = 'claim.multiple';
        }

        return array_slice(array_values(array_unique($keys)), 0, 6);
    }

    /**
     * @param  array<string, mixed>  $instructions
     */
    private function outcomeKey(array $instructions): string
    {
        if (
            data_get($instructions, 'claim.default_outcome')
                === 'account_funding'
        ) {
            return 'outcome.account_funding';
        }

        return match (data_get($instructions, 'voucher_type')) {
            'payable' => 'outcome.collect_payment',
            'settlement' => 'outcome.settlement',
            default => 'outcome.provider_disbursement',
        };
    }

    /**
     * @return list<string>
     */
    private function fieldNames(Voucher $voucher): array
    {
        return collect(
            (array) data_get($voucher->instructions, 'inputs.fields', []),
        )->map(static function (mixed $field): ?string {
            if (is_string($field)) {
                return mb_strtolower(trim($field));
            }

            $value = data_get($field, 'value');

            return is_string($value) ? mb_strtolower(trim($value)) : null;
        })->filter()
            ->values()
            ->all();
    }
}
