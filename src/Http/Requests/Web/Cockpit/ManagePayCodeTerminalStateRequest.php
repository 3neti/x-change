<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\PayCodeTerminalAction;

final class ManagePayCodeTerminalStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => mb_strtolower(trim((string) $this->input('action'))),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(PayCodeTerminalAction::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
