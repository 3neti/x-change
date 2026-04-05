<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;

class PricingService implements PricingServiceContract
{
    public function estimate(VoucherInstructionsData $instructions): array
    {
        $configuredComponents = (array) config('x-change.pricing.components', []);
        $currency = (string) config('x-change.pricing.currency', 'PHP');
        $baseFee = (float) config('x-change.pricing.base_fee', 0.0);

        $applied = [
            'cash' => $this->componentFee('cash', $configuredComponents),
            'kyc' => $this->hasKyc($instructions)
                ? $this->componentFee('kyc', $configuredComponents)
                : 0.0,
            'otp' => $this->hasOtp($instructions)
                ? $this->componentFee('otp', $configuredComponents)
                : 0.0,
            'selfie' => $this->hasSelfie($instructions)
                ? $this->componentFee('selfie', $configuredComponents)
                : 0.0,
            'signature' => $this->hasSignature($instructions)
                ? $this->componentFee('signature', $configuredComponents)
                : 0.0,
            'location' => $this->hasLocation($instructions)
                ? $this->componentFee('location', $configuredComponents)
                : 0.0,
            'webhook' => $this->hasWebhook($instructions)
                ? $this->componentFee('webhook', $configuredComponents)
                : 0.0,
            'email_feedback' => $this->hasEmailFeedback($instructions)
                ? $this->componentFee('email_feedback', $configuredComponents)
                : 0.0,
            'sms_feedback' => $this->hasSmsFeedback($instructions)
                ? $this->componentFee('sms_feedback', $configuredComponents)
                : 0.0,
        ];

        $total = $baseFee + array_sum($applied);

        return [
            'currency' => $currency,
            'base_fee' => $baseFee,
            'components' => $applied,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string,mixed>  $configuredComponents
     */
    protected function componentFee(string $key, array $configuredComponents): float
    {
        return (float) ($configuredComponents[$key] ?? 0.0);
    }

    /**
     * @return array<int, string>
     */
    /**
     * @return array<int, string>
     */
    protected function fieldNames(VoucherInstructionsData $instructions): array
    {
        $fields = (array) data_get($instructions, 'inputs.fields', []);

        $normalized = array_map(function ($field): ?string {
            if (is_string($field)) {
                return strtolower($field);
            }

            if (is_array($field)) {
                foreach (['value', 'name', 'key', 'type', 'field'] as $candidate) {
                    if (isset($field[$candidate]) && is_string($field[$candidate])) {
                        return strtolower($field[$candidate]);
                    }
                }

                return null;
            }

            if (is_object($field)) {
                foreach (['value', 'name', 'key', 'type', 'field'] as $candidate) {
                    if (isset($field->{$candidate}) && is_string($field->{$candidate})) {
                        return strtolower($field->{$candidate});
                    }
                }

                if (method_exists($field, 'value')) {
                    $value = $field->value();

                    if (is_string($value)) {
                        return strtolower($value);
                    }
                }
            }

            return null;
        }, $fields);

        return array_values(array_filter(
            $normalized,
            fn ($value) => is_string($value) && $value !== ''
        ));
    }

    protected function hasKyc(VoucherInstructionsData $instructions): bool
    {
        $fields = $this->fieldNames($instructions);

        return in_array('selfie', $fields, true)
            || in_array('id_card', $fields, true)
            || in_array('government_id', $fields, true);
    }

    protected function hasOtp(VoucherInstructionsData $instructions): bool
    {
        return data_get($instructions, 'cash.validation.payable') === 'otp'
            || data_get($instructions, 'cash.validation.otp') !== null;
    }

    protected function hasSelfie(VoucherInstructionsData $instructions): bool
    {
        return in_array('selfie', $this->fieldNames($instructions), true);
    }

    protected function hasSignature(VoucherInstructionsData $instructions): bool
    {
        return in_array('signature', $this->fieldNames($instructions), true);
    }

    protected function hasLocation(VoucherInstructionsData $instructions): bool
    {
        return data_get($instructions, 'cash.validation.location') !== null
            || data_get($instructions, 'cash.validation.radius') !== null;
    }

    protected function hasWebhook(VoucherInstructionsData $instructions): bool
    {
        $webhook = data_get($instructions, 'feedback.webhook');

        return is_string($webhook) && $webhook !== '';
    }

    protected function hasEmailFeedback(VoucherInstructionsData $instructions): bool
    {
        $email = data_get($instructions, 'feedback.email');

        return is_string($email) && $email !== '';
    }

    protected function hasSmsFeedback(VoucherInstructionsData $instructions): bool
    {
        $mobile = data_get($instructions, 'feedback.mobile');

        return is_string($mobile) && $mobile !== '';
    }
}
