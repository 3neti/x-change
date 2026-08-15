<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptProvisioningInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'responsibility_attestation' => ['required', 'accepted'],
        ];
    }
}
