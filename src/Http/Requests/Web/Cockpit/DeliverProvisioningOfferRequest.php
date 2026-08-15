<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;

final class DeliverProvisioningOfferRequest extends FormRequest
{
    public function authorize(ProvisioningOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), ProvisioningOperatorCapability::Issue);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'recipient' => [
                'required', 'string', 'max:255',
                Rule::when($this->input('channel') === 'email', ['email:rfc']),
                Rule::when($this->input('channel') === 'sms', ['regex:/^(?:\+?63|0)9\d{9}$/']),
            ],
            'claim_token' => ['required', 'string', 'size:64'],
        ];
    }
}
