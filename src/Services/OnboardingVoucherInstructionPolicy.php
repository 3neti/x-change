<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use LBHurtado\Voucher\Data\ExecutionInstructionData;

final class OnboardingVoucherInstructionPolicy
{
    public const string ExecutionDriver = 'onboarding_account_provisioning';

    public const string WorkflowKey = 'onboarding.account-provisioning.v1';

    public const string PostRedemptionMode = 'execution_only';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $onboarding = $this->isOnboarding($input);
        Arr::set($input, 'onboarding', $onboarding);

        if (! $onboarding) {
            return $input;
        }

        $this->assertCompatibleExecutionDriver($input);

        $requiresOtp = $this->requiresOtp($input);
        $requiredFields = ['name', 'email', 'mobile'];

        if ($requiresOtp) {
            $requiredFields[] = 'otp';
            Arr::set($input, 'validation.otp', [
                'required' => true,
                'on_failure' => 'block',
            ]);
        }

        Arr::set(
            $input,
            'inputs.fields',
            collect((array) data_get($input, 'inputs.fields', []))
                ->merge($requiredFields)
                ->filter(static fn (mixed $field): bool => is_string($field) && trim($field) !== '')
                ->unique()
                ->values()
                ->all(),
        );
        Arr::set($input, 'execution.schema', ExecutionInstructionData::SCHEMA);
        Arr::set($input, 'execution.driver', self::ExecutionDriver);
        Arr::set($input, 'execution.metadata.onboarding', [
            'workflow_key' => self::WorkflowKey,
            'required_claim_fields' => ['full_name', 'email', 'mobile'],
            'mobile_verification_required' => $requiresOtp,
            'authentication_mode' => 'claimant_handoff',
        ]);
        Arr::set(
            $input,
            'execution.metadata.post_redemption.mode',
            self::PostRedemptionMode,
        );

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function requiresOtp(array $input): bool
    {
        return (bool) config('x-change.onboarding.voucher.require_otp', false)
            || filled(data_get($input, 'cash.validation.mobile'));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function isOnboarding(array $input): bool
    {
        if (array_key_exists('onboarding', $input)) {
            return $input['onboarding'] === true;
        }

        return data_get($input, 'claim.onboarding.mode') === 'required';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertCompatibleExecutionDriver(array $input): void
    {
        $driver = trim((string) data_get($input, 'execution.driver', ''));

        if ($driver === '' || in_array($driver, ['default', self::ExecutionDriver], true)) {
            return;
        }

        throw new InvalidArgumentException(
            "Onboarding Vouchers cannot use execution driver [{$driver}].",
        );
    }
}
