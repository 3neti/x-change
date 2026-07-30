<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCampaignApprovalPayCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient' => trim((string) $this->input('recipient')),
            'request_token' => trim((string) $this->input('request_token')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channel = (string) $this->route('channel');

        return [
            'recipient' => [
                'required',
                'string',
                'max:191',
                Rule::when($channel === 'email', ['email:rfc']),
                Rule::when($channel === 'sms', ['regex:/^\+?[0-9][0-9\s\-]{7,31}$/']),
            ],
            'request_token' => ['required', 'uuid'],
        ];
    }
}
