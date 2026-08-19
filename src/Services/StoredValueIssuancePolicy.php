<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;

final class StoredValueIssuancePolicy
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $policy = data_get($input, 'stored_value');
        $enabled = is_array($policy) && ($policy['enabled'] ?? false) === true;
        $driver = data_get($input, 'execution.driver');

        if (! $enabled) {
            if ($driver === 'stored_value') {
                $this->reject(
                    'stored_value.enabled',
                    'Stored value must be requested through the governed Reusable Balance policy.',
                );
            }

            Arr::forget($input, 'stored_value');

            return $input;
        }

        $this->assertCompatibleProduct($input);

        $currency = strtoupper(trim((string) data_get($input, 'cash.currency', 'PHP')));

        if ($currency !== 'PHP') {
            $this->reject(
                'cash.currency',
                'Reusable Balance currently supports PHP only.',
            );
        }

        $initialBalance = $this->toMinor(
            data_get($input, 'cash.amount'),
            'cash.amount',
        );
        $replenishable = ($policy['replenishable'] ?? false) === true;

        if ($replenishable) {
            $this->reject(
                'stored_value.replenishable',
                'Reusable Balance replenishment is unavailable until its funding authority is commissioned.',
            );
        }

        $maximumBalance = $this->toMinor(
            $policy['maximum_balance'] ?? data_get($input, 'cash.amount'),
            'stored_value.maximum_balance',
        );
        $otpRequiredAbove = $this->optionalMinor(
            $policy['otp_required_above'] ?? null,
            'stored_value.otp_required_above',
        );

        if ($maximumBalance < $initialBalance) {
            $this->reject(
                'stored_value.maximum_balance',
                'Maximum balance cannot be lower than the starting balance.',
            );
        }

        if (! $replenishable && $maximumBalance !== $initialBalance) {
            $this->reject(
                'stored_value.maximum_balance',
                'A non-replenishable balance must use the starting balance as its maximum.',
            );
        }

        if ($otpRequiredAbove > $maximumBalance) {
            $this->reject(
                'stored_value.otp_required_above',
                'OTP threshold cannot be higher than the maximum balance.',
            );
        }

        $canonicalExecution = [
            'schema' => ExecutionInstructionData::SCHEMA,
            'driver' => 'stored_value',
            'metadata' => [
                'stored_value' => [
                    'initial_balance' => $initialBalance,
                    'max_balance' => $maximumBalance,
                    'replenishable' => $replenishable,
                    'otp_required_above' => $otpRequiredAbove,
                ],
                'post_redemption' => [
                    'mode' => OnboardingVoucherInstructionPolicy::PostRedemptionMode,
                ],
            ],
        ];
        $submittedExecution = data_get($input, 'execution');

        if (
            is_array($submittedExecution)
            && $submittedExecution !== []
            && $submittedExecution !== $canonicalExecution
        ) {
            $this->reject(
                'execution',
                'Reusable Balance execution instructions are compiled by the server and cannot be overridden.',
            );
        }

        Arr::set($input, 'execution', $canonicalExecution);

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertCompatibleProduct(array $input): void
    {
        foreach ([
            'cash.slice_mode',
            'cash.slices',
            'cash.max_slices',
            'cash.min_withdrawal',
            'cash.settlement_rail',
        ] as $field) {
            if (data_get($input, $field) !== null) {
                $this->reject(
                    $field,
                    'Reusable Balance cannot be combined with cash slices or a transfer network.',
                );
            }
        }

        if (data_get($input, 'onboarding') === true) {
            $this->reject(
                'onboarding',
                'Invitation ownership for Reusable Balance is not commissioned yet.',
            );
        }

        if (data_get($input, 'claim.default_outcome') === 'account_funding') {
            $this->reject(
                'claim.default_outcome',
                'Account Funding and Reusable Balance cannot be combined.',
            );
        }

        if (data_get($input, 'voucher_type', 'redeemable') !== 'redeemable') {
            $this->reject(
                'voucher_type',
                'Reusable Balance requires a redeemable Pay Code.',
            );
        }
    }

    private function toMinor(mixed $value, string $field): int
    {
        try {
            return MajorCurrencyAmount::toMinor((string) $value, 'PHP');
        } catch (InvalidArgumentException $exception) {
            $this->reject($field, $exception->getMessage());
        }
    }

    private function optionalMinor(mixed $value, string $field): int
    {
        if ($value === null || $value === '' || (float) $value === 0.0) {
            return 0;
        }

        return $this->toMinor($value, $field);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
