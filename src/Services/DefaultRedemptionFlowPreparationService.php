<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Data\Redemption\RedemptionFlowData;
use LBHurtado\XChange\Data\Redemption\RedemptionRequirementsData;
use LBHurtado\XChange\Data\Redemption\VoucherRedemptionProfileData;
use RuntimeException;

class DefaultRedemptionFlowPreparationService implements RedemptionFlowPreparationContract
{
    public function __construct(
        protected Container $container,
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    public function prepare(Voucher $voucher): PrepareRedemptionResultData
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if ($capabilities->type->isSettlement()) {
            throw new RuntimeException(
                'Settlement vouchers cannot prepare ordinary outward claim flow until settlement preparation is implemented.'
            );
        }

        if (! $capabilities->can_disburse) {
            throw new RuntimeException(
                "Voucher flow [{$capabilities->type->value}] cannot prepare outward claim flow."
            );
        }

        $messages = [];
        $canStart = true;
        $entryRoute = 'disburse';

        if (method_exists($voucher, 'isRedeemed') && $voucher->isRedeemed()) {
            $canWithdraw = method_exists($voucher, 'canWithdraw') ? (bool) $voucher->canWithdraw() : false;

            if ($canWithdraw) {
                $entryRoute = 'withdraw';
            } else {
                $canStart = false;
                $messages[] = 'This voucher has already been redeemed.';
            }
        }

        if (method_exists($voucher, 'isExpired') && $voucher->isExpired()) {
            $canStart = false;
            $messages[] = 'This voucher has expired.';
        }

        if (
            isset($voucher->starts_at)
            && $voucher->starts_at !== null
            && method_exists($voucher->starts_at, 'isFuture')
            && $voucher->starts_at->isFuture()
        ) {
            $canStart = false;
            $messages[] = 'This voucher is not yet active.';
        }

        $instructions = $voucher->instructions;
        $inputFields = $this->normalizeInputFields($instructions);

        $requiredValidation = $this->extractRequiredValidation($instructions);

        $requirements = new RedemptionRequirementsData(
            required_inputs: $inputFields,
            required_validation: $requiredValidation,
            has_kyc: in_array('kyc', $inputFields, true),
            has_otp: in_array('otp', $inputFields, true),
            has_location: in_array('location', $inputFields, true) || in_array('map', $inputFields, true),
            has_selfie: in_array('selfie', $inputFields, true),
            has_signature: in_array('signature', $inputFields, true),
            has_bio_fields: $this->hasBioFields($inputFields),
        );

        $profile = new VoucherRedemptionProfileData(
            instrument_kind: $this->resolveInstrumentKind($instructions),
            redemption_mode: $entryRoute === 'withdraw' ? 'withdraw' : 'disburse',
            requires_form_flow: true,
            is_divisible: $this->isDivisible($voucher),
            can_withdraw: method_exists($voucher, 'canWithdraw') ? (bool) $voucher->canWithdraw() : false,
            slice_mode: method_exists($voucher, 'getSliceMode') ? $voucher->getSliceMode() : null,
            driver_name: 'voucher-redemption',
        );

        $flow = new RedemptionFlowData(
            driver_name: 'voucher-redemption',
            driver_version: '1.0',
            reference_id_template: 'disburse-{{ code }}-{{ timestamp }}',
            on_complete_callback: url('/disburse/'.$voucher->code.'/complete'),
            on_cancel_callback: url('/disburse'),
            step_names: $this->resolveStepNames($requirements),
            step_handlers: $this->resolveStepHandlers($requirements),
            flow_instructions: $this->resolveFlowInstructions($voucher),
        );

        return new PrepareRedemptionResultData(
            voucher_code: (string) $voucher->code,
            can_start: $canStart,
            entry_route: $entryRoute,
            profile: $profile,
            requirements: $requirements,
            flow: $flow,
            messages: $messages,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractRequiredValidation(mixed $instructions): array
    {
        $validation = data_get($instructions, 'cash.validation');

        if ($validation === null) {
            return [];
        }

        $required = [];

        $secret = data_get($validation, 'secret');
        if ($secret) {
            $required['secret'] = true;
        }

        $mobile = data_get($validation, 'mobile');
        if ($mobile) {
            $required['mobile'] = $mobile;
        }

        $payable = data_get($validation, 'payable');
        if ($payable) {
            $required['payable'] = $payable;
        }

        $location = data_get($validation, 'location');
        if ($location) {
            $required['location'] = [
                'location' => $location,
                'radius' => data_get($validation, 'radius'),
            ];
        }

        return $required;
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function hasBioFields(array $fields): bool
    {
        $bioFields = [
            'name',
            'email',
            'birth_date',
            'address',
            'reference_code',
            'gross_monthly_income',
        ];

        return count(array_intersect($fields, $bioFields)) > 0;
    }

    protected function resolveInstrumentKind(mixed $instructions): string
    {
        if (data_get($instructions, 'cash.validation.payable')) {
            return 'payable';
        }

        if (Arr::has((array) data_get($instructions, 'metadata', []), 'settlement')) {
            return 'settlement';
        }

        return 'redeemable';
    }

    protected function isDivisible(Voucher $voucher): bool
    {
        if (method_exists($voucher, 'getSliceMode')) {
            return $voucher->getSliceMode() !== null;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveStepNames(RedemptionRequirementsData $requirements): array
    {
        $steps = ['wallet'];

        if ($requirements->has_kyc) {
            $steps[] = 'kyc';
        }

        if ($requirements->has_bio_fields) {
            $steps[] = 'bio';
        }

        if ($requirements->has_otp) {
            $steps[] = 'otp';
        }

        if ($requirements->has_location) {
            $steps[] = 'location';
        }

        if ($requirements->has_selfie) {
            $steps[] = 'selfie';
        }

        if ($requirements->has_signature) {
            $steps[] = 'signature';
        }

        array_unshift($steps, 'splash');

        return $steps;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveStepHandlers(RedemptionRequirementsData $requirements): array
    {
        $handlers = [
            'splash' => 'splash',
            'wallet' => 'form',
        ];

        if ($requirements->has_kyc) {
            $handlers['kyc'] = 'kyc';
        }

        if ($requirements->has_bio_fields) {
            $handlers['bio'] = 'form';
        }

        if ($requirements->has_otp) {
            $handlers['otp'] = 'otp';
        }

        if ($requirements->has_location) {
            $handlers['location'] = 'location';
        }

        if ($requirements->has_selfie) {
            $handlers['selfie'] = 'selfie';
        }

        if ($requirements->has_signature) {
            $handlers['signature'] = 'signature';
        }

        return $handlers;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveFlowInstructions(Voucher $voucher): ?array
    {
        if (! $this->container->bound('form-flow.driver')) {
            return null;
        }

        $driverService = $this->container->make('form-flow.driver');

        if (! method_exists($driverService, 'transform')) {
            return null;
        }

        $instructions = $driverService->transform($voucher);

        if (is_object($instructions) && method_exists($instructions, 'toArray')) {
            /** @var array<string, mixed> $result */
            $result = $instructions->toArray();

            return $result;
        }

        return is_array($instructions) ? $instructions : null;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeInputFields(mixed $instructions): array
    {
        return array_values(array_filter(array_map(
            static function ($field): ?string {
                if (is_string($field)) {
                    return $field;
                }

                if ($field instanceof \BackedEnum) {
                    return (string) $field->value;
                }

                if ($field instanceof \UnitEnum) {
                    return $field->name;
                }

                return $field !== null ? (string) $field : null;
            },
            (array) data_get($instructions, 'inputs.fields', [])
        )));
    }
}
