<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use LBHurtado\XChange\Enums\CockpitPayeeKind;
use LBHurtado\XChange\Services\Cockpit\CockpitPayeePolicy;

trait ValidatesCockpitPayeePolicy
{
    /**
     * @return array<int, callable(Validator): void>
     */
    protected function cockpitPayeePolicyValidators(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('metadata.custom.cockpit.source') !== 'cockpit.quick-generate') {
                    return;
                }

                $kind = CockpitPayeeKind::tryFrom((string) $this->input('metadata.custom.cockpit.payee.kind'));

                if ($kind === null) {
                    $validator->errors()->add('metadata.custom.cockpit.payee.kind', 'The Pay To classification is missing or invalid.');

                    return;
                }

                if ($kind === CockpitPayeeKind::Email) {
                    $validator->errors()->add('metadata.custom.cockpit.payee.kind', 'Email-bound Pay Codes require the email OTP capability, which is not available yet.');

                    return;
                }

                if ($kind === CockpitPayeeKind::Invalid) {
                    $validator->errors()->add('metadata.custom.cockpit.payee.kind', 'Correct the Pay To value before issuing this Pay Code.');

                    return;
                }

                if ($kind === CockpitPayeeKind::Mobile) {
                    $this->validateMobilePayee($validator);
                }

                if ($kind === CockpitPayeeKind::Secret) {
                    $this->validateSecretPayee($validator);
                }

                if ($kind === CockpitPayeeKind::Vendor) {
                    $this->validateVendorPayee($validator);
                }
            },
        ];
    }

    private function validateMobilePayee(Validator $validator): void
    {
        $mobile = (string) $this->input('cash.validation.mobile');
        $policy = app(CockpitPayeePolicy::class)->classify($mobile);
        $fields = $this->input('inputs.fields', []);
        $requirements = $this->input('inputs.requirements', []);

        if ($policy->kind !== CockpitPayeeKind::Mobile) {
            $validator->errors()->add('cash.validation.mobile', 'A valid Philippine mobile is required for this Pay To value.');
        }

        if (! is_array($fields) || ! in_array('mobile', $fields, true) || ! in_array('otp', $fields, true)) {
            $validator->errors()->add('inputs.fields', 'Mobile and OTP inputs are required for a mobile-bound Pay Code.');
        }

        if (! is_array($requirements) || ! in_array('otp', $requirements, true)) {
            $validator->errors()->add('inputs.requirements', 'OTP verification is required for a mobile-bound Pay Code.');
        }

        if ($this->input('cash.validation.mobile_verification') !== 'otp' || $this->input('validation.otp.required') !== true) {
            $validator->errors()->add('validation.otp', 'OTP must remain enabled for a mobile-bound Pay Code.');
        }
    }

    private function validateSecretPayee(Validator $validator): void
    {
        $secret = $this->input('cash.validation.secret');

        if (! is_string($secret) || mb_strlen($secret) < 4 || mb_strlen($secret) > 255) {
            $validator->errors()->add('cash.validation.secret', 'The release secret must contain 4 to 255 characters.');

            return;
        }

        if ($secret === '[redacted secret]' && ! $this->allowsRedactedPayeeSecret()) {
            $validator->errors()->add('cash.validation.secret', 'The actual release secret is required for issuance.');
        }
    }

    private function validateVendorPayee(Validator $validator): void
    {
        $alias = (string) $this->input('cash.validation.payable');
        $policy = app(CockpitPayeePolicy::class)->classify('@'.$alias);

        if ($policy->kind !== CockpitPayeeKind::Vendor) {
            $validator->errors()->add('cash.validation.payable', 'A valid @vendor alias is required for this Pay To value.');
        }
    }

    protected function allowsRedactedPayeeSecret(): bool
    {
        return false;
    }
}
