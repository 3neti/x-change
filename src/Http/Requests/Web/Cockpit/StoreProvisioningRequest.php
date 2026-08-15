<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

final class StoreProvisioningRequest extends FormRequest
{
    public function authorize(ProvisioningOperatorAuthority $authority): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && $authority->allows($operator, ProvisioningOperatorCapability::Request);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'seat_reference' => ['nullable', 'string', 'max:26', 'exists:x_provisioning_seats,reference'],
            'profile' => [
                'nullable',
                'required_without:seat_reference',
                Rule::enum(ProvisioningProfile::class),
                Rule::in(array_keys((array) config('x-change.provisioning.operator_profiles', []))),
            ],
            'purpose' => ['required', 'string', 'max:255'],
            'capabilities' => ['present', 'array', 'max:20'],
            'capabilities.*' => ['string', 'max:100'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seatReference = $this->input('seat_reference');
            $profile = is_string($seatReference) && $seatReference !== ''
                ? ProvisioningSeat::query()->where('reference', $seatReference)->value('profile')
                : $this->input('profile');
            $profileValue = $profile instanceof ProvisioningProfile ? $profile->value : (string) $profile;
            $profileConfig = (array) config("x-change.provisioning.operator_profiles.{$profileValue}", []);
            $allowed = array_values((array) ($profileConfig['capabilities'] ?? []));
            $selected = array_values(array_unique(array_map('strval', (array) $this->input('capabilities', []))));

            if (array_diff($selected, $allowed) !== []) {
                $validator->errors()->add('capabilities', 'One or more capabilities are unavailable for this profile.');
            }

            if (($profileConfig['activation_gate'] ?? 'operator_authority') === 'operator_authority'
                && $selected === []) {
                $validator->errors()->add('capabilities', 'Select at least one explicit capability.');
            }
        }];
    }
}
