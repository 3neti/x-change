<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;

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
        ];
    }
}
